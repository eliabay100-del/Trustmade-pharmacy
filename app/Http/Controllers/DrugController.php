<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\DrugCategory;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:100',
            'form' => 'nullable|string|max:100',
            'category_id' => 'required|exists:drug_categories,id',
            'unit_of_measure' => 'nullable|string|max:50',
            'units_per_strip' => 'nullable|integer|min:1|max:10000',
            'strips_per_pack' => 'nullable|integer|min:1|max:10000',
            'minimum_stock_level' => 'nullable|numeric|min:0',
        ]);

        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'pcs';
        $validated['units_per_strip'] = $validated['units_per_strip'] ?? 1;
        $validated['strips_per_pack'] = $validated['strips_per_pack'] ?? 1;
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'generic_name' => 'nullable|string|max:255',
            'strength' => 'nullable|string|max:100',
            'form' => 'nullable|string|max:100',
            'category_id' => 'required|exists:drug_categories,id',
            'unit_of_measure' => 'nullable|string|max:50',
            'units_per_strip' => 'nullable|integer|min:1|max:10000',
            'strips_per_pack' => 'nullable|integer|min:1|max:10000',
            'minimum_stock_level' => 'nullable|numeric|min:0',
        ]);

        $validated['units_per_strip'] = $validated['units_per_strip'] ?? 1;
        $validated['strips_per_pack'] = $validated['strips_per_pack'] ?? 1;

        $drug->update($validated);

        return redirect()->route('drugs.index')->with('success', 'Drug updated.');
    }

    public function destroy(Drug $drug)
    {
        // Deleting a drug with purchase history would orphan every batch,
        // stock card entry, and sale record tied to it — block instead.
        if ($drug->purchaseBatches()->exists()) {
            return back()->with('error', 'Cannot delete this drug — it has purchase/stock history. Consider editing it instead.');
        }

        $drug->delete();

        return redirect()->route('drugs.index')->with('success', 'Drug deleted.');
    }
}
