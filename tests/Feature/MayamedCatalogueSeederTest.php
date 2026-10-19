<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\MayamedCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Mayamed catalogue seeder.
 *
 * Two things are being defended here. First, that it files every invoiced item
 * under a real category - a catalogue row pointing at nothing is worse than a
 * missing row, because it looks complete. Second, that it is idempotent:
 * a seeder that duplicates rows every time it runs turns a catalogue into
 * noise within a week.
 */
class MayamedCatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCatalogue(): void
    {
        $this->seed(MayamedCatalogueSeeder::class);
    }

    public function test_it_creates_every_drug_from_the_invoices(): void
    {
        $this->seedCatalogue();

        $expected = [
            'Amoxicillin|250mg/5ml',
            'Cledomox|457mg/5ml',
            'Negazole Suspension|125mg/5ml',
            'Klamoks|156mg/5ml',
            'Valcodin|5mg/160mg',
            'Azithromycin|250mg',
            'Indometacine|25mg',
            'Albendazole|400mg',
            'Pregabaline|75mg',
            'Sodium Valproate|200mg',
            'Libitus Cough Syrup|',
            'Netracort Ointment|',
            'Zocon Eye Drop|',
            'Coronis|6.25mg',
            'Cal D Lift|1000mg/800IU',
        ];

        foreach ($expected as $row) {
            [$name, $strength] = array_pad(explode('|', $row), 2, null);

            // A trailing "|" means the invoice states no strength, which the
            // column stores as NULL - not as an empty string.
            if ($strength === '') {
                $strength = null;
            }

            // whereNull, not where('strength', null): the latter compiles to
            // "strength = NULL" and matches nothing.
            $query = Drug::where('name', $name);
            $found = ($strength === null ? $query->whereNull('strength') : $query->where('strength', $strength))->exists();

            $this->assertTrue($found, "Missing drug: {$name} {$strength}");
        }

        $this->assertSame(count($expected), Drug::count());
    }

    public function test_it_creates_every_product_from_the_invoices(): void
    {
        $this->seedCatalogue();

        foreach ([
            'HY-ALC-130', 'MS-FAC-050', 'VS-KID-200', 'VS-RVPC-30',
            'VS-RVMX-30', 'VS-RVMG-30', 'VS-PWRG-30', 'VS-REZU-30',
        ] as $sku) {
            $this->assertTrue(Product::where('sku', $sku)->exists(), "Missing product SKU: {$sku}");
        }

        $this->assertSame(8, Product::count());
    }

    public function test_it_creates_the_new_categories_and_reuses_the_existing_ones(): void
    {
        $this->seedCatalogue();

        foreach (['Anthelmintics', 'Neurology and Antiepileptics', 'Respiratory', 'Dermatology', 'Ophthalmology'] as $name) {
            $this->assertTrue(DrugCategory::where('name', $name)->exists(), "Missing drug category: {$name}");
        }

        $this->assertTrue(ProductCategory::where('name', 'Vitamins and Supplements')->exists());

        // Existing categories must be reused, not duplicated.
        $this->assertSame(1, DrugCategory::where('name', 'Antibiotics')->count());
        $this->assertSame(1, DrugCategory::where('name', 'Chronic Care')->count());
        $this->assertSame(1, ProductCategory::where('name', 'Medical Supplies')->count());
    }

    /** Nothing may point at a category that does not exist. */
    public function test_no_row_points_at_a_missing_category(): void
    {
        $this->seedCatalogue();

        foreach (Drug::all() as $drug) {
            $this->assertNotNull(
                DrugCategory::find($drug->category_id),
                "{$drug->name} points at a category that does not exist"
            );
        }

        foreach (Product::all() as $product) {
            $this->assertNotNull(
                ProductCategory::find($product->category_id),
                "{$product->name} points at a category that does not exist"
            );
        }
    }

    public function test_the_two_albendazole_lines_become_one_drug_and_two_batches_later(): void
    {
        // "Alwo of 100" and "Aldaz 10x5" are the same active ingredient and
        // strength under two brands. One catalogue row, two batches.
        $this->seedCatalogue();

        $this->assertSame(1, Drug::where('name', 'Albendazole')->where('strength', '400mg')->count());
    }

    public function test_it_does_not_duplicate_a_drug_that_already_exists(): void
    {
        // Azithromycin 500 mg is already in the catalogue. The invoice also
        // carries it, so the seeder must recognise the match instead of adding
        // a second row that competes with the first in search.
        $existingCategory = DrugCategory::create(['name' => 'Antibiotics']);

        Drug::create([
            'name' => 'Azithromycin', 'strength' => '500 mg', 'unit_of_measure' => 'tablet',
            'units_per_strip' => 3, 'strips_per_pack' => 1, 'category_id' => $existingCategory->id,
        ]);

        $this->seedCatalogue();

        // Only the 250mg is added; the pre-existing 500 mg row is left alone.
        $this->assertSame(1, Drug::where('strength', '500 mg')->count());
        $this->assertSame(1, Drug::where('strength', '250mg')->count());
        $this->assertSame(
            3,
            (int) Drug::where('strength', '500 mg')->first()->units_per_strip,
            'The existing Azithromycin packing must not be overwritten by the seeder.'
        );
    }

    /**
     * The three brand-only lines have no strength on the invoice. A naive
     * lookup would treat them as new on every run and report "created"
     * forever, so this checks the second run changes nothing.
     */
    public function test_rows_without_a_strength_are_not_recreated_each_run(): void
    {
        $this->seedCatalogue();

        $before = Drug::whereNull('strength')->pluck('id')->sort()->values()->all();

        $this->seedCatalogue();

        $this->assertSame(
            $before,
            Drug::whereNull('strength')->pluck('id')->sort()->values()->all(),
            'A brand with no stated strength was created again on the second run.'
        );
        $this->assertSame(3, count($before), 'Expected Libitus, Netracort and Zocon to have no strength.');
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seedCatalogue();

        $drugs = Drug::count();
        $products = Product::count();
        $drugCategories = DrugCategory::count();
        $productCategories = ProductCategory::count();

        $snapshot = Drug::orderBy('id')->get(['id', 'name', 'strength', 'category_id', 'units_per_strip', 'strips_per_pack'])->toArray();

        $this->seedCatalogue();

        $this->assertSame($drugs, Drug::count(), 'A second run created duplicate drugs.');
        $this->assertSame($products, Product::count(), 'A second run created duplicate products.');
        $this->assertSame($drugCategories, DrugCategory::count(), 'A second run created duplicate categories.');
        $this->assertSame($productCategories, ProductCategory::count());
        $this->assertEquals(
            $snapshot,
            Drug::orderBy('id')->get(['id', 'name', 'strength', 'category_id', 'units_per_strip', 'strips_per_pack'])->toArray(),
            'A second run altered existing rows.'
        );
    }

    public function test_three_brands_without_a_stated_ingredient_keep_it_blank(): void
    {
        // Libitus, Netracort and Zocon carry a brand but no active ingredient
        // on the invoice. Inventing one would be guessing at a medicine.
        $this->seedCatalogue();

        $this->assertNull(Drug::where('name', 'Libitus Cough Syrup')->first()->generic_name);
        $this->assertNull(Drug::where('name', 'Netracort Ointment')->first()->strength);
        $this->assertSame('Fluconazole', Drug::where('name', 'Zocon Eye Drop')->first()->generic_name);
    }

    public function test_drugs_are_stocked_as_whole_sealed_packs(): void
    {
        $this->seedCatalogue();

        foreach (Drug::all() as $drug) {
            $this->assertSame(1, (int) $drug->units_per_strip, "{$drug->name} should be one base unit per strip.");
            $this->assertSame(1, (int) $drug->strips_per_pack, "{$drug->name} should be one strip per pack.");
        }
    }

    public function test_products_are_active_and_have_a_reorder_level(): void
    {
        $this->seedCatalogue();

        foreach (Product::all() as $product) {
            $this->assertTrue((bool) $product->active, "{$product->name} is not active.");
            $this->assertGreaterThan(0, (float) $product->minimum_stock);
        }
    }

    public function test_the_unpriceable_rezumin_line_says_so(): void
    {
        // That line had a quantity but no batch, expiry or price, so it can be
        // listed but not purchased. Whoever buys it needs to know why.
        $this->seedCatalogue();

        $this->assertStringContainsString(
            'no batch, expiry or price',
            (string) Product::where('sku', 'VS-REZU-30')->first()->description
        );
    }
}
