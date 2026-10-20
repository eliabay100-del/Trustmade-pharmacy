<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\DrugCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DrugController extends Controller
{
    public function index(Request $request)
    {
        $query = Drug::with('category')
            ->withSum('purchaseBatches as current_stock', 'quantity_remaining')
            ->orderBy('name')
            ;

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(function ($drugQuery) use ($term) {
                $drugQuery->where('name', 'like', "%{$term}%")
                    ->orWhere('generic_name', 'like', "%{$term}%")
                    ->orWhere('strength', 'like', "%{$term}%")
                    ->orWhere('form', 'like', "%{$term}%");
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);

        $drugs = $query->paginate(15)->withQueryString();
        $categories = DrugCategory::orderBy('name')->get();
        return view('drugs.index', compact('drugs', 'categories'));
    }

    public function create()
    {
        $categories = DrugCategory::orderBy('name')->get();

        if ($categories->isEmpty()) {
            return redirect()->route('categories.create')
                ->with('error', 'Add at least one drug category before adding a drug.');
        }

        return view('drugs.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['minimum_stock_level'] = $validated['minimum_stock_level'] ?? 0;

        Drug::create($validated);

        return redirect()->route('drugs.index')->with('success', 'Drug added.');
    }

    public function edit(Drug $drug)
    {
        $categories = DrugCategory::orderBy('name')->get();
        return view('drugs.edit', compact('drug', 'categories'));
    }

    public function update(Request $request, Drug $drug)
    {
        $drug->update($this->validated($request, $drug));

        return redirect()->route('drugs.index')->with('success', 'Drug updated.');
    }

    public function destroy(Drug $drug)
    {
        // Deleting a drug with purchase history would orphan every batch, stock
        // card entry, and sale record tied to it — block instead.
        if ($drug->purchaseBatches()->exists()) {
            return back()->with('error', 'Cannot delete this drug — it has purchase/stock history. Consider editing it instead.');
        }

        $drug->delete();

        return redirect()->route('drugs.index')->with('success', 'Drug deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Drug $drug = null): array
    {
        // A drug whose existing unit is outside the usual list must stay
        // selectable. Otherwise opening the form and saving it would quietly
        // rewrite that unit to whichever option happened to be first.
        $tops = ['pack', 'box', 'bottle', 'tin', 'vial', 'tube'];
        $bases = ['tablet', 'capsule', 'suppository'];

        if ($drug?->unit_top) {
            $tops[] = strtolower((string) $drug->unit_top);
        }
        if ($drug?->unit_base) {
            $bases[] = strtolower((string) $drug->unit_base);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:100',
            'form' => 'nullable|string|max:100',
            'category_id' => 'required|exists:drug_categories,id',
            'minimum_stock_level' => 'nullable|numeric|min:0',

            // Packing. Optional so that older callers which still post
            // unit_of_measure / units_per_strip keep working.
            'unit_top' => ['nullable', Rule::in(array_values(array_unique($tops)))],
            'inner' => ['nullable', Rule::in(['none', 'strip', 'sachet', 'ampoule', 'bottle', 'loose'])],
            'top_holds' => [
                'nullable', 'integer', 'min:1', 'max:10000',
                Rule::requiredIf(fn () => $this->holdsContents($request)),
            ],
            'middle_holds' => [
                'nullable', 'integer', 'min:1', 'max:10000',
                // A strip that holds nothing would divide every price by one
                // without saying so.
                Rule::requiredIf(fn () => $this->holdsContents($request)
                    && $request->input('inner') === 'strip'),
            ],
            'unit_base' => ['nullable', Rule::in(array_values(array_unique($bases)))],

            // Legacy geometry, still accepted.
            'unit_of_measure' => 'nullable|string|max:50',
            'units_per_strip' => 'nullable|integer|min:1|max:10000',
            'strips_per_pack' => 'nullable|integer|min:1|max:10000',
        ], [
            'top_holds.required' => 'Tell us how many fit in one pack or box.',
            'middle_holds.required' => 'Tell us how many tablets or capsules one strip holds.',
        ]);

        unset($validated['inner']);

        if ($request->filled('unit_top')) {
            return array_merge($validated, $this->packingAttributes($request));
        }

        // No packing posted: the pre-existing columns are the source, and the
        // model falls back to the old tablet -> strip -> pack geometry.
        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        $validated['units_per_strip'] = $validated['units_per_strip'] ?? 1;
        $validated['strips_per_pack'] = $validated['strips_per_pack'] ?? 1;

        return $validated;
    }

    /**
     * Does this packing describe anything countable inside the top unit?
     *
     * Bottles, tins, vials and tubes are sold whole. Everything else - pack,
     * box, or a unit an existing record already uses - may hold contents,
     * which is what the form's "contains" answer decides.
     */
    private function holdsContents(Request $request): bool
    {
        $top = strtolower((string) $request->input('unit_top'));

        if (in_array($top, ['bottle', 'tin', 'vial', 'tube'], true)) {
            return false;
        }

        return $request->input('inner', 'none') !== 'none';
    }

    /**
     * Turn the form's sentence into the rungs the model stores.
     *
     *   Bought as  Pack  containing  10  Strips
     *   Each strip holds 10 Capsules
     *
     *     -> top=pack, top_holds=10, middle=strip, middle_holds=10, base=capsule
     *
     * A bottle, tin, vial or tube is sold whole, so it has one rung and the
     * base unit is the container itself. Choosing "nothing" for a pack says
     * the same thing: the pack is what you count.
     *
     * @return array<string, mixed>
     */
    private function packingAttributes(Request $request): array
    {
        $top = strtolower((string) $request->input('unit_top'));
        $inner = (string) $request->input('inner', 'none');
        $held = max(1, (int) $request->input('top_holds'));
        $perStrip = max(1, (int) $request->input('middle_holds'));
        $base = strtolower((string) $request->input('unit_base', 'tablet'));

        // These four are never opened: the container is the counting unit.
        // Everything else - pack, box, or any unit a record already uses - may
        // hold something countable.
        $soldWhole = in_array($top, ['bottle', 'tin', 'vial', 'tube'], true);

        $single = [
            'unit_top' => $top,
            'unit_middle' => null,
            'unit_base' => $top,
            'top_holds' => null,
            'middle_holds' => null,
            'sell_top' => true,
            'sell_middle' => false,
            'sell_base' => true,
        ];

        // Sold as it arrives: the container is the counting unit.
        if ($soldWhole || $inner === 'none') {
            return $single;
        }

        if ($inner === 'strip') {
            return [
                'unit_top' => $top,
                'unit_middle' => 'strip',
                'unit_base' => $base,
                'top_holds' => $held,
                'middle_holds' => $perStrip,
                'sell_top' => true,
                'sell_middle' => $request->boolean('sell_middle'),
                'sell_base' => $request->boolean('sell_base'),
            ];
        }

        // Sachets, ampoules, bottles or loose tablets sit directly in the pack.
        return [
            'unit_top' => $top,
            'unit_middle' => null,
            'unit_base' => $inner === 'loose' ? $base : $inner,
            'top_holds' => $held,
            'middle_holds' => null,
            'sell_top' => true,
            'sell_middle' => false,
            'sell_base' => $request->boolean('sell_base'),
        ];
    }
}
