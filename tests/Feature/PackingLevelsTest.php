<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Packing is described per drug, not assumed.
 *
 * The old model hard-coded tablet -> strip -> pack and offered all three for
 * every medicine, including a bottle of syrup. These tests pin the two things
 * that matter: the arithmetic between the rungs, and the way a quantity is
 * read back out ("2 packs 3 strips 4 capsules"), biggest unit first.
 */
class PackingLevelsTest extends TestCase
{
    use RefreshDatabase;

    private DrugCategory $category;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create(['name' => 'Main Branch']);

        $this->category = DrugCategory::create(['name' => 'Antibiotics']);

        $this->owner = User::create([
            'name' => 'Owner', 'username' => 'owner', 'email' => 'owner@example.com',
            'password' => 'secret1234', 'role' => 'owner_admin',
            'branch_id' => $branch->id,
        ]);
    }

    private function drug(array $attributes = []): Drug
    {
        return Drug::create(array_merge([
            'name' => 'Amoxicillin', 'strength' => '500mg',
            'category_id' => $this->category->id,
        ], $attributes));
    }

    /** The shape that started this: a pack of strips of capsules. */
    public function test_a_pack_of_strips_counts_down_to_the_capsule(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'capsule',
            'top_holds' => 10, 'middle_holds' => 15,
            'minimum_stock_level' => 2,
        ]);

        // Deliberately not ten per strip: the count comes from the drug.
        $this->assertSame(150, $drug->unitsPerTop());
        $this->assertSame(150, $drug->baseUnitsPer('pack'));
        $this->assertSame(15, $drug->baseUnitsPer('strip'));
        $this->assertSame(1, $drug->baseUnitsPer('capsule'));

        // Selling works downward: a strip is a fifteenth of a pack.
        $this->assertSame(1.0, $drug->pricePerBase(15.0, 'strip'));
        $this->assertSame(150.0, $drug->pricePerUnit(1.0, 'pack'));
    }

    public function test_stock_is_read_from_the_biggest_unit_down(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'capsule',
            'top_holds' => 10, 'middle_holds' => 15,
        ]);

        $this->assertSame('0 capsules', $drug->describeQuantity(0));
        $this->assertSame('4 capsules', $drug->describeQuantity(4));
        $this->assertSame('2 strips 4 capsules', $drug->describeQuantity(34));
        $this->assertSame('2 packs 3 strips 4 capsules', $drug->describeQuantity(349));
        $this->assertSame('24 packs', $drug->describeQuantity(3600));
    }

    /** A box of sachets has nothing in between the box and the sachet. */
    public function test_a_box_of_sachets_has_no_strip(): void
    {
        $drug = $this->drug([
            'name' => 'Oral Rehydration Salts',
            'unit_top' => 'box', 'unit_middle' => null, 'unit_base' => 'sachet',
            'top_holds' => 20, 'minimum_stock_level' => 2,
        ]);

        $levels = $drug->packingLevels();

        $this->assertCount(2, $levels);
        $this->assertSame(['top', 'base'], array_column($levels, 'key'));
        $this->assertSame(20, $drug->baseUnitsPer('box'));
        $this->assertSame(1, $drug->baseUnitsPer('sachet'));
        $this->assertSame('2 boxes 5 sachets', $drug->describeQuantity(45));
        $this->assertSame('Box of 20 sachets', $drug->packingSummary());
    }

    /** A bottle is never broken open: one rung, counted in bottles. */
    public function test_a_bottle_is_sold_whole(): void
    {
        $drug = $this->drug([
            'name' => 'Amoxicillin Suspension',
            'unit_top' => 'bottle', 'unit_middle' => null, 'unit_base' => 'bottle',
        ]);

        $this->assertCount(1, $drug->packingLevels());
        $this->assertSame(1, $drug->unitsPerTop());
        $this->assertSame('41 bottles', $drug->describeQuantity(41));
        $this->assertSame('1 bottle', $drug->describeQuantity(1));
        $this->assertSame('Bottle', $drug->packingSummary());
        $this->assertFalse($drug->sell_middle);
    }

    /** Minimum stock is in the upper unit, so 2 means two packs, not two tablets. */
    public function test_minimum_stock_is_measured_in_the_upper_unit(): void
    {
        $drug = $this->drug([
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'tablet',
            'top_holds' => 10, 'middle_holds' => 10,
            'minimum_stock_level' => 2,
        ]);

        $this->assertSame(200.0, $drug->minimumInBaseUnits());
        $this->assertTrue($drug->isBelowMinimum(200));
        $this->assertFalse($drug->isBelowMinimum(201));
    }

    public function test_unticked_units_are_not_offered(): void
    {
        $drug = $this->drug([
            'name' => 'Azithromycin',
            'unit_top' => 'pack', 'unit_middle' => 'strip', 'unit_base' => 'tablet',
            'top_holds' => 10, 'middle_holds' => 3,
            'sell_middle' => false, 'sell_base' => true,
        ]);

        $this->assertSame(['top', 'base'], $drug->sellableUnitKeys());
    }

    /** The medicine form writes the sentence into the rungs. */
    public function test_the_form_stores_a_pack_of_strips_of_tablets(): void
    {
        $response = $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Doxycycline',
            'strength' => '100mg',
            'category_id' => $this->category->id,
            'unit_top' => 'box',
            'inner' => 'strip',
            'top_holds' => 4,
            'middle_holds' => 12,
            'unit_base' => 'tablet',
            'sell_middle' => '1',
            'sell_base' => '1',
            'minimum_stock_level' => 2,
        ]);

        $response->assertRedirect(route('drugs.index'));

        $drug = Drug::where('name', 'Doxycycline')->firstOrFail();

        $this->assertSame('box', $drug->unit_top);
        $this->assertSame('strip', $drug->unit_middle);
        $this->assertSame('tablet', $drug->unit_base);
        $this->assertSame(4, $drug->top_holds);
        $this->assertSame(12, $drug->middle_holds);
        $this->assertSame(48, (int) $drug->units_per_top);
        $this->assertSame('2.00', $drug->minimum_stock_level);

        // The old columns follow along, so unconverted screens keep working.
        $this->assertSame('tablet', $drug->unit_of_measure);
        $this->assertSame(12, (int) $drug->units_per_strip);
        $this->assertSame(4, (int) $drug->strips_per_pack);
    }

    public function test_the_form_stores_a_bottle_as_one_rung(): void
    {
        $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Cough Syrup',
            'category_id' => $this->category->id,
            'unit_top' => 'bottle',
            'inner' => 'none',
            'minimum_stock_level' => 5,
        ])->assertRedirect(route('drugs.index'));

        $drug = Drug::where('name', 'Cough Syrup')->firstOrFail();

        $this->assertSame('bottle', $drug->unit_top);
        $this->assertNull($drug->unit_middle);
        $this->assertSame('bottle', $drug->unit_base);
        $this->assertNull($drug->top_holds);
        $this->assertSame(1, (int) $drug->units_per_top);
        $this->assertSame('41 bottles', $drug->describeQuantity(41));
    }

    public function test_the_form_refuses_a_pack_that_holds_nothing_specified(): void
    {
        $this->actingAs($this->owner)->post(route('drugs.store'), [
            'name' => 'Mystery Pack',
            'category_id' => $this->category->id,
            'unit_top' => 'pack',
            'inner' => 'strip',
            'middle_holds' => 10,
        ])->assertSessionHasErrors('top_holds');

        $this->assertDatabaseMissing('drugs', ['name' => 'Mystery Pack']);
    }

    /**
     * Drugs created before the packing columns existed still behave exactly as
     * they did: 10 tablets to a strip, 10 strips to a pack.
     */
    public function test_a_drug_with_only_the_old_columns_still_works(): void
    {
        $drug = $this->drug([
            'unit_of_measure' => 'tablet',
            'units_per_strip' => 10,
            'strips_per_pack' => 10,
        ]);

        $this->assertSame(100, $drug->unitsPerTop());
        $this->assertSame('1 pack 5 tablets', $drug->describeQuantity(105));
        $this->assertSame('pack', $drug->packingFormState()['unit_top']);
        $this->assertSame('strip', $drug->packingFormState()['inner']);
    }
}
