<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\TransferService;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function index()
    {
        // BUG FIX: previously showed every transfer across every branch
        // to every user. A branch's relevant transfers are ones where it
        // is either the source OR the destination — filtered for
        // non-admin users, unfiltered (all branches) for Owner/Admin.
        $user = auth()->user();
        $query = StockTransfer::with(['fromBranch', 'toBranch', 'purchaseBatch.drug', 'purchaseBatch.product']);
        if ($user->branch_id) {
            $query->where(function ($q) use ($user) {
                $q->where('from_branch_id', $user->branch_id)
                  ->orWhere('to_branch_id', $user->branch_id);
            });
        }
        $transfers = $query->latest()->paginate(10);

        return view('transfers.index', compact('transfers'));
    }

    public function create()
    {
        // Branches list intentionally shows all branches (not just the
        // user's own) — you need to see every branch to pick a transfer
        // destination, and Branch Manager role already gates who can
        // reach this page at all via route middleware.
        $branches = Branch::all();
        $drugs = Drug::all();
        $products = Product::where('active', true)->orderBy('name')->get();
        return view('transfers.create', compact('branches', 'drugs', 'products'));
    }

    public function store(Request $request)
    {
        if (!$request->filled('item_type') && $request->filled('drug_id')) {
            $request->merge(['item_type' => 'drug', 'item_id' => $request->drug_id]);
        }
        $request->validate([
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|different:from_branch_id|exists:branches,id',
            'item_type' => 'required|in:drug,product',
            'item_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'quantity_unit' => 'required|in:tablet,strip,pack',
        ]);

        try {
            $item = $request->item_type === 'product' ? Product::findOrFail($request->item_id) : Drug::findOrFail($request->item_id);
            $qtyBase = $item instanceof Drug ? $item->toBaseQuantity((int) $request->quantity, $request->quantity_unit) : (int) $request->quantity;

            $this->transferService->transfer(
                $request->item_type,
                $request->item_id,
                $request->from_branch_id,
                $request->to_branch_id,
                $qtyBase,
                auth()->id()
            );
            return redirect()->route('transfers.index')->with('success', 'Transfer completed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
