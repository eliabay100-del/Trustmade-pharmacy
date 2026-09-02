<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Find and drop whatever the FK constraint on customer_id is named
        // (varies depending on how the table was originally created).
        $fk = DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'issued_items'
            AND COLUMN_NAME = 'customer_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if ($fk) {
            DB::statement("ALTER TABLE issued_items DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        DB::statement("ALTER TABLE issued_items MODIFY customer_id BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE issued_items ADD CONSTRAINT issued_items_customer_id_foreign
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE issued_items DROP FOREIGN KEY issued_items_customer_id_foreign");
        DB::statement("ALTER TABLE issued_items MODIFY customer_id BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE issued_items ADD CONSTRAINT issued_items_customer_id_foreign
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE");
    }
};
