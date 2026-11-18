<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report card tables without dependencies on study groups (rombel): subjects, groups, curricula (initial templates),
 * settings, description templates, and religion teachers. All utf8mb4_unicode_ci, UUID.
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

    public function up(): void
    {
        $this->make('rapor_subject_groups', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('code');
            $t->timestamps();
            $t->index('code');
        });

        $this->make('rapor_subjects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('code');
            $t->boolean('is_religion_subject')->default(false);
            // Enum value App\Enums\Student\Religion (islam, kristen, ...). Plain text, not encrypted.
            $t->string('religion', 20)->nullable();
            $t->char('religion_hash', 64)->nullable();
            $t->enum('status', ['active', 'archived'])->default('active');
            $t->timestamps();
            $t->index('code');
            $t->index('religion_hash');
        });

        $this->make('rapor_curricula', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('code');               // not unique in legacy report card system
            $t->text('description')->nullable();
            $t->enum('status', ['active', 'archived'])->default('archived');
            $t->timestamps();
            $t->index('code');
        });

        $this->make('rapor_academic_year_curricula', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('curriculum_id')->constrained('rapor_curricula')->cascadeOnDelete();
            $t->foreignUuid('academic_year_id')->constrained('core_academic_years')->cascadeOnDelete();
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
            $t->index(['academic_year_id', 'curriculum_id'], 'rapor_ayc_year_curr_index');
        });

        // Initial template (NOT the single source of truth for subjects per study group).
        $this->make('rapor_curriculum_subjects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('curriculum_id')->constrained('rapor_curricula')->cascadeOnDelete();
            $t->foreignUuid('subject_id')->constrained('rapor_subjects')->cascadeOnDelete();
            $t->foreignUuid('subject_group_id')->nullable()->constrained('rapor_subject_groups')->nullOnDelete();
            $t->unsignedTinyInteger('grade_level');          // 10 / 11 / 12
            $t->unsignedTinyInteger('sort_order')->nullable();
            $t->timestamps();
            $t->unique(['curriculum_id', 'subject_id', 'grade_level'], 'rapor_cs_unique');
        });

        // Initial template per concentration. curriculum_id may be NULL (NULLs are treated as distinct in unique indexes).
        $this->make('rapor_concentration_subjects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('concentration_id')->constrained('core_concentrations')->cascadeOnDelete();
            $t->foreignUuid('subject_id')->constrained('rapor_subjects')->cascadeOnDelete();
            $t->foreignUuid('curriculum_id')->nullable()->constrained('rapor_curricula')->nullOnDelete();
            $t->enum('type', ['required', 'elective'])->default('required');
            $t->timestamps();
            $t->unique(['concentration_id', 'subject_id', 'curriculum_id'], 'rapor_conc_subj_unique');
        });

        // One row per semester. report_date = legacy settings.tanggal_rapor (required in source).
        $this->make('rapor_settings', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('semester_id')->unique()->constrained('core_semesters')->cascadeOnDelete();
            $t->foreignUuid('headmaster_staff_id')->nullable()->constrained('staff_data')->nullOnDelete();
            $t->date('report_date');
            $t->json('display_config')->nullable();
            $t->timestamps();
        });

        $this->make('rapor_score_achievement_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('semester_id')->constrained('core_semesters')->cascadeOnDelete();
            $t->foreignUuid('subject_id')->nullable()->constrained('rapor_subjects')->cascadeOnDelete();
            $t->string('grade_level', 10)->nullable();
            $t->integer('min_range');
            $t->integer('max_range');
            $t->string('predicate');
            $t->text('description');
            $t->boolean('is_active')->default(true);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            // NULLs are treated as distinct: global rows (subject/grade NULL) can duplicate. Reported in dry-run.
            $t->unique(['semester_id', 'subject_id', 'grade_level', 'min_range'], 'rapor_sat_unique');
        });

        $this->make('rapor_cocurricular_achievement_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('semester_id')->constrained('core_semesters')->cascadeOnDelete();
            $t->string('subdimension', 191)->nullable();
            $t->string('grade_level', 10)->nullable();
            $t->string('predicate', 100);
            $t->text('description');
            $t->boolean('is_active')->default(true);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['semester_id', 'grade_level', 'subdimension', 'predicate'], 'rapor_cat_unique');
        });

        // extracurricular_id NULL = general template for that semester; populated = PINTAR extracurricular row in the same semester.
        $this->make('rapor_extracurricular_description_templates', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('semester_id')->constrained('core_semesters')->cascadeOnDelete();
            $t->foreignUuid('extracurricular_id')
                ->nullable()
                ->constrained('acd_extracurriculars', 'id', 'rapor_edt_extracurricular_fk')
                ->cascadeOnDelete();
            $t->string('predicate', 100);
            $t->text('description');
            $t->boolean('use_placeholder')->default(false);
            $t->boolean('is_active')->default(true);
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['semester_id', 'extracurricular_id', 'predicate'], 'rapor_edt_unique');
        });

        // Religion teachers: encrypted specialization WITHOUT serialize (shared key) + hash SHA-256 (staff style).
        $this->make('rapor_religion_teachers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('staff_id')->unique()->constrained('staff_data')->cascadeOnDelete();
            $t->boolean('is_religion_teacher')->default(true);
            $t->text('specialization_encrypted')->nullable();
            $t->char('specialization_hash', 64)->nullable();
            $t->timestamps();
            $t->index('specialization_hash');
        });
    }

    public function down(): void
    {
        foreach (
            [
                'rapor_religion_teachers',
                'rapor_extracurricular_description_templates',
                'rapor_cocurricular_achievement_templates',
                'rapor_score_achievement_templates',
                'rapor_settings',
                'rapor_concentration_subjects',
                'rapor_curriculum_subjects',
                'rapor_academic_year_curricula',
                'rapor_curricula',
                'rapor_subjects',
                'rapor_subject_groups',
            ] as $table
        ) {
            Schema::dropIfExists($table);
        }
    }
};
