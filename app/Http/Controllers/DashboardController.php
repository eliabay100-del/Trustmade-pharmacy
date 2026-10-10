<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $branchId = $user->branch_id;
        $scope = fn ($query, $column = 'branch_id') => $branchId ? $query->where($column, $branchId) : $query;

        // 1. Total Drugs (Unique items in stock)
        $totalDrugs = $scope(DB::table('purchase_batches'))
            ->whereNotNull('drug_id')
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
            ->distinct('drug_id')
            ->count('drug_id');

        // 2. Total Quantity (All units combined)
        $totalQuantity = $scope(DB::table('purchase_batches'))
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
            ->sum('quantity_remaining');

        // 3. Total Value (Cost value of current stock) - FIXED
        $totalValue = $scope(DB::table('purchase_batches'))
            ->where('quantity_remaining', '>', 0)
            ->where(fn ($query) => $query->whereNull('expiry_date')->orWhere('expiry_date', '>=', Carbon::today()))
            ->selectRaw('SUM(quantity_remaining * cost_per_unit) as total')
            ->value('total') ?? 0;

        // 4. Low Stock Count
        $lowStockCount = $scope(DB::table('drugs as d')->join('purchase_batches as pb', 'd.id', '=', 'pb.drug_id'), 'pb.branch_id')
            ->where('pb.quantity_remaining', '>', 0)
            ->where('pb.expiry_date', '>=', Carbon::today())
            ->whereColumn('pb.quantity_remaining', '<=', 'd.minimum_stock_level')
            ->distinct('d.id')
            ->count('d.id'); // <--- Ensure this line ends with ->count('d.id');

        // 5. Expiring Soon (Next 30 days)
        $expiringSoonQuery = DB::table('purchase_batches as pb')->join('drugs as d', 'pb.drug_id', '=', 'd.id')
            ->whereNotNull('pb.drug_id')->whereBetween('pb.expiry_date', [Carbon::today(), Carbon::today()->addDays(30)])->where('pb.quantity_remaining', '>', 0);
        $expiringSoon = $scope($expiringSoonQuery, 'pb.branch_id')->limit(5)->get()
            ->map(fn ($row) => (object) ['drug_name' => $row->name, 'expiry_date' => $row->expiry_date, 'quantity_remaining' => $row->quantity_remaining]);
        $expiringSoonCount = $expiringSoon->count();  
        
		// 6. Recent Sales (Last 7 days)
        $recentSales = $scope(DB::table('issued_items'))
            ->where('issue_date', '>=', Carbon::today()->subDays(7))
            ->sum('total_amount');

        // 7. Recent Purchases (Last 7 days) - FIXED
        $recentPurchases = $scope(DB::table('purchase_batches'))
            ->where('purchase_date', '>=', Carbon::today()->subDays(7))
            ->selectRaw('SUM(quantity_received * cost_per_unit) as total')
            ->value('total') ?? 0;
			
		//  8. Today Sales
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

        // PART B — supplier payment reminders (owner/admin only). Live from
        // the purchases; the 7-day window is a setting.
        $supplierReminders = null;
        if ($user->role === 'owner_admin') {
            $supplierReminders = app(\App\Services\SupplierOwedService::class)
                ->reminderData($branchId, max(1, (int) \App\Models\Setting::value('supplier_alert_days', '7')));
        }

        return view('dashboard.index', compact(
            'totalDrugs',
            'totalQuantity',
            'totalValue',
            'lowStockCount',
            'recentSales',
            'recentPurchases',
            'todaySales',
            'expiringSoonCount', 'totalProducts', 'productStockValue', 'productSales', 'supplierReminders'
        ), [
            'expiringSoon' => $expiringSoon // Add the array separately here
        ]);
	}		
}	