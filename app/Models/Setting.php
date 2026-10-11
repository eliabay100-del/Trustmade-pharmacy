<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value settings store (Part B reminder window now, Part D
 * branding later). Global by convention; branch-specific values can use a
 * "branch:{id}:{key}" style name if ever needed. Reads are cached so the
 * sidebar badge / layout don't hit the DB repeatedly.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function value(string $key, $default = null)
    {
        $all = static::allCached();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function allCached(): array
    {
        return Cache::remember('settings.all', 300, function () {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                // Table may not exist yet during first migration run.
                return [];
            }
        });
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }

    /**
     * The VAT percentage already contained in the prices the pharmacy charges
     * (15 in Ethiopia). 0 means the owner has not set a rate, so receipts show
     * no breakdown rather than inventing one.
     */
    public static function vatRatePercent(): float
    {
        $rate = round((float) (string) static::value('vat_rate_percent', 0), 2);

        return ($rate > 0 && $rate < 100) ? $rate : 0.0;
    }
}
