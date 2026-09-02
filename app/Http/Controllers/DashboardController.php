<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Branch;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $branchId = $user->branch_id;
        
        // If user is admin/owner without a branch, get the first branch or null
        if (!$branchId) {
            $firstBranch = Branch::first();
            $branchId = $firstBranch ? $firstBranch->id : null;
        }

        // 1. Total Drugs (Unique items in stock)
        $totalDrugs = DB::table('purchase_batches')
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>=', Carbon::today())
            ->distinct('drug_id')
            ->count('drug_id');

        // 2. Total Quantity (All units combined)
        $totalQuantity = DB::table('purchase_batches')
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>=', Carbon::today())
            ->sum('quantity_remaining');

        // 3. Total Value (Cost value of current stock) - FIXED
        $totalValue = DB::table('purchase_batches')
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>=', Carbon::today())
            ->selectRaw('SUM(quantity_remaining * cost_per_unit) as total')
            ->value('total') ?? 0;

        // 4. Low Stock Count
        $lowStockCount = DB::table('drugs as d')
            ->join('purchase_batches as pb', 'd.id', '=', 'pb.drug_id')
            ->where('pb.branch_id', $branchId)
            ->where('pb.quantity_remaining', '>', 0)
            ->where('pb.expiry_date', '>=', Carbon::today())
            ->whereColumn('pb.quantity_remaining', '<=', 'd.minimum_stock_level')
            ->distinct('d.id')
            ->count('d.id'); // <--- Ensure this line ends with ->count('d.id');

        // 5. Expiring Soon (Next 30 days)
        $expiringSoon = collect(DB::select("
            SELECT d.name as drug_name, pb.expiry_date, pb.quantity_remaining
            FROM purchase_batches pb
            JOIN drugs d ON pb.drug_id = d.id
            WHERE pb.branch_id = ?
            AND pb.expiry_date BETWEEN ? AND ?
            AND pb.quantity_remaining > 0
            LIMIT 5
        ", [$branchId, Carbon::today(), Carbon::today()->addDays(30)]));
        
		// Calculate the count explicitly as an integer
        $expiringSoonCount = $expiringSoon->count();  
        
		// 6. Recent Sales (Last 7 days)
        $recentSales = DB::table('issued_items')
            ->where('branch_id', $branchId)
            ->where('issue_date', '>=', Carbon::today()->subDays(7))
            ->sum('total_amount');

        // 7. Recent Purchases (Last 7 days) - FIXED
        $recentPurchases = DB::table('purchase_batches')
            ->where('branch_id', $branchId)
            ->where('purchase_date', '>=', Carbon::today()->subDays(7))
            ->selectRaw('SUM(quantity_received * cost_per_unit) as total')
            ->value('total') ?? 0;
			
		//  8. Today Sales
		$todaySales = DB::table('issued_items')
            ->where('branch_id', $branchId)
            ->whereDate('issue_date', Carbon::today())
            ->sum('total_amount');

        return view('dashboard.index', compact(
            'totalDrugs',
            'totalQuantity',
            'totalValue',
            'lowStockCount',
            'recentSales',
            'recentPurchases',
            'todaySales',
            'expiringSoonCount' // Just the name here
        ), [
            'expiringSoon' => $expiringSoon // Add the array separately here
        ]);
	}		
}	