<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extracurriculars owned by the report card app (rapor_*):
 * - rapor_extracurriculars: global (name, description, status).
 * - rapor_extracurricular_supervisors: supervisor (staff) per extracurricular per semester.
 * - rapor_extracurricular_members: members with score and description per semester.
 * The description templates now point to rapor_extracurriculars instead of acd_extracurriculars.
 */
return new class extends Migration
{
    private function make(string $name, Closure $callback): void
    {
        if (Schema::hasTable($name)) {
            return;
        }
        Schema::create($name, function (Blueprint $table) use ($callback) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $callback($table);
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::selectOne(
            "SELECT 1 AS x FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $name]
        ) !== null;
    }

    public function up(): void
    {
        $this->make('rapor_extracurriculars', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->text('description')->nullable();
            $t->enum('status', ['active', 'archived'])->default('active');
            $t->timestamps();
        });

        $this->make('rapor_extracurricular_supervisors', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('extracurricular_id')->constrained('rapor_extracurriculars')->cascadeOnDelete();
            $t->foreignUuid('semester_id')->constrained('core_semesters')->cascadeOnDelete();
            $t->foreignUuid('staff_id')->nullable()->constrained('staff_data')->nullOnDelete();
            $t->timestamps();
            $t->unique(['extracurricular_id', 'semester_id'], 'rapor_es_unique');
        });

        $this->make('rapor_extracurricular_members', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('semester_id')->constrained('core_semesters')->cascadeOnDelete();
            $t->foreignUuid('extracurricular_id')->constrained('rapor_extracurriculars')->cascadeOnDelete();
            $t->foreignUuid('student_id')->constrained('acd_students')->cascadeOnDelete();
            $t->enum('score', ['A', 'B', 'C', 'D']);
            $t->text('description')->nullable();
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['semester_id', 'extracurricular_id', 'student_id'], 'rapor_em_unique');
            $t->index('student_id', 'rapor_em_student_index');
        });

        // Point the description templates to rapor_extracurriculars (they were created pointing to acd_extracurriculars).
        $templates = 'rapor_extracurricular_description_templates';
        if (Schema::hasTable($templates) && $this->foreignKeyExists($templates, 'rapor_edt_extracurricular_fk')) {
            if (DB::table($templates)->whereNotNull('extracurricular_id')->exists()) {
                throw new RuntimeException("{$templates} already has rows pointing to acd_extracurriculars; clear them before running this migration.");
            }
            Schema::table($templates, function (Blueprint $t) {
                $t->dropForeign('rapor_edt_extracurricular_fk');
            });
        }
        if (Schema::hasTable($templates) && ! $this->foreignKeyExists($templates, 'rapor_edt_rapor_extracurricular_fk')) {
            Schema::table($templates, function (Blueprint $t) {
                $t->foreign('extracurricular_id', 'rapor_edt_rapor_extracurricular_fk')
                    ->references('id')->on('rapor_extracurriculars')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $templates = 'rapor_extracurricular_description_templates';
        if (Schema::hasTable($templates) && $this->foreignKeyExists($templates, 'rapor_edt_rapor_extracurricular_fk')) {
            Schema::table($templates, function (Blueprint $t) {
                $t->dropForeign('rapor_edt_rapor_extracurricular_fk');
            });
        }

        Schema::dropIfExists('rapor_extracurricular_members');
        Schema::dropIfExists('rapor_extracurricular_supervisors');
        Schema::dropIfExists('rapor_extracurriculars');
    }
};
