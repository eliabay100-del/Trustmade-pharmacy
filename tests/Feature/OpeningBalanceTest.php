<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    /** Signed ledger balance (debit − credit) for a code, at a branch. */
    protected function net(string $code, int $branchId): float
    {
        $account = Account::where('code', $code)->firstOrFail();

        return round(
            (float) Ledger::where('account_id', $account->id)->where('branch_id', $branchId)->sum('debit')
            - (float) Ledger::where('account_id', $account->id)->where('branch_id', $branchId)->sum('credit'),
            2
        );
    }

    public function test_owner_posts_opening_balances_with_derived_capital(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);

        $this->actingAs($owner)->post(route('opening-balances.store'), [
            'branch_id' => $branch->id,
            'as_of' => '2026-01-01',
            'balances' => ['1001' => 1000, '1200' => 500],
        ])->assertRedirect(route('opening-balances.edit', ['branch_id' => $branch->id]));

        $entry = JournalEntry::where('reference_type', 'opening_balance')->firstOrFail();
        $this->assertSame($branch->id, $entry->branch_id);
        $this->assertSame('2026-01-01', $entry->date->toDateString());

        $this->assertEquals(1000.00, $this->net('1001', $branch->id));
        $this->assertEquals(500.00, $this->net('1200', $branch->id));
        // Capital (credit-normal) = assets − liabilities.
        $this->assertEquals(-1500.00, $this->net('3000', $branch->id));
    }

    public function test_payables_reduce_owner_capital(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);

        $this->actingAs($owner)->post(route('opening-balances.store'), [
            'branch_id' => $branch->id,
            'balances' => ['1001' => 200, '2000' => 300],
        ])->assertRedirect();

        $this->assertEquals(200.00, $this->net('1001', $branch->id));
        $this->assertEquals(-300.00, $this->net('2000', $branch->id));
        // Assets 200 − liabilities 300 = −100 of capital (a debit balance).
        $this->assertEquals(100.00, $this->net('3000', $branch->id));
    }

    public function test_re_saving_the_same_figures_posts_nothing(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);

        $payload = ['branch_id' => $branch->id, 'balances' => ['1001' => 1000]];

        $this->actingAs($owner)->post(route('opening-balances.store'), $payload);
        $this->actingAs($owner)->post(route('opening-balances.store'), $payload);

        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(1000.00, $this->net('1001', $branch->id));
    }

    public function test_opening_balances_page_is_owner_only(): void
    {
        $branch = Branch::create(['name' => 'Main Branch']);
        $owner = User::factory()->create(['role' => 'owner_admin', 'branch_id' => null]);
        $manager = User::factory()->create(['role' => 'branch_manager', 'branch_id' => $branch->id]);

        $this->actingAs($owner)->get(route('opening-balances.edit'))->assertOk();

        $this->actingAs($manager)->get(route('opening-balances.edit'))->assertForbidden();
        $this->actingAs($manager)
            ->post(route('opening-balances.store'), ['balances' => ['1001' => 1]])
            ->assertForbidden();
    }
}
