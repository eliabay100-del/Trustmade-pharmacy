<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Branch;
use App\Models\Ledger;
use App\Services\AccountingService;
use Illuminate\Http\Request;

/**
 * Opening balances — the day-one figures a pharmacy starts with: cash in the
 * drawer, money in the bank/wallets, stock on hand, money customers owe, and
 * money owed to suppliers. Owner's capital is derived so the entry always
 * balances.
 *
 * The form asks for the balance each account SHOULD have and posts the
 * difference from what the ledger currently holds, so it is safe to re-open
 * and re-save: entering the same figures twice posts nothing.
 */
class OpeningBalanceController extends Controller
{
    /**
     * Editable lines. "sign" turns the user-facing amount (always a positive
     * number: what you have, or what you owe) into a signed ledger balance.
     * Assets are debit-normal (sign 1); payables are credit-normal (sign -1).
     */
    public const LINES = [
        ['code' => '1001', 'label' => 'Cash in hand',              'sign' => 1],
        ['code' => '1010', 'label' => 'Telebirr',                  'sign' => 1],
        ['code' => '1011', 'label' => 'CBE Birr',                  'sign' => 1],
        ['code' => '1012', 'label' => 'Bank',                      'sign' => 1],
        ['code' => '1013', 'label' => 'Card',                      'sign' => 1],
        ['code' => '1002', 'label' => 'Money owed to you',         'sign' => 1],
        ['code' => '1200', 'label' => 'Stock on hand (at cost)',   'sign' => 1],
        ['code' => '2000', 'label' => 'Money you owe suppliers',   'sign' => -1],
    ];

    public const EQUITY_CODE = '3000';

    public function __construct(private AccountingService $accountingService)
    {
    }

    public function edit(Request $request)
    {
        $user = auth()->user();
        $branchId = $this->resolveBranchId($request, $user);

        $rows = collect(self::LINES)->map(fn ($line) => $line + [
            // Show the user-facing figure (what you have / what you owe).
            'current' => round($this->currentNet($line['code'], $branchId) * $line['sign'], 2),
        ]);

        $branch = $branchId ? Branch::find($branchId) : null;

        return view('opening-balances.edit', compact('rows', 'branch', 'branchId'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'as_of' => 'nullable|date',
            'notes' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'balances' => 'required|array',
            'balances.*' => 'nullable|numeric|min:0',
        ]);

        $branchId = $this->resolveBranchId($request, $user);
        if (!$branchId) {
            return back()
                ->withErrors(['branch_id' => 'No branch is set up yet — run the installer first.'])
                ->withInput();
        }

        $lines = [];
        $netTotal = 0.0;

        foreach (self::LINES as $line) {
            $entered = round((float) ($validated['balances'][$line['code']] ?? 0), 2);
            $targetNet = round($entered * $line['sign'], 2);
            $delta = round($targetNet - $this->currentNet($line['code'], $branchId), 2);

            if ($delta === 0.0) {
                continue;
            }

            $lines[] = [
                'account_code' => $line['code'],
                'debit' => $delta > 0 ? $delta : 0,
                'credit' => $delta < 0 ? -$delta : 0,
                'desc' => 'Opening balance — ' . $line['label'],
            ];
            $netTotal += $delta;
        }

        // Owner's capital is whatever makes the sheet balance.
        $equity = round(-$netTotal, 2);
        if ($equity !== 0.0) {
            $lines[] = [
                'account_code' => self::EQUITY_CODE,
                'debit' => $equity > 0 ? $equity : 0,
                'credit' => $equity < 0 ? -$equity : 0,
                'desc' => 'Opening balance — owner capital',
            ];
        }

        if (empty($lines)) {
            return $this->backToForm($branchId)
                ->with('success', 'Those figures already match the books — nothing to post.');
        }

        try {
            $this->accountingService->postEntry(
                ($validated['notes'] ?? null) ?: 'Opening balances',
                $branchId,
                'opening_balance',
                null,
                $lines,
                $validated['as_of'] ?? null
            );
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return $this->backToForm($branchId)->with('success', 'Opening balances posted.');
    }

    private function backToForm(?int $branchId)
    {
        return redirect()->route(
            'opening-balances.edit',
            $branchId ? ['branch_id' => $branchId] : []
        );
    }

    private function resolveBranchId(Request $request, $user): ?int
    {
        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        $requested = $request->input('branch_id');

        return $requested ? (int) $requested : Branch::current()?->id;
    }

    /** Signed ledger balance (debit − credit) for an account, at a branch. */
    private function currentNet(string $code, ?int $branchId): float
    {
        $account = Account::where('code', $code)->first();
        if (!$account) {
            return 0.0;
        }

        $query = Ledger::where('account_id', $account->id);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return round((float) $query->sum('debit') - (float) $query->sum('credit'), 2);
    }
}
