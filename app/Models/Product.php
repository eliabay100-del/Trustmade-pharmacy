<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'sku', 'category_id', 'brand', 'description',
        'purchase_price', 'selling_price', 'minimum_stock', 'active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function getCurrentStockAttribute(): int
    {
        return (int) $this->purchaseBatches()->sum('quantity_remaining');
    }

    public function getMinimumStockLevelAttribute(): mixed
    {
        return $this->minimum_stock;
    }

    public function baseUnitLabel(): string { return 'unit'; }
    public function unitsPerStrip(): int { return 1; }
    public function unitsPerPack(): int { return 1; }
    public function toBaseQuantity(int $qty, string $unit): int { return $qty; }
    public function pricePerBase(float $price, string $unit): float { return $price; }
}
