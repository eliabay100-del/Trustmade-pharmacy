<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CustomerReturn;
use App\Models\Payment;
use App\Models\TillClosing;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TillClosingController extends Controller
{
    public function __construct(private AccountingService $accountingService)
    {
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $branchId = $this->resolveBranchId($request, $user);

        $periodStart = $this->periodStart($user->id, $branchId);
        $expectedCash = $this->cashCollected($user->id, $branchId, $periodStart);
        $otherPayments = $this->nonCashCollected($user->id, $branchId, $periodStart);

        $historyQuery = TillClosing::with(['user', 'branch'])->latest('closed_at');
        if ($user->branch_id) {
            $historyQuery->where('branch_id', $user->branch_id);
        }
        $closings = $historyQuery->paginate(10)->withQueryString();

        // Single-branch install: no picker, the close belongs to the pharmacy.
        $branchId = $user->branch_id ?: Branch::current()?->id;

        return view('till-closings.index', compact(
            'branchId',
            'periodStart',
            'expectedCash',
            'otherPayments',
            'closings'
        ));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'counted_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $branchId = $this->resolveBranchId($request, $user);
        if (!$branchId) {
            return back()->withErrors(['branch_id' => 'No branch is set up yet — run the installer first.'])->withInput();
        }

        try {
            DB::transaction(function () use ($request, $user, $branchId) {
                // Recompute inside the transaction so two overlapping
                // closes can't both lock in the same expected figure.
                $periodStart = $this->periodStart($user->id, $branchId);
                $expectedCash = $this->cashCollected($user->id, $branchId, $periodStart);
                $counted = round((float) $request->counted_cash, 2);

                $closing = TillClosing::create([
                    'user_id' => $user->id,
                    'branch_id' => $branchId,
                    'period_start' => $periodStart,
                    'closed_at' => now(),
                    'expected_cash' => $expectedCash,
                    'counted_cash' => $counted,
                    'variance' => round($counted - $expectedCash, 2),
                    'notes' => $request->notes,
                ]);

                // Put the cash difference in the books so the ledger cash
                // balance matches the physical drawer. No-op when it balances.
                $this->accountingService->recordTillVariance($closing->variance, $branchId, $closing->id);
            });

            return redirect()->route('till-closings.index')->with('success', 'Till closed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    private function resolveBranchId(Request $request, $user): ?int
    {
        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        $requested = $request->input('branch_id');
        return $requested ? (int) $requested : Branch::current()?->id;
    }

    private function periodStart(int $userId, ?int $branchId): Carbon
    {
        // Next close starts after the previous close for this cashier
        // at this branch; if they've never closed, start of today.
        $query = TillClosing::where('user_id', $userId);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $last = $query->latest('closed_at')->first();

        return $last?->closed_at?->copy() ?? Carbon::today()->startOfDay();
    }

    private function cashCollected(int $userId, ?int $branchId, Carbon $periodStart): float
    {
        if (!$branchId) {
            return 0.0;
        }

        $cashIn = (float) Payment::where('payment_method', 'cash')
            ->where('created_at', '>', $periodStart)
            ->whereHas('order', function ($q) use ($userId, $branchId) {
                $q->where('user_id', $userId)->where('branch_id', $branchId);
            })
            ->sum('amount');

        $cashRefunds = (float) CustomerReturn::where('refund_method', 'cash')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('created_at', '>', $periodStart)
            ->sum('refund_amount');

        return round($cashIn - $cashRefunds, 2);
    }

    private function nonCashCollected(int $userId, ?int $branchId, Carbon $periodStart): array
    {
        if (!$branchId) {
            return [];
        }

        return Payment::where('payment_method', '!=', 'cash')
            ->where('created_at', '>', $periodStart)
            ->whereHas('order', function ($q) use ($userId, $branchId) {
                $q->where('user_id', $userId)->where('branch_id', $branchId);
            })
            ->get()
            ->groupBy('payment_method')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->all();
    }
}
