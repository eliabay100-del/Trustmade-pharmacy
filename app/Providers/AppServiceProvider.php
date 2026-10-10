<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PART B — sidebar badge count for "Owed to suppliers" (owner/admin
        // only). Calculated live from the purchases so no cron is needed.
        View::composer('layouts.app', function ($view) {
            $badge = 0;
            $user = Auth::user();
            if ($user && $user->role === 'owner_admin') {
                try {
                    if (Schema::hasTable('purchase_batches')) {
                        $badge = app(\App\Services\SupplierOwedService::class)
                            ->reminderData($user->branch_id, max(1, (int) Setting::value('supplier_alert_days', '7')))['badge_count'];
                    }
                } catch (\Throwable $e) {
                    $badge = 0; // never break the layout over a badge
                }
            }
            $view->with('owedBadgeCount', $badge);
        });
    }
}
