<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Effective class of a student for ONE semester when it differs from the shared class membership
 * (acd_class_group_students), which the report card app is not allowed to change.
 * When a row exists here, the report card app uses it instead of the shared membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rapor_class_overrides')) {
            return;
        }

        Schema::create('rapor_class_overrides', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('acd_students')->cascadeOnDelete();
            $table->foreignUuid('semester_id')->constrained('core_semesters')->cascadeOnDelete();
            $table->foreignUuid('class_group_id')->constrained('acd_class_groups')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id'], 'rapor_co_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_class_overrides');
    }
};
