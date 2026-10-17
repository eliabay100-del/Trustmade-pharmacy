<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\Customer;
use App\Models\Order;
use App\Models\IssuedItem;
use App\Models\Payment;
use App\Models\Product;
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
        foreach ($drugs as $drug) $drug->catalog_key = 'drug:' . $drug->id;
        foreach ($products as $product) $product->catalog_key = 'product:' . $product->id;
        $drugs = $drugs->concat($products);
        $recurringCustomers = Customer::where('is_recurring', true)->orderBy('name')->get();
        $branchId = auth()->user()->branch_id;
        $paymentMethods = Payment::METHODS;
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
            'items.*.selling_price' => 'required|numeric|min:0',
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
            $branchId = auth()->user()->branch_id;

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

                foreach ($request->items as $item) {
                    [$itemType, $itemId] = explode(':', $item['drug_id'], 2);
                    $itemModel = $itemType === 'product' ? Product::findOrFail($itemId) : Drug::findOrFail($itemId);
                    $qtyBase = $itemModel->toBaseQuantity((int) $item['quantity'], $item['unit']);
                    $pricePerBase = $itemModel->pricePerBase((float) $item['selling_price'], $item['unit']);

                    $allocations = $this->inventoryService->selectBatchesForDispensing(
                        $itemType, (int) $itemId, $branchId, $qtyBase
                    );

                    $orderCogs += $this->inventoryService->calculateCOGS($allocations);

                    $orderTotal += $this->inventoryService->recordIssuance(
                        $allocations, $customerId, $branchId, $pricePerBase, $order->id, $itemType, (int) $itemId
                    );
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
