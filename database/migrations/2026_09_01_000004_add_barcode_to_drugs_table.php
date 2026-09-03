<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (!Schema::hasColumn('drugs', 'barcode')) {
                $table->string('barcode')->nullable()->after('name')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            if (Schema::hasColumn('drugs', 'barcode')) {
                $table->dropColumn('barcode');
            }
        });
    }
};
