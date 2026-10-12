<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use App\Models\Customer;
use App\Models\Drug;
use App\Models\Product;
use Carbon\Carbon;

class AlertController extends Controller
{
    public function index()
    {
        // BUG FIX: ->where('branch_id', $branchId) with a null $branchId
        // (Owner/Admin's intended "all branches" value) silently becomes
        // WHERE branch_id IS NULL in Laravel — which matches nothing,
        // since every real record has a real branch_id. Owner/Admin was
        // seeing empty alerts everywhere instead of all branches' alerts.
        $user = auth()->user();
        $branchId = $user->branch_id;

        $expiringQuery = PurchaseBatch::whereBetween('expiry_date', [Carbon::today(), Carbon::today()->addDays(30)])
            ->where('quantity_remaining', '>', 0);
        $expiredQuery = PurchaseBatch::where('expiry_date', '<', Carbon::today())
            ->where('quantity_remaining', '>', 0);

        if ($user->branch_id) {
            $expiringQuery->where('branch_id', $user->branch_id);
            $expiredQuery->where('branch_id', $user->branch_id);
        }

        $expiringSoon = $expiringQuery->get();
        $expired = $expiredQuery->get();

        $drugStockQuery = Drug::select('drugs.*')
            ->selectSub(
                \App\Models\PurchaseBatch::selectRaw('COALESCE(SUM(quantity_remaining), 0)')
                    ->whereColumn('purchase_batches.drug_id', 'drugs.id')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId)),
                'total_stock'
            )
            ->groupBy('drugs.id')
            ->havingRaw('total_stock <= drugs.minimum_stock_level')
            ->orderBy('name');
        $productStockQuery = Product::select('products.*')
            ->selectSub(
                \App\Models\PurchaseBatch::selectRaw('COALESCE(SUM(quantity_remaining), 0)')
                    ->whereColumn('purchase_batches.product_id', 'products.id')
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId)),
                'total_stock'
            )
            ->groupBy('products.id')
            ->havingRaw('total_stock <= products.minimum_stock')
            ->orderBy('name');
        $lowStockDrugs = $drugStockQuery->get();
        $lowStockProducts = $productStockQuery->get();

        // Customers aren't branch-owned data (no branch_id column), so
        // recurring-customer reminders are intentionally shown to all
        // roles that can reach this page — not scoped further.
        $dueForRefill = Customer::where('is_recurring', true)
            ->whereNotNull('reminder_interval_days')
            ->get()
            ->filter(fn ($customer) => $customer->isDueForReminder())
            ->values();

        // PART B — "Payments due within N days" / "Overdue" owed to suppliers.
        // Owner/Admin only (clerks don't see supplier debts). Calculated live.
        $supplierReminders = null;
        if ($user->role === 'owner_admin') {
            $soonDays = max(1, (int) \App\Models\Setting::value('supplier_alert_days', '7'));
            $supplierReminders = app(\App\Services\SupplierOwedService::class)->reminderData($branchId, $soonDays);
        }

        // PART C — monthly repeating bills that are due now (optional flag on
        // an expense: next_due_date reached). Same window as supplier reminders
        // so the owner sees them together. Calculated live, no cron needed —
        // reminderDate() rolls a bill forward to its cycle for this month, so
        // a reminder shows once per month rather than permanently.
        $recurringExpensesDue = collect();
        if ($user->role === 'owner_admin') {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('expenses')) {
                    $windowEnd = Carbon::today()->addDays($soonDays ?? 7);
                    $recurringExpensesDue = \App\Models\Expense::with('category')
                        ->where('repeat_monthly', true)
                        ->whereNotNull('next_due_date')
                        ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                        ->get()
                        ->filter(fn ($bill) => $bill->reminderDate()?->lte($windowEnd))
                        ->sortBy(fn ($bill) => $bill->reminderDate()->timestamp)
                        ->values();
                }
            } catch (\Throwable $e) {
                $recurringExpensesDue = collect(); // never break alerts over this
            }
        }

        return view('alerts.index', compact('expiringSoon', 'expired', 'dueForRefill', 'lowStockDrugs', 'lowStockProducts', 'supplierReminders', 'recurringExpensesDue'));
    }
}
