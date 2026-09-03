<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use App\Models\Drug;
use App\Models\Supplier;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockCard;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        // BUG FIX: previously showed every branch's purchases to every
        // user, regardless of which branch they belong to.
        $user = auth()->user();
        $query = PurchaseBatch::with(['drug', 'product', 'supplier', 'branch']);
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $purchases = $query->latest()->paginate(10);

        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $drugs = Drug::all();
        $products = Product::where('active', true)->orderBy('name')->get();
        $suppliers = Supplier::all();
        $branches = Branch::all();
        return view('purchases.create', compact('drugs', 'products', 'suppliers', 'branches'));
    }

    public function store(Request $request)
    {
        if (!$request->has('items')) {
            $request->merge(['items' => [[
                'item_type' => $request->item_type ?: 'drug',
                'item_id' => $request->item_id ?: $request->drug_id,
                'batch_number' => $request->batch_number,
                'quantity_received' => $request->quantity_received,
                'quantity_unit' => $request->quantity_unit,
                'cost_per_unit' => $request->cost_per_unit,
                'selling_price_per_unit' => $request->selling_price_per_unit,
                'expiry_date' => $request->expiry_date,
            ]]]);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:drug,product',
            'items.*.item_id' => 'required|integer',
            'items.*.batch_number' => 'required|string|max:255',
            'items.*.quantity_received' => 'required|integer|min:1',
            'items.*.quantity_unit' => 'required|in:tablet,strip,pack',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
            'items.*.selling_price_per_unit' => 'required|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date|after:today',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->items as $line) {
                $item = $line['item_type'] === 'product' ? Product::findOrFail($line['item_id']) : Drug::findOrFail($line['item_id']);
                $isDrug = $item instanceof Drug;
                $qtyBase = $isDrug ? $item->toBaseQuantity((int) $line['quantity_received'], $line['quantity_unit']) : (int) $line['quantity_received'];
                $totalCost = round((float) $line['quantity_received'] * (float) $line['cost_per_unit'], 2);
                $costPerBase = $isDrug ? $item->pricePerBase((float) $line['cost_per_unit'], $line['quantity_unit']) : (float) $line['cost_per_unit'];
                $sellingPerBase = $isDrug ? $item->pricePerBase((float) $line['selling_price_per_unit'], $line['quantity_unit']) : (float) $line['selling_price_per_unit'];
                $batch = PurchaseBatch::create([
                    'drug_id' => $isDrug ? $item->id : null,
                    'product_id' => $isDrug ? null : $item->id,
                    'supplier_id' => $request->supplier_id,
                    'branch_id' => $request->branch_id,
                    'batch_number' => $line['batch_number'],
                    'quantity_received' => $qtyBase,
                    'quantity_remaining' => $qtyBase,
                    'cost_per_unit' => round($costPerBase, 2),
                    'selling_price_per_unit' => round($sellingPerBase, 2),
                    'expiry_date' => $line['expiry_date'] ?? null,
                    'purchase_date' => $request->purchase_date,
                ]);
                StockCard::create([
                    'purchase_batch_id' => $batch->id, 'branch_id' => $request->branch_id,
                    'transaction_type' => 'in', 'quantity' => $qtyBase, 'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $totalCost, 'reference_type' => 'purchase', 'reference_id' => $batch->id,
                    'transaction_date' => now(),
                ]);
                $this->accountingService->postEntry('Purchase: ' . $item->name, $request->branch_id, 'purchase', $batch->id, [
                    ['account_code' => '1200', 'debit' => $totalCost, 'credit' => 0, 'desc' => 'Inventory'],
                    ['account_code' => '2000', 'debit' => 0, 'credit' => $totalCost, 'desc' => 'Payable'],
                ]);
            }

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase recorded!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
