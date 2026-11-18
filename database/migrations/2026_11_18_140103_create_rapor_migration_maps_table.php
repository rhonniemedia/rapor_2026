<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapping of legacy report card IDs (UUID) -> new IDs. Separate from migration_mappings
 * because the old_id column there is bigint, whereas report card IDs are UUIDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rapor_migration_maps')) {
            return;
        }

        Schema::create('rapor_migration_maps', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->string('table_name', 100);      // legacy report card table name, e.g., 'pelajars'
            $table->char('old_id', 36);             // legacy ID (report card UUID)
            $table->char('new_id', 36)->nullable(); // ID in the target table; null = intentionally unmapped
            $table->string('note')->nullable();     // e.g., 'guessed', 'manual'
            $table->timestamps();

            $table->unique(['table_name', 'old_id'], 'rapor_maps_table_old_unique');
            $table->index(['table_name', 'new_id'], 'rapor_maps_table_new_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_migration_maps');
    }
};
