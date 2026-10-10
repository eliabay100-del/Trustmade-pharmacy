<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    protected function owner(): User
    {
        $user = User::factory()->create(['role' => 'owner_admin', 'branch_id' => \App\Models\Branch::create(['name' => 'Main', 'address' => 'Addis', 'phone' => '0911'])->id]);
        $this->seed(\Database\Seeders\AccountSeeder::class);
        $this->seed(\Database\Seeders\ExpenseCategorySeeder::class);

        return $user;
    }

    protected function category(string $name): ExpenseCategory
    {
        return ExpenseCategory::where('name', $name)->firstOrFail();
    }

    public function test_expense_posts_balanced_entry_expense_account_dr_method_acct_cr(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Rent');
        $expenseAccount = Account::where('code', $cat->account_code)->firstOrFail();
        $cashAccount = Account::where('code', Payment::ACCOUNT_CODES['cash'])->firstOrFail();

        $resp = $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'note' => 'October rent',
        ]);
        $resp->assertRedirect(route('expenses.index'));

        $expense = Expense::firstOrFail();
        $this->assertEquals(500.0, (float) $expense->amount);

        $entry = JournalEntry::where('reference_type', 'expense')->where('reference_id', $expense->id)->firstOrFail();
        // Debits == credits (balanced).
        $lines = $entry->ledgers;
        $this->assertEquals(
            (float) $lines->sum('debit'),
            (float) $lines->sum('credit')
        );
        $this->assertEqualsWithDelta(500.0, (float) $lines->sum('debit'), 0.001);

        // Expense account increased (debit), cash reduced (credit).
        $expLine = $lines->firstWhere('account_id', $expenseAccount->id);
        $this->assertNotNull($expLine);
        $this->assertEqualsWithDelta(500.0, (float) $expLine->debit, 0.001);
        $cashLine = $lines->first(fn ($l) => $l->account_id === $cashAccount->id);
        $this->assertNotNull($cashLine);
        $this->assertEqualsWithDelta(500.0, (float) $cashLine->credit, 0.001);
    }

    public function test_deleting_expense_reverses_and_soft_deletes(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Electricity');
        $this->actingAs($owner)->post(route('expenses.store'), [
            'expense_date' => now()->toDateString(),
            'expense_category_id' => $cat->id,
            'amount' => 120,
            'payment_method' => 'cash',
        ]);
        $expense = Expense::firstOrFail();

        $this->actingAs($owner)->delete(route('expenses.destroy', $expense->id))->assertRedirect();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        // A reversal entry exists in addition to the original.
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'expense', 'reference_id' => $expense->id]);
        $this->assertDatabaseHas('journal_entries', ['reference_type' => 'expense_reversal', 'reference_id' => $expense->id]);
    }

    public function test_clerk_and_branch_manager_cannot_access_expenses(): void
    {
        $owner = $this->owner();
        $clerk = User::factory()->create(['role' => 'pharmacist_clerk']);
        $manager = User::factory()->create(['role' => 'branch_manager']);

        $this->actingAs($clerk)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($clerk)->post(route('expenses.store'), [])->assertForbidden();
        $this->actingAs($manager)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('expenses.index'))->assertOk();
    }

    public function test_category_rename_and_deactivate(): void
    {
        $owner = $this->owner();
        $cat = $this->category('Water');

        $this->actingAs($owner)->put(route('expense-categories.update', $cat->id), [
            'name' => 'Water & Utilities',
            'is_active' => 0,
        ])->assertRedirect();

        $cat->refresh();
        $this->assertSame('Water & Utilities', $cat->name);
        $this->assertFalse((bool) $cat->is_active);

        // Deactivated categories do not appear on the create form.
        $this->actingAs($owner)->get(route('expenses.create'))
            ->assertOk()
            ->assertDontSee('Water & Utilities');
    }
}
