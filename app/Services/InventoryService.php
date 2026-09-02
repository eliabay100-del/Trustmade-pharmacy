<?php
namespace App\Services;
use App\Models\PurchaseBatch;
use App\Models\StockCard;
use App\Models\IssuedItem;
use App\Models\StockAdjustment;
use App\Models\CustomerReturn;
use App\Models\SupplierReturn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService {
    public function selectBatchesForDispensing($drugId, $branchId, $qtyNeeded) {
        // CONCURRENCY FIX: lockForUpdate() locks these rows for the
        // duration of the enclosing DB transaction (already provided by
        // every caller — DispenseController::store, TransferService).
        // If two requests try to sell from the same batch at once, the
        // second one now waits until the first commits, instead of both
        // reading the same "5 remaining" and overselling.
        $batches = PurchaseBatch::where('drug_id', $drugId)
            ->where('branch_id', $branchId)
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>=', Carbon::today())
            ->orderBy('expiry_date', 'asc')
            ->orderBy('purchase_date', 'asc')
            ->lockForUpdate()
            ->get();

        if ($batches->sum('quantity_remaining') < $qtyNeeded) {
            throw new \Exception("Insufficient stock available.");
        }

        $allocations = [];
        $remaining = $qtyNeeded;
        foreach ($batches as $batch) {
            if ($remaining <= 0) break;
            $take = min($batch->quantity_remaining, $remaining);
            $allocations[] = ['batch' => $batch, 'qty' => $take];
            $remaining -= $take;
        }
        return $allocations;
    }

    public function calculateCOGS(array $allocations): float
    {
        $total = 0;
        foreach ($allocations as $item) {
            $total += $item['qty'] * $item['batch']->cost_per_unit;
        }
        return round($total, 2);
    }

    public function recordIssuance($allocations, $customerId, $branchId, $sellingPricePerUnit, $orderId = null) {
        return DB::transaction(function () use ($allocations, $customerId, $branchId, $sellingPricePerUnit, $orderId) {
            $totalAmount = 0;
            foreach ($allocations as $item) {
                $batch = $item['batch'];
                $qty = $item['qty'];
                $batch->decrement('quantity_remaining', $qty);

                $lineTotal = round($qty * $sellingPricePerUnit, 2);

                $issued = IssuedItem::create([
                    'order_id' => $orderId,
                    'purchase_batch_id' => $batch->id,
                    'customer_id' => $customerId,
                    'branch_id' => $branchId,
                    'quantity_issued' => $qty,
                    'selling_price_per_unit' => $sellingPricePerUnit,
                    'total_amount' => $lineTotal,
                    'issue_date' => now(),
                ]);
                $totalAmount += $lineTotal;

                StockCard::create([
                    'purchase_batch_id' => $batch->id,
                    'branch_id' => $branchId,
                    'transaction_type' => 'out',
                    'quantity' => $qty,
                    'unit_cost' => $batch->cost_per_unit,
                    'total_amount' => $qty * $batch->cost_per_unit,
                    'reference_type' => 'sale',
                    'reference_id' => $issued->id,
                    'transaction_date' => now(),
                ]);
            }
            return $totalAmount;
        });
    }

    // Phase 2: a formal write-off, not a silent edit of quantity_remaining.
    // Expired batches are allowed here (dispense FEFO skips them on purpose).
    public function writeOff(int $batchId, int $qty, string $reason, ?string $notes, int $userId)
    {
        return DB::transaction(function () use ($batchId, $qty, $reason, $notes, $userId) {
            $batch = PurchaseBatch::lockForUpdate()->findOrFail($batchId);

            if ($qty < 1) {
                throw new \Exception('Write-off quantity must be at least 1.');
            }
            if ($batch->quantity_remaining < $qty) {
                throw new \Exception('Cannot write off more than the remaining batch quantity (' . $batch->quantity_remaining . ').');
            }

            $batch->decrement('quantity_remaining', $qty);
            $batch->refresh();

            $unitCost = $batch->cost_per_unit;
            $total = round($qty * (float) $unitCost, 2);

            $adjustment = StockAdjustment::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'user_id' => $userId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'transaction_type' => 'out',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'reference_type' => 'adjustment',
                'reference_id' => $adjustment->id,
                'transaction_date' => now(),
            ]);

            return $adjustment;
        });
    }

    // Puts sold units back on the original batch. Does not edit the
    // original IssuedItem — the return row is the offsetting record.
    public function restockCustomerReturn(IssuedItem $item, int $qty, string $refundMethod, ?string $notes, int $userId): CustomerReturn
    {
        return DB::transaction(function () use ($item, $qty, $refundMethod, $notes, $userId) {
            $item = IssuedItem::lockForUpdate()->findOrFail($item->id);

            if ($qty < 1) {
                throw new \Exception('Return quantity must be at least 1.');
            }

            $already = (int) CustomerReturn::where('issued_item_id', $item->id)->sum('quantity');
            $returnable = (int) $item->quantity_issued - $already;
            if ($qty > $returnable) {
                throw new \Exception('Cannot return more than the remaining sold quantity (' . $returnable . ').');
            }

            $batch = PurchaseBatch::lockForUpdate()->findOrFail($item->purchase_batch_id);
            $batch->increment('quantity_remaining', $qty);
            $batch->refresh();

            $unitCost = (float) $batch->cost_per_unit;
            $costAmount = round($qty * $unitCost, 2);
            $refundAmount = round($qty * (float) $item->selling_price_per_unit, 2);

            $return = CustomerReturn::create([
                'order_id' => $item->order_id,
                'issued_item_id' => $item->id,
                'purchase_batch_id' => $batch->id,
                'branch_id' => $item->branch_id,
                'user_id' => $userId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'cost_amount' => $costAmount,
                'refund_amount' => $refundAmount,
                'refund_method' => $refundMethod,
                'notes' => $notes,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $item->branch_id,
                'transaction_type' => 'in',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $costAmount,
                'reference_type' => 'customer_return',
                'reference_id' => $return->id,
                'transaction_date' => now(),
            ]);

            return $return;
        });
    }

    public function returnToSupplier(int $batchId, int $qty, ?string $notes, int $userId): SupplierReturn
    {
        return DB::transaction(function () use ($batchId, $qty, $notes, $userId) {
            $batch = PurchaseBatch::lockForUpdate()->findOrFail($batchId);

            if ($qty < 1) {
                throw new \Exception('Return quantity must be at least 1.');
            }
            if ($batch->quantity_remaining < $qty) {
                throw new \Exception('Cannot return more than remaining stock on this batch (' . $batch->quantity_remaining . ').');
            }

            $batch->decrement('quantity_remaining', $qty);
            $batch->refresh();

            $unitCost = (float) $batch->cost_per_unit;
            $total = round($qty * $unitCost, 2);

            $return = SupplierReturn::create([
                'purchase_batch_id' => $batch->id,
                'supplier_id' => $batch->supplier_id,
                'branch_id' => $batch->branch_id,
                'user_id' => $userId,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'notes' => $notes,
            ]);

            StockCard::create([
                'purchase_batch_id' => $batch->id,
                'branch_id' => $batch->branch_id,
                'transaction_type' => 'out',
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_amount' => $total,
                'reference_type' => 'supplier_return',
                'reference_id' => $return->id,
                'transaction_date' => now(),
            ]);

            return $return;
        });
    }
}
