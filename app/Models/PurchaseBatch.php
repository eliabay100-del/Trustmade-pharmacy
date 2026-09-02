<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseBatch extends Model
{
    use LogsActivity;

    protected $fillable = [
        'drug_id', 'supplier_id', 'branch_id', 'batch_number',
        'quantity_received', 'quantity_remaining', 'cost_per_unit',
        'selling_price_per_unit', 'expiry_date', 'purchase_date',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_remaining' => 'integer',
        'cost_per_unit' => 'decimal:2',
        'selling_price_per_unit' => 'decimal:2',
    ];

    public function drug(): BelongsTo
    {
        return $this->belongsTo(Drug::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockCards(): HasMany
    {
        return $this->hasMany(StockCard::class);
    }

    public function issuedItems(): HasMany
    {
        return $this->hasMany(IssuedItem::class);
    }

    public function getAvailableQuantityAttribute()
    {
        $in = $this->stockCards()->where('transaction_type', 'in')->sum('quantity');
        $out = $this->stockCards()->whereIn('transaction_type', ['out', 'transfer_out'])->sum('quantity');
        return $in - $out;
    }

    public function isExpired()
    {
        return Carbon::today()->gte($this->expiry_date);
    }

    public function isNearExpiry($days = 30)
    {
        return $this->expiry_date->diffInDays(Carbon::today(), false) <= $days && !$this->isExpired();
    }
}
