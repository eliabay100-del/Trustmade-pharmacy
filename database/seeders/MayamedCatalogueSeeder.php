<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Catalogue from the Mayamed Trading PLC invoices.
 *
 * Only the catalogue is seeded here: the categories, the drugs and the retail
 * products. The purchases themselves are a separate step, because a purchase
 * creates batches, and a batch needs an expiry date and a price - both of which
 * are taken from the same paper but verified separately.
 *
 * Idempotent by design. Every row is matched on its natural key and updated in
 * place, so running this twice changes nothing and running it after you have
 * hand-corrected something will not undo your correction unless the value
 * differs from what is defined below.
 *
 * Two deliberate decisions, both reversible per row:
 *
 *   1. WHOLE-PACK STOCKING. Every invoice line is priced in a sealed retail
 *      unit - bottle, tube, box, pack - and the descriptions do not describe
 *      the internals consistently enough to trust ("2*14", "10*10", "2*12"
 *      could each mean several things). So units_per_strip and strips_per_pack
 *      are 1: one base unit is one sealed pack. Selling a single tablet out of
 *      a pack is therefore not currently possible. If you want that, set the
 *      real packing on the affected rows.
 *
 *   2. MINIMUM STOCK IS A PLACEHOLDER. The invoices carry no reorder level, so
 *      the low-stock alerts would otherwise fire on everything at once. These
 *      values are starting points, not answers.
 */
class MayamedCatalogueSeeder extends Seeder
{
    /**
     * Every drug category this seeder files something under.
     *
     * The first group already exists on the current install and is only listed
     * so that this seeder also works against a fresh database, where none of
     * them exist yet. firstOrCreate() finds an existing row and leaves its
     * description alone; it only writes a description when it creates the row.
     *
     * The second group is genuinely new and comes from the invoices.
     */
    private const DRUG_CATEGORIES = [
        'Antibiotics' => 'Antibacterial medicines.',
        'Analgesics and Antipyretics' => 'Pain relief and fever reducers.',
        'Chronic Care' => 'Long-term conditions: cardiovascular, metabolic, endocrine.',
        'Vitamins and Supplements' => 'Vitamins, minerals and supplements.',
        // --- new, required by the invoices ---
        'Anthelmintics' => 'Deworming medicines: albendazole, mebendazole.',
        'Neurology and Antiepileptics' => 'Epilepsy, neuropathic pain and related nervous-system medicines.',
        'Respiratory' => 'Cough, cold and airway medicines.',
        'Dermatology' => 'Topical corticosteroids, antifungals and skin preparations.',
        'Ophthalmology' => 'Eye drops and ophthalmic preparations.',
    ];

    /**
     * Every product category this seeder files something under. Only "Vitamins
     * and Supplements" is new - five of the eight invoiced products are
     * supplements, and filing them under "Other Products" would have made that
     * category meaningless.
     */
    private const PRODUCT_CATEGORIES = [
        'Hygiene Products' => 'Soaps, antiseptics and hand hygiene.',
        'Medical Supplies' => 'Consumables and dressings used in the pharmacy.',
        // --- new, required by the invoices ---
        'Vitamins and Supplements' => 'Vitamin and mineral supplements, including syrups and softgels.',
    ];

    /**
     * Drugs from the invoices. Keyed by category name, resolved at run time.
     *
     * name is what staff see and search for, so it keeps the brand name where
     * the brand is what the invoice and the shelf label use. generic_name is
     * where the active ingredient goes, which is what makes a later "show me
     * everything that is a statin" possible.
     *
     * Left null deliberately: three lines carry a brand with no stated active
     * ingredient on the paper. Guessing the generic from the brand name is how
     * a pharmacy ends up dispensing the wrong thing, so they stay blank until
     * someone confirms them against the box.
     */
    private const DRUGS = [
        // --- Antibiotics (existing category) ---------------------------------
        [
            'name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '250mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
            'note' => 'Amyn brand, 100ml bottle. Distinct from the existing Amoxicillin 500mg capsule.',
        ],
        [
            'name' => 'Cledomox', 'generic_name' => 'Amoxicillin', 'strength' => '457mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Negazole Suspension', 'generic_name' => 'Metronidazole', 'strength' => '125mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Klamoks', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '156mg/5ml',
            'form' => 'oral suspension', 'unit_of_measure' => 'bottle',
            'category' => 'Antibiotics',
        ],
        [
            'name' => 'Valcodin', 'generic_name' => 'Amoxicillin/Clavulanate', 'strength' => '5mg/160mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Antibiotics',
            'note' => 'Dispersible, 2x14 on the invoice.',
        ],
        [
            'name' => 'Azithromycin', 'generic_name' => 'Azithromycin', 'strength' => '250mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Antibiotics',
            'note' => 'Azitro 250mg of 6. The 500mg of 3 already exists - see MATCHED below.',
        ],

        // --- Analgesics and Antipyretics (existing category) -----------------
        [
            'name' => 'Indometacine', 'generic_name' => 'Indometacin', 'strength' => '25mg',
            'form' => 'capsule', 'unit_of_measure' => 'pack',
            'category' => 'Analgesics and Antipyretics',
            'note' => 'Spelled "Indometacine" on the invoice, which is the brand spelling.',
        ],

        // --- Anthelmintics (new category) ------------------------------------
        [
            'name' => 'Albendazole', 'generic_name' => 'Albendazole', 'strength' => '400mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Anthelmintics',
            'note' => 'Covers both invoiced lines: Alwo 400mg of 100 (box) and Aldaz 400mg 10x5 (pack). Same active ingredient and strength, so one row; the two lots become separate batches at purchase time.',
        ],

        // --- Neurology and Antiepileptics (new category) ---------------------
        [
            'name' => 'Pregabaline', 'generic_name' => 'Pregabalin', 'strength' => '75mg',
            'form' => 'capsule', 'unit_of_measure' => 'pack',
            'category' => 'Neurology and Antiepileptics',
            'note' => 'Probion brand, 2x12.',
        ],
        [
            'name' => 'Sodium Valproate', 'generic_name' => 'Sodium Valproate', 'strength' => '200mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Neurology and Antiepileptics',
            'note' => 'SSP (sodium valproate semisodium) 200mg, 10x10.',
        ],

        // --- Respiratory (new category) --------------------------------------
        [
            // generic_name deliberately null: no active ingredient is stated.
            'name' => 'Libitus Cough Syrup', 'generic_name' => null, 'strength' => null,
            'form' => 'syrup', 'unit_of_measure' => 'bottle',
            'category' => 'Respiratory',
        ],

        // --- Dermatology (new category) --------------------------------------
        [
            'name' => 'Netracort Ointment', 'generic_name' => 'Hydrocortisone', 'strength' => null,
            'form' => 'ointment', 'unit_of_measure' => 'tube',
            'category' => 'Dermatology',
            'note' => 'No strength stated on the invoice.',
        ],

        // --- Ophthalmology (new category) ------------------------------------
        [
            'name' => 'Zocon Eye Drop', 'generic_name' => 'Fluconazole', 'strength' => null,
            'form' => 'eye drop', 'unit_of_measure' => 'tube',
            'category' => 'Ophthalmology',
            'note' => 'Zocon is fluconazole per the invoice; no strength stated.',
        ],

        // --- Chronic Care (existing category) --------------------------------
        [
            'name' => 'Coronis', 'generic_name' => 'Carvedilol', 'strength' => '6.25mg',
            'form' => 'tablet', 'unit_of_measure' => 'pack',
            'category' => 'Chronic Care',
            'note' => 'Cardiovascular. Put in Chronic Care alongside Amlodipine rather than creating a near-duplicate category.',
        ],

        // --- Vitamins and Supplements (existing category) ---------------------
        [
            'name' => 'Cal D Lift', 'generic_name' => 'Calcium/Vitamin D3', 'strength' => '1000mg/800IU',
            'form' => 'effervescent tablet', 'unit_of_measure' => 'pack',
            'category' => 'Vitamins and Supplements',
            'note' => '20 effervescent tablets per pack.',
        ],
    ];

    /** Retail products from the invoices. SKUs follow the existing CAT-NAME-SIZE shape. */
    private const PRODUCTS = [
        [
            'name' => 'Denatured Alcohol 70%, 130ml', 'sku' => 'HY-ALC-130',
            'category' => 'Hygiene Products',
            'description' => 'Denatured alcohol 70%, 130ml bottle. Mayamed invoice CA-00009817.',
        ],
        [
            'name' => 'Facemask 3 layers (Surgical) of 50', 'sku' => 'MS-FAC-050',
            'category' => 'Medical Supplies',
            'description' => 'Three-layer surgical face mask, 50 per box. Mayamed invoice CA-00009817.',
        ],
        [
            'name' => 'Kidovit Syrup 200ml', 'sku' => 'VS-KID-200',
            'category' => 'Vitamins and Supplements',
            'description' => 'Multivitamin syrup, 200ml. Mayamed invoice CA-00009743.',
        ],
        [
            'name' => 'Revamp Capsules (Vit B1, B2, B6) of 30', 'sku' => 'VS-RVPC-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Vitamin B complex (B1, B2, B6), 30 capsules. Mayamed invoice CA-00009743.',
        ],
        [
            'name' => 'Revamp Max Capsules of 30', 'sku' => 'VS-RVMX-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Revamp Max, 30 capsules. Mayamed invoice CA-00009743.',
        ],
        [
            'name' => 'Revamp Max Gold (Multivitamin & multimineral) of 30', 'sku' => 'VS-RVMG-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Multivitamin and multimineral, 30 capsules. Mayamed invoice CA-00009743.',
        ],
        [
            'name' => 'Power Plus Gold of 30', 'sku' => 'VS-PWRG-30',
            'category' => 'Vitamins and Supplements',
            'description' => 'Power Plus Gold, 30 capsules. Mayamed invoice CA-00009743.',
        ],
        [
            'name' => 'Rezumin (Multivitamin) of 30', 'sku' => 'VS-REZU-30',
            'category' => 'Vitamins and Supplements',
            // Flagged: the invoice line carried a quantity but no batch, no
            // expiry and no price, so it cannot be purchased - only listed.
            'description' => 'Multivitamin, 30 capsules. NOTE: the invoice line had no batch, expiry or price, so it cannot be entered as a purchase until Mayamed supplies them.',
        ],
    ];

    /**
     * Invoiced items deliberately matched to a row that already exists rather
     * than duplicated. Recorded here so the decision is visible instead of
     * looking like an omission.
     */
    private const MATCHED = [
        'Azithromycin 500 mg (Azitro of 3)' => 'already in the catalogue as Azithromycin 500 mg, tablet - matches the "of 3" packing',
    ];

    /** Placeholder reorder levels, in whole packs. */
    private const DRUG_MIN_STOCK = 10;
    private const PRODUCT_MIN_STOCK = 5;

    /**
     * Progress output. Written through the command when one is attached, and
     * silently skipped when the seeder is called directly, so the class can be
     * used from a test or a console script without a command.
     */
    private function say(string $line): void
    {
        $this->command?->line($line);
    }

    public function run(): void
    {
        [$drugCategories, $newDrugCategories] = $this->ensureCategories(DrugCategory::class, self::DRUG_CATEGORIES);
        [$productCategories, $newProductCategories] = $this->ensureCategories(ProductCategory::class, self::PRODUCT_CATEGORIES);

        $created = 0;
        $updated = 0;

        foreach (self::DRUGS as $row) {
            $categoryId = $this->categoryId($drugCategories, $row['category']);

            // Three invoiced lines carry a brand but no strength. A bare
            // where('strength', null) compiles to "strength = NULL", which
            // matches no row in SQL, so those would look brand new on every run
            // and always report "created". whereNull is the only correct form.
            $query = Drug::where('name', $row['name']);
            $existing = ($row['strength'] === null
                    ? $query->whereNull('strength')
                    : $query->where('strength', $row['strength']))
                ->first();

            $drug = Drug::updateOrCreate(
                ['name' => $row['name'], 'strength' => $row['strength']],
                [
                    'generic_name' => $row['generic_name'],
                    'form' => $row['form'],
                    'category_id' => $categoryId,
                    'unit_of_measure' => $row['unit_of_measure'],
                    'units_per_strip' => 1,
                    'strips_per_pack' => 1,
                    'minimum_stock_level' => self::DRUG_MIN_STOCK,
                ]
            );

            $existing ? $updated++ : $created++;
            $this->say(sprintf('  %s %-38s %-12s [%s]', $existing ? 'updated' : 'created', $drug->name, $drug->strength ?? '-', $row['category']));
        }

        $productsCreated = 0;
        $productsUpdated = 0;

        foreach (self::PRODUCTS as $row) {
            $existing = Product::where('sku', $row['sku'])->first();

            Product::updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'name' => $row['name'],
                    'category_id' => $this->categoryId($productCategories, $row['category']),
                    'description' => $row['description'],
                    'minimum_stock' => self::PRODUCT_MIN_STOCK,
                    'active' => true,
                ]
            );

            $existing ? $productsUpdated++ : $productsCreated++;
            $this->say(sprintf('  %s %-46s [%s]', $existing ? 'updated' : 'created', $row['name'], $row['category']));
        }

        $this->say("");
        $this->say(sprintf(
            'Categories created: %d drug, %d product. Drugs created %d, updated %d. Products created %d, updated %d.',
            count($newDrugCategories),
            count($newProductCategories),
            $created,
            $updated,
            $productsCreated,
            $productsUpdated
        ));

        if (self::MATCHED !== []) {
            $this->say("");
            $this->say('Matched to existing rows rather than duplicated:');
            foreach (self::MATCHED as $item => $why) {
                $this->say("  {$item} - {$why}");
            }
        }
    }

    /**
     * Ensure every category this seeder depends on exists.
     *
     * Lists categories that already exist on the current install alongside the
     * new ones, so the seeder works on a fresh database too. firstOrCreate()
     * leaves an existing row - and its description - untouched.
     *
     * @param  class-string  $model
     * @param  array<string, string>  $categories
     * @return array{0: array<string, int>, 1: array<int, string>} ids by name, and the names actually created
     */
    private function ensureCategories(string $model, array $categories): array
    {
        $ids = [];
        $created = [];

        foreach ($model::all() as $existing) {
            $ids[$existing->name] = $existing->id;
        }

        foreach ($categories as $name => $description) {
            if (isset($ids[$name])) {
                continue;
            }

            $ids[$name] = $model::firstOrCreate(['name' => $name], ['description' => $description])->id;
            $created[] = $name;
        }

        return [$ids, $created];
    }

    /** @param array<string, int> $categories */
    private function categoryId(array $categories, string $name): int
    {
        if (! isset($categories[$name])) {
            throw new \RuntimeException("Category \"{$name}\" was not created; refusing to file a catalogue row under nothing.");
        }

        return $categories[$name];
    }
}
