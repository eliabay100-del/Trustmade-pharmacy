<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'address', 'phone'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * This is a single-branch install. Branch management (the CRUD screen and
     * every branch picker) was removed, but the branch_id columns were kept so
     * existing records, reports and the row-scoping checks keep working. Every
     * new record is stamped with this one branch instead of asking the user to
     * choose one. Null only on a database with no branch row yet.
     */
    public static function current(): ?self
    {
        return static::query()->orderBy('id')->first();
    }

    /**
     * The branch_id to stamp on a new record: whatever the request supplied,
     * otherwise the signed-in user's own branch, otherwise the single branch.
     */
    public static function resolveId($requested = null, $userBranchId = null): ?int
    {
        if ($requested) {
            return (int) $requested;
        }

        if ($userBranchId) {
            return (int) $userBranchId;
        }

        return static::current()?->id;
    }

    public function purchaseBatches(): HasMany
    {
        return $this->hasMany(PurchaseBatch::class);
    }

    public function stockCards(): HasMany
    {
        return $this->hasMany(StockCard::class);
    }

    public function issuedItems(): HasMany
    {
        return $this->hasMany(IssuedItem::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }
}
