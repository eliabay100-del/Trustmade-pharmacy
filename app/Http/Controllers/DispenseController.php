<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\IssuedItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispenseController extends Controller
{
    protected $inventoryService;
    protected $accountingService;

    public function __construct(InventoryService $inventoryService, AccountingService $accountingService)
    {
        $this->inventoryService = $inventoryService;
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        // BUG FIX: previously showed every branch's orders to every user.
        // If the logged-in user has a branch_id, scope to it; a null
        // branch_id (Owner/Admin, by design) means all-branch access,
        // so no filter is applied in that case.
        $user = auth()->user();
        $query = Order::with(['customer', 'items.purchaseBatch.drug', 'payments']);
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $orders = $query->latest()->paginate(10);

        return view('dispense.index', compact('orders'));
    }

    /**
     * The sale screen.
     *
     * Deliberately does not load the catalogue. It used to render one
     * <option> per drug and build a JSON packing map covering every row, which
     * measured at roughly 450 KB and 1.2 seconds with 800 drugs - growing
     * linearly, on the screen a cashier opens all day, on the slowest
     * connection the pharmacy has. Items are now found through searchDrug() as
     * the cashier types, and that endpoint returns the same packing, marker
     * and shelf-price data for the handful of rows that actually match.
     */
    public function create()
    {
        $branchId = auth()->user()->branch_id ?: Branch::current()?->id;
        $paymentMethods = Payment::METHODS;
        $recurringCustomers = Customer::where('is_recurring', true)->orderBy('name')->get();

        // Only used to decide whether to show the "nothing in the catalogue
        // yet" nudge on a fresh install. The catalogue itself is not loaded, so
        // this has to be answered on its own - but an EXISTS is cheap next to
        // the page it saves.
        $hasCatalogueItems = Drug::exists() || Product::where('active', true)->exists();

        return view('dispense.create', compact('recurringCustomers', 'branchId', 'paymentMethods', 'hasCatalogueItems'));
    }

    public function store(Request $request)
    {
        $items = collect($request->input('items', []))->map(function ($item) {
            if (isset($item['drug_id']) && !str_contains((string) $item['drug_id'], ':')) {
                $item['drug_id'] = 'drug:' . $item['drug_id'];
            }
            return $item;
        })->all();
        $request->merge(['items' => $items]);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.drug_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit' => 'required|in:tablet,strip,pack',
            'items.*.selling_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'customer_mode' => 'required|in:existing,new,anonymous',
            'customer_id' => 'required_if:customer_mode,existing|nullable|exists:customers,id',
            'new_customer_name' => 'required_if:customer_mode,new|nullable|string|max:255',
            'new_customer_phone' => 'nullable|string|max:50',
            'is_recurring' => 'nullable|boolean',
            'reminder_interval_days' => 'nullable|integer|min:1|max:365',
            'condition_note' => 'nullable|string|max:255',
            // Phase 1: one row for a single-method sale; two or more
            // rows for a split (e.g. cash + Telebirr). Sum is checked
            // against the cart total inside the transaction below.
            'payments' => 'required|array|min:1',
            'payments.*.payment_method' => 'required|in:' . implode(',', array_keys(Payment::METHODS)),
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.transaction_reference' => 'nullable|string|max:255',
        ]);

        try {
            $branchId = auth()->user()->branch_id ?: Branch::current()?->id;

            $customerId = null;
            if ($request->customer_mode === 'existing') {
                $customerId = $request->customer_id;
            } elseif ($request->customer_mode === 'new') {
                $isRecurring = $request->boolean('is_recurring');
                $customer = Customer::create([
                    'name' => $request->new_customer_name,
                    'phone' => $request->new_customer_phone,
                    'is_recurring' => $isRecurring,
                    'reminder_interval_days' => $isRecurring ? $request->reminder_interval_days : null,
                    'condition_note' => $isRecurring ? $request->condition_note : null,
                ]);
                $customerId = $customer->id;
            }

            $order = DB::transaction(function () use ($request, $branchId, $customerId) {
                $order = Order::create([
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'user_id' => auth()->id(),
                    'total_amount' => 0,
                    'status' => 'completed',
                ]);

                $orderTotal = 0;
                $orderCogs = 0;

                $discountRows = [];

                foreach ($request->items as $item) {
                    [$itemType, $itemId] = explode(':', $item['drug_id'], 2);
                    $itemModel = $itemType === 'product' ? Product::findOrFail($itemId) : Drug::findOrFail($itemId);
                    $qtyBase = $itemModel->toBaseQuantity((int) $item['quantity'], $item['unit']);

                    // Discounts are an Owner/Admin-only lever. A posted value
                    // from anyone else is refused rather than quietly ignored,
                    // so a clerk cannot sneak a price down.
                    $discount = round((float) ($item['discount'] ?? 0), 2);
                    if ($discount < 0) {
                        throw new \Exception('A discount cannot be negative.');
                    }
                    if ($discount > 0 && auth()->user()->role !== User::ROLE_OWNER_ADMIN) {
                        throw new \Exception('Only the Owner/Admin can apply a discount.');
                    }

                    // The price is never taken from the form. It is re-derived
                    // from the newest batch that still has stock, so a seller
                    // cannot charge a different amount by editing the (locked)
                    // price box in the browser.
                    $shelfBase = $this->inventoryService->shelfPrice($itemType, (int) $itemId, $branchId);
                    if ($shelfBase === null) {
                        throw new \Exception('No priced stock is on hand for this item.');
                    }

                    // Discount is entered per selected unit, then converted to
                    // the base unit the books are kept in.
                    $factor = $itemModel->baseUnitsPer($item['unit']);
                    $discountPerBase = $factor > 0 ? round($discount / $factor, 4) : $discount;
                    if ($shelfBase <= 0) {
                        throw new \Exception('This item has no selling price on its batches yet.');
                    }
                    if ($discountPerBase >= $shelfBase) {
                        throw new \Exception('That discount is the whole price. Reduce it so the item still has a price.');
                    }
                    $pricePerBase = round($shelfBase - $discountPerBase, 4);

                    $allocations = $this->inventoryService->selectBatchesForDispensing(
                        $itemType, (int) $itemId, $branchId, $qtyBase
                    );

                    $orderCogs += $this->inventoryService->calculateCOGS($allocations);

                    $orderTotal += $this->inventoryService->recordIssuance(
                        $allocations, $customerId, $branchId, $pricePerBase, $order->id, $itemType, (int) $itemId, $discountPerBase
                    );

                    if ($discountPerBase > 0) {
                        $discountRows[] = [
                            'item' => ($itemModel->name ?? '—') . ($item['unit'] !== 'tablet' ? ' (' . $item['unit'] . ')' : ''),
                            'shelf_price_per_base_unit' => $shelfBase,
                            'charged_price_per_base_unit' => $pricePerBase,
                            'discount_per_base_unit' => $discountPerBase,
                            'discount_per_selected_unit' => $discount,
                        ];
                    }
                }

                // Audit trail: who discounted, which item, and both prices.
                foreach ($discountRows as $row) {
                    ActivityLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'discount',
                        'model_type' => 'Order',
                        'model_id' => $order->id,
                        'changes' => $row,
                    ]);
                }

                $order->update(['total_amount' => $orderTotal]);

                $paymentRows = [];
                foreach ($request->payments as $payment) {
                    $paymentRows[] = [
                        'payment_method' => $payment['payment_method'],
                        'amount' => round((float) $payment['amount'], 2),
                    ];
                }

                $paidTotal = round(collect($paymentRows)->sum('amount'), 2);
                if ($paidTotal !== round((float) $orderTotal, 2)) {
                    throw new \Exception(
                        'Payments must sum to the sale total (' . number_format($orderTotal, 2) . '). Got ' . number_format($paidTotal, 2) . '.'
                    );
                }

                foreach ($paymentRows as $i => $payment) {
                    Payment::create([
                        'order_id' => $order->id,
                        'payment_method' => $payment['payment_method'],
                        'amount' => $payment['amount'],
                        'transaction_reference' => $request->payments[$i]['transaction_reference'] ?? null,
                    ]);
                }

                $this->accountingService->recordSale($orderTotal, $orderCogs, $branchId, $order->id, $paymentRows);

                return $order;
            }, 3); // retry up to 3x on a transient deadlock / lock-wait timeout

            return redirect()->route('dispense.show', $order->id)->with('success', 'Sale completed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Order $order)
    {
        // BUG FIX: no ownership check previously — a user could view any
        // order by guessing its ID in the URL, regardless of branch.
        $user = auth()->user();
        if ($user->branch_id && $order->branch_id !== $user->branch_id) {
            abort(403, 'You do not have access to this order.');
        }

        $order->load(['customer', 'user', 'items.purchaseBatch.drug', 'payments']);
        return view('dispense.show', compact('order'));
    }

    /**
     * Type-ahead for the sale screen.
     *
     * The sale screen used to carry the entire catalogue in its HTML so the
     * browser could filter it, which cost ~450 KB and over a second at 800
     * drugs. This replaces it: the browser asks for what has been typed so far
     * and gets back only the rows that match, each already carrying its unit
     * packing, dispensing marker and locked shelf price, so a line can be
     * priced and unit-synchronised without a second round trip.
     *
     * Searches drugs AND products. Products have always been sellable through
     * store() - they carry the same "product:<id>" key - but the old preloaded
     * <select> listed only drugs, so there was no way to reach one on screen.
     *
     * The term is escaped so that a user typing "%" searches for a literal
     * percent sign instead of matching every row.
     */
    public function searchDrug(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        // One or two characters matches most of a pharmacy's catalogue and is
        // never what the cashier meant.
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $term = Str::of($term)->replace(['%', '_'], ['\%', '\_'])->value();
        $like = "%{$term}%";

        $branchId = auth()->user()->branch_id ?: Branch::current()?->id;
        $limit = 20;

        $drugs = Drug::where(function ($query) use ($like) {
            $query->where('name', 'like', $like)
                ->orWhere('generic_name', 'like', $like)
                ->orWhere('strength', 'like', $like);
        })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'strength', 'unit_of_measure', 'units_per_strip', 'strips_per_pack']);

        $products = Product::where('active', true)
            ->where(function ($query) use ($like) {
                $query->where('name', 'like', $like)->orWhere('sku', 'like', $like);
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name']);

        // Markers and prices are worked out for the rows that matched, not for
        // the whole catalogue on every keystroke.
        $drugIds = $drugs->pluck('id')->all();
        $productIds = $products->pluck('id')->all();
        $drugMarkers = $this->inventoryService->dispenseMarkers('drug', $drugIds, $branchId);
        $drugPrices = $this->inventoryService->shelfPrices('drug', $drugIds, $branchId);
        $productMarkers = $this->inventoryService->dispenseMarkers('product', $productIds, $branchId);
        $productPrices = $this->inventoryService->shelfPrices('product', $productIds, $branchId);

        $results = [];

        foreach ($drugs as $drug) {
            $results[] = $this->catalogEntry(
                'drug:' . $drug->id, $drug->name, $drug->strength,
                $drug->baseUnitLabel(), $drug->unitsPerStrip(), $drug->unitsPerPack(),
                $drugMarkers[$drug->id] ?? null, $drugPrices[$drug->id] ?? null
            );
        }

        foreach ($products as $product) {
            // A product has no strip/pack geometry - it is counted in whole
            // pieces - so its unit list stays "piece (base)".
            $results[] = $this->catalogEntry(
                'product:' . $product->id, $product->name, null,
                'piece', 1, 1,
                $productMarkers[$product->id] ?? null, $productPrices[$product->id] ?? null
            );
        }

        return response()->json(['results' => array_slice($results, 0, $limit)]);
    }

    /**
     * One catalogue row in the shape the sale screen consumes.
     *
     * @param  array{rank: int, quantity: int}|null  $marker
     */
    protected function catalogEntry(string $key, string $name, ?string $strength, string $base, int $strip, int $pack, ?array $marker, $price): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'strength' => $strength,
            'label' => trim($name . ($strength ? " ({$strength})" : '')),
            'base' => $base,
            'strip' => $strip,
            'pack' => $pack,
            // "#1 - 40 left": the batch FEFO will actually reach for.
            'marker' => $marker ? sprintf('#%d - %d left', $marker['rank'], $marker['quantity']) : null,
            // Locked shelf price per base unit. Re-derived server-side on save;
            // this only fills the read-only box as soon as the item is picked.
            'price' => $price === null ? null : round((float) $price, 2),
        ];
    }
}
