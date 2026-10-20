<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A medicine, and the packing it is bought and sold in.
 *
 * Stock is stored in exactly one unit - the smallest rung (unit_base) - in
 * purchase_batches.quantity_received / quantity_remaining. Everything else in
 * this class is about translating between that number and the way the shop
 * talks: "2 packs", "3 strips", "47.30 a capsule".
 *
 * The rungs are described per drug rather than assumed, because they genuinely
 * differ: a box of 100 strips of 10 tablets, a box of 20 sachets, a bottle of
 * syrup sold whole. See the migration that added these columns.
 */
class Drug extends Model
{
    use LogsActivity;

    // Kept for older call-sites. New code should use packing levels by key.
    public const UNIT_TABLET = 'tablet';
    public const UNIT_STRIP = 'strip';
    public const UNIT_PACK = 'pack';

    /** The words that describe a container rather than a countable piece. */
    public const CONTAINER_UNITS = ['pack', 'box', 'bottle', 'tin', 'vial', 'tube'];

    protected $fillable = [
        'name', 'generic_name', 'strength', 'form',
        'category_id', 'unit_of_measure', 'units_per_strip',
        'strips_per_pack', 'minimum_stock_level',
        'unit_top', 'unit_middle', 'unit_base',
        'top_holds', 'middle_holds', 'units_per_top',
        'sell_top', 'sell_middle', 'sell_base',
    ];

    protected $casts = [
        'minimum_stock_level' => 'decimal:2',
        'units_per_strip' => 'integer',
        'strips_per_pack' => 'integer',
        'top_holds' => 'integer',
        'middle_holds' => 'integer',
        'units_per_top' => 'integer',
        'sell_top' => 'boolean',
        'sell_middle' => 'boolean',
        'sell_base' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Drug $drug) {
            if ($drug->unit_top) {
                $drug->normalisePacking();
                $drug->mirrorLegacyPackingColumns();
            }

            // Derived either way, so the low-stock queries can multiply in SQL.
            $drug->units_per_top = $drug->unitsPerTop();
        });
    }

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
        return $this->purchaseBatches()->sum('quantity_remaining');
    }

    /* ------------------------------------------------------------------
     |  Packing
     | ------------------------------------------------------------------ */

    /**
     * The rungs of this drug's packing, biggest first.
     *
     * Each rung carries:
     *   key       stable handle: top / middle / base
     *   name      what the shop calls it: pack, strip, capsule, sachet
     *   factor    how many base units one of these is
     *   sellable  may it leave the shelf in this unit
     *
     * Drugs created before the packing columns existed (and code that still
     * sets only units_per_strip / strips_per_pack) fall back to the old fixed
     * geometry, so nothing that predates this survives as a special case.
     *
     * @return array<int, array{key:string, name:string, factor:int, sellable:bool}>
     */
    public function packingLevels(): array
    {
        if ($this->unit_top) {
            $levels = [[
                'key' => 'top',
                'name' => strtolower((string) $this->unit_top),
                'factor' => $this->unitsPerTop(),
                'sellable' => (bool) ($this->sell_top ?? true),
            ]];

            if ($this->unit_middle) {
                $levels[] = [
                    'key' => 'middle',
                    'name' => strtolower((string) $this->unit_middle),
                    'factor' => max(1, (int) $this->middle_holds),
                    'sellable' => (bool) ($this->sell_middle ?? true),
                ];
                $levels[] = $this->baseLevel();

                return $levels;
            }

            // A top that holds more than one thing still has a rung below it.
            // A top that holds nothing (a bottle) is the only rung.
            if ($this->unitsPerTop() > 1
                || strcasecmp((string) ($this->unit_base ?? ''), (string) $this->unit_top) !== 0) {
                $levels[] = $this->baseLevel();
            }

            return $levels;
        }

        return $this->legacyPackingLevels();
    }

    /**
     * @return array{key:string, name:string, factor:int, sellable:bool}
     */
    private function baseLevel(): array
    {
        return [
            'key' => 'base',
            'name' => strtolower((string) ($this->unit_base ?: 'tablet')),
            'factor' => 1,
            'sellable' => (bool) ($this->sell_base ?? true),
        ];
    }

    /**
     * @return array<int, array{key:string, name:string, factor:int, sellable:bool}>
     */
    private function legacyPackingLevels(): array
    {
        $base = strtolower((string) ($this->unit_of_measure ?: 'tablet'));
        $perStrip = $this->unitsPerStrip();
        $perPack = $this->stripsPerPack();

        return [
            ['key' => 'top', 'name' => 'pack', 'factor' => $perStrip * $perPack, 'sellable' => true],
            ['key' => 'middle', 'name' => 'strip', 'factor' => $perStrip, 'sellable' => true],
            ['key' => 'base', 'name' => $base, 'factor' => 1, 'sellable' => true],
        ];
    }

    /** How many base units one top unit holds. */
    public function unitsPerTop(): int
    {
        if (! $this->unit_top) {
            return $this->unitsPerStrip() * $this->stripsPerPack();
        }

        $top = max(1, (int) $this->top_holds);

        return $this->unit_middle
            ? $top * max(1, (int) $this->middle_holds)
            : $top;
    }

    /** The levels a sale may use, biggest first. */
    public function sellableLevels(): array
    {
        return array_values(array_filter(
            $this->packingLevels(),
            fn (array $level) => $level['sellable']
        ));
    }

    /** @return array<int, string> */
    public function sellableUnitKeys(): array
    {
        return array_column($this->sellableLevels(), 'key');
    }

    /**
     * How many base units one of $unit is.
     *
     * Accepts a level key ('top', 'middle', 'base') or the level's own name
     * ('pack', 'strip', 'capsule'), because forms and older call-sites use
     * both. An unknown value counts as one, which matches what this method
     * always did for unrecognised input.
     */
    public function baseUnitsPer(string $unit): int
    {
        $unit = strtolower(trim($unit));

        foreach ($this->packingLevels() as $level) {
            if ($level['key'] === $unit || $level['name'] === $unit) {
                return max(1, $level['factor']);
            }
        }

        return 1;
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

    /** The shelf price of one $unit, given the price of one base unit. */
    public function pricePerUnit(float $basePrice, string $unit): float
    {
        return round($basePrice * $this->baseUnitsPer($unit), 4);
    }

    /* ------------------------------------------------------------------
     |  Reading quantities out loud
     | ------------------------------------------------------------------ */

    public function baseUnitLabel(): string
    {
        $levels = $this->packingLevels();

        return $levels[count($levels) - 1]['name'];
    }

    public function topUnitLabel(): string
    {
        return $this->packingLevels()[0]['name'];
    }

    /**
     * "24 packs", "12 packs 3 strips 4 capsules", "41 bottles".
     *
     * Always starts from the biggest unit and only steps down when something
     * is left over, because that is how the shop reads a shelf. The stored
     * number never changes; this is only how it is said.
     */
    public function describeQuantity($baseQty): string
    {
        $levels = $this->packingLevels();
        $left = max(0, (int) round((float) $baseQty));
        $parts = [];
        $last = count($levels) - 1;

        foreach ($levels as $i => $level) {
            $factor = max(1, $level['factor']);
            $count = intdiv($left, $factor);
            $left -= $count * $factor;

            if ($i === $last) {
                if ($count > 0 || $parts === []) {
                    $parts[] = number_format($count) . ' ' . $this->plural($level['name'], $count);
                }
            } elseif ($count > 0) {
                $parts[] = number_format($count) . ' ' . $this->plural($level['name'], $count);
            }
        }

        return implode(' ', $parts);
    }

    /** One readable line for lists: "Pack of 10 strips × 10 capsules". */
    public function packingSummary(): string
    {
        $levels = $this->packingLevels();

        if (count($levels) === 1) {
            return ucfirst($levels[0]['name']);
        }

        $top = $levels[0];
        $next = $levels[1];

        // How many of the next rung sit in one top. Dividing the two factors
        // also covers the case where the top holds a single strip.
        $perTop = intdiv($top['factor'], max(1, $next['factor']));

        $text = ucfirst($top['name']) . ' of ' . number_format($perTop) . ' '
            . $this->plural($next['name'], $perTop);

        if (count($levels) === 3 && $next['factor'] > 1) {
            $text .= ' × ' . number_format($next['factor']) . ' '
                . $this->plural($levels[2]['name'], $next['factor']);
        }

        return $text;
    }

    /** The minimum, expressed in base units, for comparing against stock. */
    public function minimumInBaseUnits(): float
    {
        return (float) $this->minimum_stock_level * (float) $this->unitsPerTop();
    }

    public function isBelowMinimum($stockInBaseUnits): bool
    {
        return (float) $stockInBaseUnits <= $this->minimumInBaseUnits();
    }

    private function plural(string $word, int $count): string
    {
        if ($count === 1) {
            return $word;
        }

        return match ($word) {
            'sachet' => 'sachets',
            'box' => 'boxes',
            default => $word . 's',
        };
    }

    /* ------------------------------------------------------------------
     |  Form state
     | ------------------------------------------------------------------ */

    /**
     * What the medicine form should show, whether or not the new columns have
     * been filled in yet.
     *
     * "inner" is the form's own wording for what sits inside one pack or box:
     * none (sold whole), strip, sachet, ampoule, bottle, or loose.
     *
     * @return array<string, mixed>
     */
    public function packingFormState(): array
    {
        $state = [
            'unit_top' => 'pack',
            'top_holds' => '',
            'inner' => 'none',
            'middle_holds' => '',
            'unit_base' => 'tablet',
            'sell_middle' => true,
            'sell_base' => true,
        ];

        if (! $this->unit_top) {
            $base = strtolower((string) ($this->unit_of_measure ?: 'tablet'));
            $perStrip = $this->unitsPerStrip();
            $perPack = $this->stripsPerPack();

            if ($perStrip === 1 && $perPack === 1) {
                $container = in_array($base, self::CONTAINER_UNITS, true) ? $base : 'pack';
                $state['unit_top'] = $container;
                $state['unit_base'] = $container;
                $state['inner'] = 'none';
                $state['sell_middle'] = false;

                return $state;
            }

            $state['unit_top'] = 'pack';
            $state['top_holds'] = $perPack;
            $state['unit_base'] = $base;

            if ($perStrip === 1) {
                $state['inner'] = in_array($base, ['sachet', 'ampoule', 'bottle'], true) ? $base : 'loose';
            } else {
                $state['inner'] = 'strip';
                $state['middle_holds'] = $perStrip;
            }

            return $state;
        }

        $state['unit_top'] = (string) $this->unit_top;
        $state['unit_base'] = (string) ($this->unit_base ?: 'tablet');
        $state['top_holds'] = $this->top_holds ?? '';
        $state['middle_holds'] = $this->middle_holds ?? '';
        $state['sell_middle'] = (bool) $this->sell_middle;
        $state['sell_base'] = (bool) $this->sell_base;

        if ($this->unit_middle) {
            $state['inner'] = 'strip';
        } elseif ((int) $this->top_holds <= 1
            && strcasecmp((string) $this->unit_base, (string) $this->unit_top) === 0) {
            $state['inner'] = 'none';
        } elseif (in_array(strtolower((string) $this->unit_base), ['sachet', 'ampoule', 'bottle'], true)) {
            $state['inner'] = strtolower((string) $this->unit_base);
        } else {
            $state['inner'] = 'loose';
        }

        return $state;
    }

    /* ------------------------------------------------------------------
     |  Internals
     | ------------------------------------------------------------------ */

    private function normalisePacking(): void
    {
        $this->unit_top = strtolower(trim((string) $this->unit_top));
        $this->unit_middle = $this->unit_middle
            ? strtolower(trim((string) $this->unit_middle))
            : null;
        $this->unit_base = strtolower(trim((string) ($this->unit_base ?: 'tablet')));

        // A rung that holds nothing cannot have a count.
        if (! $this->unit_middle) {
            $this->middle_holds = null;
        }

        if ($this->top_holds !== null) {
            $this->top_holds = max(1, (int) $this->top_holds);
        }

        $this->sell_top = true;
        $this->sell_middle = $this->unit_middle
            ? ($this->sell_middle === null ? true : (bool) $this->sell_middle)
            : false;
        $this->sell_base = $this->sell_base === null ? true : (bool) $this->sell_base;
    }

    /**
     * Keep the three columns the rest of the app still reads in step with the
     * described packing. They are derived; nothing should write to them.
     */
    private function mirrorLegacyPackingColumns(): void
    {
        $this->unit_of_measure = $this->unit_base;
        $this->units_per_strip = $this->unit_middle ? max(1, (int) $this->middle_holds) : 1;
        $this->strips_per_pack = max(1, (int) $this->top_holds);
    }

    public function unitsPerStrip(): int
    {
        if ($this->unit_top) {
            return $this->unit_middle ? max(1, (int) $this->middle_holds) : 1;
        }

        return max(1, (int) $this->units_per_strip);
    }

    public function stripsPerPack(): int
    {
        if ($this->unit_top) {
            return max(1, (int) $this->top_holds);
        }

        return max(1, (int) $this->strips_per_pack);
    }

    public function unitsPerPack(): int
    {
        return $this->unitsPerTop();
    }

    /** @return array<string, string> */
    public function packingOptions(): array
    {
        $options = [];

        foreach ($this->packingLevels() as $level) {
            $label = ucfirst($level['name']);

            if ($level['factor'] > 1) {
                $label .= ' (' . number_format($level['factor']) . ' ' . $this->baseUnitLabel() . ')';
            }

            $options[$level['key']] = $label;
        }

        return $options;
    }
}
