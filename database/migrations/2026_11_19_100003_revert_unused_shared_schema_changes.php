<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the shared-table schema changes that the report card app no longer uses:
 * - acd_extracurriculars: columns description/status, the duplicate unique index added by
 *   2026_11_18_140049 (the ORIGINAL unique_extracurricular_per_semester stays), and the supervisor_id
 *   foreign key is restored to its original target (users, no ON DELETE action).
 * - acd_students: column family_status.
 * Kept on purpose: the 8 core_schools columns (used by rapor:copy --steps=school).
 *
 * Refuses to run when it would lose data (non-empty acd_extracurriculars or family_status with values).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('acd_extracurriculars')->exists() || DB::table('acd_extracurricular_students')->exists()) {
            throw new RuntimeException('acd_extracurriculars / acd_extracurricular_students contain rows; refusing to revert their schema.');
        }
        if (Schema::hasColumn('acd_students', 'family_status') && DB::table('acd_students')->whereNotNull('family_status')->exists()) {
            throw new RuntimeException('acd_students.family_status contains values; refusing to drop it.');
        }

        // supervisor_id: staff_data -> users (original).
        if ($this->supervisorFkTarget() === 'staff_data') {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->dropForeign('acd_extracurriculars_supervisor_id_foreign');
            });
        }
        if ($this->supervisorFkTarget() === null) {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->foreign('supervisor_id', 'acd_extracurriculars_supervisor_id_foreign')->references('id')->on('users');
            });
        }

        // Duplicate unique index added earlier (the original one keeps protecting semester + name).
        if (Schema::hasIndex('acd_extracurriculars', 'acd_extracurriculars_semester_id_name_unique')) {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->dropUnique('acd_extracurriculars_semester_id_name_unique');
            });
        }

        foreach (['description', 'status'] as $column) {
            if (Schema::hasColumn('acd_extracurriculars', $column)) {
                Schema::table('acd_extracurriculars', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('acd_students', 'family_status')) {
            Schema::table('acd_students', function (Blueprint $table) {
                $table->dropColumn('family_status');
            });
        }
    }

    public function down(): void
    {
        // Re-adds the dropped columns (empty) so the previous schema can be restored; the foreign key is left as is.
        if (! Schema::hasColumn('acd_extracurriculars', 'description')) {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }
        if (! Schema::hasColumn('acd_extracurriculars', 'status')) {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->enum('status', ['active', 'archived'])->default('active')->after('description');
            });
        }
        if (! Schema::hasColumn('acd_students', 'family_status')) {
            Schema::table('acd_students', function (Blueprint $table) {
                $table->string('family_status', 50)->nullable()->after('child_order');
            });
        }
    }

    /** The table currently referenced by the supervisor_id foreign key, or null when it does not exist. */
    private function supervisorFkTarget(): ?string
    {
        $row = DB::selectOne(
            'SELECT REFERENCED_TABLE_NAME AS t FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['acd_extracurriculars', 'acd_extracurriculars_supervisor_id_foreign']
        );

        return $row->t ?? null;
    }
};
