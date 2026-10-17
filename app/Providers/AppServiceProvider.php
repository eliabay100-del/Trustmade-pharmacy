<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        // Development guardrail: a lazily-loaded relation (the classic N+1)
        // throws instead of quietly hammering the database. Disabled in
        // production so a surprise during a request can never take the app
        // down for real users.
        Model::preventLazyLoading(! app()->isProduction());

        View::composer('layouts.app', function ($view) {
            $badge = 0;
            $billsBadge = 0;
            $user = Auth::user();
            if ($user && $user->role === 'owner_admin') {
                try {
                    if (Schema::hasTable('purchase_batches')) {
                        $cacheKey = 'owed_badge:' . ($user->branch_id ?? 'all');
                        $badge = Cache::remember($cacheKey, 60, function () use ($user) {
                            return app(\App\Services\SupplierOwedService::class)
                                ->reminderData($user->branch_id, max(1, (int) Setting::value('supplier_alert_days', '7')))['badge_count'];
                        });
                    }

                    // Monthly bills due — the count on the Alerts link, so a
                    // reminder is noticed without opening the page.
                    if (Schema::hasTable('expenses')) {
                        $billsBadge = Cache::remember('bills_due_badge:' . ($user->branch_id ?? 'all'), 60, function () use ($user) {
                            return \App\Models\Expense::dueBills(
                                $user->branch_id,
                                max(1, (int) Setting::value('supplier_alert_days', '7'))
                            )->count();
                        });
                    }
                } catch (\Throwable $e) {
                    $badge = 0;
                    $billsBadge = 0;
                }
            }
            $view->with('owedBadgeCount', $badge);
            $view->with('billsDueCount', $billsBadge);
        });
    }
}
