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

    public function create()
    {
        $drugs = Drug::all();
        $products = Product::where('active', true)->orderBy('name')->get();
        $branchId = auth()->user()->branch_id ?: Branch::current()?->id;
        $paymentMethods = Payment::METHODS;

        // One query each for the dispensing marker ("#1 · 40 left") and the
        // locked shelf price. Both are recomputed on every page load.
        // Keyed by catalog_key, not by id, because a drug id and a product id
        // can be the same number.
        $markers = [];
        $prices = [];
        foreach (['drug' => $drugs, 'product' => $products] as $type => $collection) {
            $ids = $collection->pluck('id')->all();
            foreach ($this->inventoryService->dispenseMarkers($type, $ids, $branchId) as $id => $marker) {
                $markers[$type . ':' . $id] = $marker;
            }
            foreach ($this->inventoryService->shelfPrices($type, $ids, $branchId) as $id => $price) {
                $prices[$type . ':' . $id] = $price;
            }
        }

        foreach ($drugs as $drug) $drug->catalog_key = 'drug:' . $drug->id;
        foreach ($products as $product) $product->catalog_key = 'product:' . $product->id;

        $items = $drugs->concat($products);
        foreach ($items as $item) {
            $marker = $markers[$item->catalog_key] ?? null;
            $item->dispense_marker = $marker ? sprintf('#%d · %d left', $marker['rank'], $marker['quantity']) : null;
            $item->shelf_price = $prices[$item->catalog_key] ?? null;
        }

        $recurringCustomers = Customer::where('is_recurring', true)->orderBy('name')->get();
        return view('dispense.create', compact('drugs', 'recurringCustomers', 'branchId', 'paymentMethods'));
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

                foreach ($paymentRows as $payment) {
                    Payment::create([
                        'order_id' => $order->id,
                        'payment_method' => $payment['payment_method'],
                        'amount' => $payment['amount'],
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

    public function searchDrug(Request $request)
    {
        $query = Str::of((string) $request->input('q'))
            ->replace(['%', '_'], ['\%', '\_'])
            ->value();
        $results = Drug::where('name', 'like', "%{$query}%")->limit(5)->get([
            'id', 'name', 'strength', 'unit_of_measure', 'units_per_strip', 'strips_per_pack',
        ]);
        return response()->json($results);
    }
}
