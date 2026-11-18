<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared table schema changes for report card (rapor) integration (handoff part 4).
 * Idempotent: safe to re-run. Does not modify old migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. core_schools: 8 columns from legacy school data that do not have a place yet.
        //    (nss, website, postal_code, logo_path, establishment decree ALREADY exist in core_schools.)
        Schema::table('core_schools', function (Blueprint $table) {
            if (! Schema::hasColumn('core_schools', 'nis')) {
                $table->string('nis', 20)->nullable()->after('nss');
            }
            if (! Schema::hasColumn('core_schools', 'nds')) {
                $table->string('nds', 20)->nullable()->after('nis');
            }
            if (! Schema::hasColumn('core_schools', 'education_level')) {
                $table->string('education_level', 20)->nullable();
            }
            if (! Schema::hasColumn('core_schools', 'accreditation_status')) {
                $table->string('accreditation_status', 5)->nullable();
            }
            if (! Schema::hasColumn('core_schools', 'accreditation_year')) {
                $table->year('accreditation_year')->nullable();
            }
            if (! Schema::hasColumn('core_schools', 'operational_permit_decree')) {
                $table->string('operational_permit_decree', 100)->nullable();
            }
            if (! Schema::hasColumn('core_schools', 'operational_permit_date')) {
                $table->date('operational_permit_date')->nullable();
            }
            if (! Schema::hasColumn('core_schools', 'local_government_logo_path')) {
                $table->string('local_government_logo_path')->nullable();
            }
        });

        // 2. acd_students: status within the family.
        if (! Schema::hasColumn('acd_students', 'family_status')) {
            Schema::table('acd_students', function (Blueprint $table) {
                $table->string('family_status', 50)->nullable()->after('child_order');
            });
        }

        // 3. acd_extracurriculars: description, status, supervisor -> staff_data.
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

        // FK supervisor_id: users -> staff_data. Table must be empty or supervisor_id NULL,
        // because old values point to users.id instead of staff_data.id.
        $stale = DB::table('acd_extracurriculars')->whereNotNull('supervisor_id')->count();
        if ($stale > 0 && $this->supervisorFkTarget() === 'users') {
            throw new RuntimeException(
                "acd_extracurriculars has {$stale} rows with supervisor_id (still referencing users IDs). "
                    . 'Clear or map them to staff_data before running this migration.'
            );
        }
        if ($this->supervisorFkTarget() === 'users') {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->dropForeign('acd_extracurriculars_supervisor_id_foreign');
            });
        }
        if ($this->supervisorFkTarget() === null) {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->foreign('supervisor_id', 'acd_extracurriculars_supervisor_id_foreign')
                    ->references('id')->on('staff_data')->nullOnDelete();
            });
        }

        // Unique per semester + name (handoff decision). Skipped if it already exists.
        if (! Schema::hasIndex('acd_extracurriculars', 'acd_extracurriculars_semester_id_name_unique')) {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->unique(['semester_id', 'name'], 'acd_extracurriculars_semester_id_name_unique');
            });
        }
    }

    public function down(): void
    {
        // Intentionally only reverts what is safe. Added columns are kept to prevent data loss.
        if ($this->supervisorFkTarget() === 'staff_data') {
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->dropForeign('acd_extracurriculars_supervisor_id_foreign');
            });
            Schema::table('acd_extracurriculars', function (Blueprint $table) {
                $table->foreign('supervisor_id', 'acd_extracurriculars_supervisor_id_foreign')
                    ->references('id')->on('users');
            });
        }
    }

    /** The table currently referenced by the supervisor_id FK, or null if the FK does not exist yet. */
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
