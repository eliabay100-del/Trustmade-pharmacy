<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $entries = JournalEntry::with('ledgers.account')
            ->where('branch_id', auth()->user()->branch_id)
            ->latest()
            ->paginate(20);

        return view('journal-entries.index', compact('entries'));
    }

    public function create()
    {
        $accounts = Account::where('active', true)->orderBy('code')->get();

        if ($accounts->isEmpty()) {
            return redirect()->route('accounts.create')
                ->with('error', 'Add at least one account before posting a journal entry.');
        }

        return view('journal-entries.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
        ]);

        // Build the [account_code => ..., debit => ..., credit => ...] shape
        // AccountingService::postEntry() expects, resolving each selected
        // account_id to its code.
        $lines = [];
        foreach ($validated['lines'] as $line) {
            $account = Account::findOrFail($line['account_id']);
            $lines[] = [
                'account_code' => $account->code,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'desc' => $validated['description'],
            ];
        }

        try {
            $this->accountingService->postEntry(
                $validated['description'],
                auth()->user()->branch_id,
                'manual',
                null,
                $lines
            );

            return redirect()->route('journal-entries.index')->with('success', 'Journal entry posted.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
