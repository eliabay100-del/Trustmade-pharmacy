<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Drug extends Model
{
    use LogsActivity;

    public const UNIT_TABLET = 'tablet';
    public const UNIT_STRIP = 'strip';
    public const UNIT_PACK = 'pack';

    protected $fillable = [
        'name', 'generic_name', 'strength', 'form',
        'category_id', 'unit_of_measure', 'units_per_strip',
        'strips_per_pack', 'minimum_stock_level'
    ];

    protected $casts = [
        'minimum_stock_level' => 'decimal:2',
        'units_per_strip' => 'integer',
        'strips_per_pack' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DrugCategory::class, 'category_id');
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function getCurrentStockAttribute()
    {
        return DB::table('current_stock')
            ->where('drug_id', $this->id)
            ->sum('current_quantity');
    }

    public function baseUnitLabel(): string
    {
        return $this->unit_of_measure ?: 'tablet';
    }

    public function unitsPerStrip(): int
    {
        return max(1, (int) $this->units_per_strip);
    }

    public function stripsPerPack(): int
    {
        return max(1, (int) $this->strips_per_pack);
    }

    public function unitsPerPack(): int
    {
        return $this->unitsPerStrip() * $this->stripsPerPack();
    }

    // FIFO/FEFO always counts this many base units for one pack/strip/tablet.
    public function baseUnitsPer(string $unit): int
    {
        return match ($unit) {
            self::UNIT_STRIP => $this->unitsPerStrip(),
            self::UNIT_PACK => $this->unitsPerPack(),
            default => 1,
        };
    }

    public function toBaseQuantity(int $qty, string $unit): int
    {
        return $qty * $this->baseUnitsPer($unit);
    }

    public function pricePerBase(float $price, string $unit): float
    {
        $factor = $this->baseUnitsPer($unit);
        return $factor > 0 ? $price / $factor : $price;
    }

    public function packingOptions(): array
    {
        $base = $this->baseUnitLabel();
        return [
            self::UNIT_TABLET => ucfirst($base) . ' (base)',
            self::UNIT_STRIP => 'Strip (' . $this->unitsPerStrip() . ' ' . $base . ')',
            self::UNIT_PACK => 'Pack (' . $this->unitsPerPack() . ' ' . $base . ')',
        ];
    }
}
