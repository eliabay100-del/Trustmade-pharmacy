<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use LogsActivity;

    const METHODS = [
        'cash' => 'Cash',
        'telebirr' => 'Telebirr',
        'cbe_birr' => 'CBE Birr',
        'bank_transfer' => 'Bank Transfer',
        'card' => 'Card',
    ];

    // Each method posts to its own asset account so till-closing (Phase 1)
    // can later compare counted cash against cash-paid orders only —
    // Telebirr/CBE/bank/card sit in separate books, not in the till.
    const ACCOUNT_CODES = [
        'cash' => '1001',
        'telebirr' => '1010',
        'cbe_birr' => '1011',
        'bank_transfer' => '1012',
        'card' => '1013',
    ];

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }

    public static function accountCodeFor(string $method): string
    {
        if (!isset(self::ACCOUNT_CODES[$method])) {
            throw new \InvalidArgumentException("Unknown payment method: {$method}");
        }

        return self::ACCOUNT_CODES[$method];
    }
}
