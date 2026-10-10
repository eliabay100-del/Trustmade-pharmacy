<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PART C — a single expense (rent, electricity, ...). Posting happens in
 * ExpenseController inside DB::transaction: expense account up, the paying
 * method's account down. Soft delete + reversal journal entry follows the
 * system's existing safe-delete pattern (see write-offs).
 */
class Expense extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'expense_date', 'expense_category_id', 'branch_id', 'amount',
        'payment_method', 'payment_reference',
        'check_number', 'check_date', 'check_bank',
        'note', 'repeat_monthly', 'next_due_date',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'check_date' => 'date',
        'next_due_date' => 'date',
        'amount' => 'decimal:2',
        'repeat_monthly' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Plain-language payment label for lists (never accounting terms).
     */
    public function payment_label(): string
    {
        return Payment::METHODS[$this->payment_method] ?? ucfirst($this->payment_method);
    }
}
