<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $branchId = $user->branch_id;

        // Same window as the Alerts page, so the two never disagree.
        $expiryDays = Setting::expiryAlertDays();

        $cacheKey = 'dashboard:' . ($branchId ?? 'all') . ':expiry:' . $expiryDays;

        $data = Cache::remember($cacheKey, 60, function () use ($user, $branchId, $expiryDays) {
            $scope = fn ($query, $column = 'branch_id') => $branchId ? $query->where($column, $branchId) : $query;

            $totalDrugs = $scope(DB::table('purchase_batches'))
                ->whereNotNull('drug_id')
                ->where('quantity_remaining', '>', 0)
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
                ->distinct('drug_id')
                ->count('drug_id');

            $totalQuantity = $scope(DB::table('purchase_batches'))
                ->where('quantity_remaining', '>', 0)
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
                ->sum('quantity_remaining');

            $totalValue = $scope(DB::table('purchase_batches'))
                ->where('quantity_remaining', '>', 0)
                ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
                ->selectRaw('SUM(quantity_remaining * cost_per_unit) as total')
                ->value('total') ?? 0;

            $lowStockCount = $scope(DB::table('drugs as d')->join('purchase_batches as pb', 'd.id', '=', 'pb.drug_id'), 'pb.branch_id')
                ->where('pb.quantity_remaining', '>', 0)
                ->where('pb.expiry_date', '>=', Carbon::today())
                ->whereColumn('pb.quantity_remaining', '<=', 'd.minimum_stock_level')
                ->distinct('d.id')
                ->count('d.id');

            $expiringSoonQuery = DB::table('purchase_batches as pb')->join('drugs as d', 'pb.drug_id', '=', 'd.id')
                ->whereNotNull('pb.drug_id')->whereBetween('pb.expiry_date', [Carbon::today(), Carbon::today()->addDays($expiryDays)])->where('pb.quantity_remaining', '>', 0);
            $expiringSoon = $scope($expiringSoonQuery, 'pb.branch_id')->limit(5)->get()
                ->map(fn ($row) => (object) ['drug_name' => $row->name, 'expiry_date' => $row->expiry_date, 'quantity_remaining' => $row->quantity_remaining]);
            $expiringSoonCount = $expiringSoon->count();

            $recentSales = $scope(DB::table('issued_items'))
                ->where('issue_date', '>=', Carbon::today()->subDays(7))
                ->sum('total_amount');

            $recentPurchases = $scope(DB::table('purchase_batches'))
                ->where('purchase_date', '>=', Carbon::today()->subDays(7))
                ->selectRaw('SUM(quantity_received * cost_per_unit) as total')
                ->value('total') ?? 0;

            $todaySales = $scope(DB::table('issued_items'))
                ->whereDate('issue_date', Carbon::today())
                ->sum('total_amount');

            $totalProducts = $scope(DB::table('purchase_batches'))
                ->whereNotNull('product_id')->where('quantity_remaining', '>', 0)->distinct()->count('product_id');
            $productStockValue = $scope(DB::table('purchase_batches'))
                ->whereNotNull('product_id')->where('quantity_remaining', '>', 0)
                ->selectRaw('SUM(quantity_remaining * cost_per_unit) as total')->value('total') ?? 0;
            $productSales = $scope(DB::table('issued_items as ii')->join('purchase_batches as pb', 'ii.purchase_batch_id', '=', 'pb.id'), 'ii.branch_id')
                ->whereNotNull('ii.product_id')->sum('ii.total_amount');
            $productLowStockCount = $scope(DB::table('products as p')->join('purchase_batches as pb', 'p.id', '=', 'pb.product_id'), 'pb.branch_id')
                ->where('pb.quantity_remaining', '>', 0)->whereColumn('pb.quantity_remaining', '<=', 'p.minimum_stock')
                ->distinct('p.id')->count('p.id');
            $lowStockCount += $productLowStockCount;

            return [
                'totalDrugs' => $totalDrugs,
                'totalQuantity' => $totalQuantity,
                'totalValue' => $totalValue,
                'lowStockCount' => $lowStockCount,
                'recentSales' => $recentSales,
                'recentPurchases' => $recentPurchases,
                'todaySales' => $todaySales,
                'expiringSoonCount' => $expiringSoonCount,
                'totalProducts' => $totalProducts,
                'productStockValue' => $productStockValue,
                'productSales' => $productSales,
                'expiringSoon' => $expiringSoon->all(),
            ];
        });

        $supplierReminders = null;
        if ($user->role === 'owner_admin') {
            $supplierReminders = app(\App\Services\SupplierOwedService::class)
                ->reminderData($branchId, max(1, (int) \App\Models\Setting::value('supplier_alert_days', '7')));
        }

        return view('dashboard.index', array_merge($data, [
            'supplierReminders' => $supplierReminders,
            'expiryDays' => $expiryDays,
            'expiringSoon' => collect($data['expiringSoon']),
        ]));
    }
}	