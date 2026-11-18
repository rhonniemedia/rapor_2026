<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report card tables bound to study groups (acd_class_groups) per semester: single source of truth for subjects,
 * teaching assignments, scores, attendances, homeroom teacher notes, and cocurricular activities.
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
        // Reference curriculum per study group (one per class group).
        $this->make('rapor_class_group_curricula', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('class_group_id')->unique()->constrained('acd_class_groups')->cascadeOnDelete();
            $t->foreignUuid('academic_year_curriculum_id')->constrained('rapor_academic_year_curricula')->cascadeOnDelete();
            $t->timestamps();
        });

        // SINGLE SOURCE OF TRUTH for subjects per study group per semester.
        $this->make('rapor_class_group_subjects', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('class_group_id')->constrained('acd_class_groups')->cascadeOnDelete();
            $t->foreignUuid('subject_id')->constrained('rapor_subjects')->restrictOnDelete();
            $t->foreignUuid('subject_group_id')->nullable()->constrained('rapor_subject_groups')->nullOnDelete();
            $t->unsignedTinyInteger('sort_order')->nullable();
            $t->enum('type', ['required', 'elective'])->default('required');
            $t->timestamps();
            $t->unique(['class_group_id', 'subject_id'], 'rapor_cgs_unique');
        });

        $this->make('rapor_teaching_assignments', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('class_group_id')->constrained('acd_class_groups')->cascadeOnDelete();
            $t->foreignUuid('subject_id')->constrained('rapor_subjects')->restrictOnDelete();
            $t->foreignUuid('staff_id')->nullable()->constrained('staff_data')->nullOnDelete();
            $t->timestamps();
            $t->unique(['class_group_id', 'subject_id'], 'rapor_ta_unique');
        });

        // Final report card scores: one row per student + subject + semester. Scores FOLLOW THE STUDENT
        // (not the study group), so there is no class_group_id. Semester/class membership consistency
        // is enforced at the application level. Deleting student/subject/semester is restricted so scores are not silently lost.
        $this->make('rapor_scores', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained('acd_students')->restrictOnDelete();
            $t->foreignUuid('subject_id')->constrained('rapor_subjects')->restrictOnDelete();
            $t->foreignUuid('teaching_assignment_id')->nullable()->constrained('rapor_teaching_assignments')->nullOnDelete();
            $t->foreignUuid('semester_id')->constrained('core_semesters')->restrictOnDelete();
            $t->foreignUuid('staff_id')->nullable()->constrained('staff_data')->nullOnDelete(); // last inputter
            $t->decimal('score', 5, 2);
            $t->enum('predicate', ['A', 'B', 'C', 'D'])->nullable();
            $t->text('competency_description')->nullable();
            $t->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['student_id', 'subject_id', 'semester_id'], 'rapor_scores_unique');
            $t->index(['semester_id', 'student_id'], 'rapor_scores_sem_student_index');
        });

        // class_group_id is already scoped per semester, so semester_id is omitted.
        $this->make('rapor_attendances', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained('acd_students')->cascadeOnDelete();
            $t->foreignUuid('class_group_id')->constrained('acd_class_groups')->cascadeOnDelete();
            $t->unsignedInteger('sick_count')->default(0);
            $t->unsignedInteger('permission_count')->default(0);
            $t->unsignedInteger('unexcused_count')->default(0);
            $t->timestamps();
            $t->unique(['student_id', 'class_group_id'], 'rapor_att_unique');
        });

        // class_group_id is derived during migration (legacy source lacks rombel_id).
        // Intentionally without a unique constraint for now: duplicates per student+class are reported during dry-run,
        // and the unique constraint will be added via a subsequent migration once the data is cleaned.
        $this->make('rapor_homeroom_notes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained('acd_students')->cascadeOnDelete();
            $t->foreignUuid('class_group_id')->constrained('acd_class_groups')->cascadeOnDelete();
            $t->foreignUuid('staff_id')->nullable()->constrained('staff_data')->nullOnDelete();
            $t->text('note');
            $t->timestamp('entered_at')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'class_group_id'], 'rapor_hn_student_cg_index');
        });

        $this->make('rapor_cocurriculars', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('student_id')->constrained('acd_students')->cascadeOnDelete();
            $t->foreignUuid('class_group_id')->constrained('acd_class_groups')->cascadeOnDelete();
            $t->foreignUuid('staff_id')->nullable()->constrained('staff_data')->nullOnDelete();
            $t->string('predicate', 25)->nullable();
            $t->text('achievement')->nullable();
            $t->date('entered_date')->nullable();
            $t->timestamps();
            $t->index(['student_id', 'class_group_id'], 'rapor_cc_student_cg_index');
        });
    }

    public function down(): void
    {
        foreach (
            [
                'rapor_cocurriculars',
                'rapor_homeroom_notes',
                'rapor_attendances',
                'rapor_scores',
                'rapor_teaching_assignments',
                'rapor_class_group_subjects',
                'rapor_class_group_curricula',
            ] as $table
        ) {
            Schema::dropIfExists($table);
        }
    }
};
