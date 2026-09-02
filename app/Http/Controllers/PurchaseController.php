<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use App\Models\Drug;
use App\Models\Supplier;
use App\Models\Branch;
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
        $query = PurchaseBatch::with(['drug', 'supplier', 'branch']);
        if ($user->branch_id) {
            $query->where('branch_id', $user->branch_id);
        }
        $purchases = $query->latest()->paginate(10);

        return view('purchases.index', compact('purchases'));
    }

    public function create()
    {
        $drugs = Drug::all();
        $suppliers = Supplier::all();
        $branches = Branch::all();
        return view('purchases.create', compact('drugs', 'suppliers', 'branches'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'drug_id' => 'required|exists:drugs,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'batch_number' => 'required|string',
            'quantity_received' => 'required|integer|min:1',
            'quantity_unit' => 'required|in:tablet,strip,pack',
            'cost_per_unit' => 'required|numeric|min:0',
            'selling_price_per_unit' => 'required|numeric|min:0',
            'expiry_date' => 'required|date|after:today',
            'purchase_date' => 'required|date',
        ]);

        $drug = Drug::findOrFail($request->drug_id);
        $qtyBase = $drug->toBaseQuantity((int) $request->quantity_received, $request->quantity_unit);
        $totalCost = round((float) $request->quantity_received * (float) $request->cost_per_unit, 2);
        $costPerBase = $drug->pricePerBase((float) $request->cost_per_unit, $request->quantity_unit);
        $sellingPerBase = $drug->pricePerBase((float) $request->selling_price_per_unit, $request->quantity_unit);

        DB::beginTransaction();
        try {
            $batch = PurchaseBatch::create([
                'drug_id' => $request->drug_id,
                'supplier_id' => $request->supplier_id,
                'branch_id' => $request->branch_id,
                'batch_number' => $request->batch_number,
                'quantity_received' => $qtyBase,
                'quantity_remaining' => $qtyBase,
                'cost_per_unit' => round($costPerBase, 2),
                'selling_price_per_unit' => round($sellingPerBase, 2),
                'expiry_date' => $request->expiry_date,
                'purchase_date' => $request->purchase_date,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $request->branch_id,
                'transaction_type' => 'in',
                'quantity' => $qtyBase,
                'unit_cost' => $batch->cost_per_unit,
                'total_amount' => $totalCost,
                'reference_type' => 'purchase',
                'reference_id' => $batch->id,
                'transaction_date' => now(),
            ]);

            $this->accountingService->postEntry(
                'Purchase: ' . $drug->name,
                $request->branch_id,
                'purchase',
                $batch->id,
                [
                    ['account_code' => '1200', 'debit' => $totalCost, 'credit' => 0, 'desc' => 'Inventory'],
                    ['account_code' => '2000', 'debit' => 0, 'credit' => $totalCost, 'desc' => 'Payable'],
                ]
            );

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase recorded!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
