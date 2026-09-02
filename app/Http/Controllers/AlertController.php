<?php

namespace App\Http\Controllers;

use App\Models\PurchaseBatch;
use App\Models\Customer;
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

        // Customers aren't branch-owned data (no branch_id column), so
        // recurring-customer reminders are intentionally shown to all
        // roles that can reach this page — not scoped further.
        $dueForRefill = Customer::where('is_recurring', true)
            ->whereNotNull('reminder_interval_days')
            ->get()
            ->filter(fn ($customer) => $customer->isDueForReminder())
            ->values();

        return view('alerts.index', compact('expiringSoon', 'expired', 'dueForRefill'));
    }
}
