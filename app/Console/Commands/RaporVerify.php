<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * rapor:verify - READ-ONLY validation of the migration (writes nothing, anywhere).
 *
 * Compares the staging tables (old_*) with the migrated rapor_* tables:
 * 1. Row counts per table.
 * 2. Number of scores and sum of scores per student per semester (old vs new).
 * 3. Printability: how many scores belong to a subject of the student's effective class
 *    (class override first, then the shared class membership).
 * 4. State of the shared tables (access rows, school columns, untouched extracurricular tables).
 *
 * Exit code is non-zero when any check FAILS.
 */
class RaporVerify extends Command
{
    protected $signature = 'rapor:verify {--max-rows=20 : Maximum mismatching rows listed per check}';

    protected $description = 'Read-only validation of the report card migration (row counts, score sums, printability).';

    private string $prefix = 'old_';
    private array $rows = [];
    private int $failures = 0;
    private array $maps = [];
    private array $details = [];

    public function handle(): int
    {
        $this->prefix = (string) config('rapor_migration.staging_prefix', 'old_');

        foreach (['rapor_migration_maps', 'rapor_scores', 'rapor_class_overrides'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Table {$table} does not exist.");

                return self::FAILURE;
            }
        }

        foreach (DB::table('rapor_migration_maps')->whereNotNull('new_id')->get() as $row) {
            $this->maps[$row->table_name][$row->old_id] = $row->new_id;
        }
        if (empty($this->maps['pelajars']) || empty($this->maps['tahun_ajaran_semesters'])) {
            $this->error("rapor_migration_maps is empty. Run 'php artisan rapor:map' (without --dry-run) first.");

            return self::FAILURE;
        }

        $this->checkCounts();
        $this->checkScoreSums();
        $this->checkPrintable();
        $this->checkSharedTables();

        $this->table(['Status', 'Check', 'Expected', 'Actual', 'Note'], $this->rows);
        foreach ($this->details as $title => $lines) {
            $this->newLine();
            $this->warn($title);
            foreach ($lines as $line) {
                $this->line('  ' . $line);
            }
        }

        $this->newLine();
        $this->failures > 0
            ? $this->error("{$this->failures} check(s) FAILED.")
            : $this->info('All checks passed.');

        return $this->failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ------------------------------------------------------------------------

    private function old(string $table)
    {
        return DB::table($this->prefix . $table);
    }

    private function compare(string $check, mixed $expected, mixed $actual, string $note = ''): void
    {
        $pass = (string) $expected === (string) $actual;
        $this->failures += $pass ? 0 : 1;
        $this->rows[] = [$pass ? 'PASS' : 'FAIL', $check, $expected, $actual, $note];
    }

    private function addInfo(string $check, mixed $value, string $note = ''): void
    {
        $this->rows[] = ['INFO', $check, '-', $value, $note];
    }

    // ------------------------------------------------------------------------
    // 1. Row counts
    // ------------------------------------------------------------------------

    private function checkCounts(): void
    {
        $pairs = [
            ['mata_pelajaran_kelompoks', 'rapor_subject_groups'],
            ['mata_pelajarans', 'rapor_subjects'],
            ['kurikulums', 'rapor_curricula'],
            ['tahun_ajaran_kurikulums', 'rapor_academic_year_curricula'],
            ['kurikulum_mata_pelajarans', 'rapor_curriculum_subjects'],
            ['jurusan_mata_pelajarans', 'rapor_concentration_subjects'],
            ['pengaturans', 'rapor_settings'],
            ['template_nilai_capaians', 'rapor_score_achievement_templates'],
            ['template_kokurikuler_capaians', 'rapor_cocurricular_achievement_templates'],
            ['template_ekstrakurikuler_deskripsis', 'rapor_extracurricular_description_templates'],
            ['ekstrakurikulers', 'rapor_extracurriculars'],
            ['ekskul_pelajars', 'rapor_extracurricular_members'],
            ['kehadirans', 'rapor_attendances'],
            ['catatan_wali_kelas', 'rapor_homeroom_notes'],
            ['kokurikulers', 'rapor_cocurriculars'],
        ];
        foreach ($pairs as [$old, $new]) {
            $this->compare("{$new}", $this->old($old)->count(), DB::table($new)->count(), "old_{$old}");
        }

        $this->compare('rapor_scores', $this->old('nilais')->whereNull('deleted_at')->count(), DB::table('rapor_scores')->whereNull('deleted_at')->count(), 'old_nilais (not soft-deleted)');

        $semesters = count(array_unique(array_values($this->maps['tahun_ajaran_semesters'])));
        $this->compare('rapor_extracurricular_supervisors', $this->old('ekstrakurikulers')->count() * $semesters, DB::table('rapor_extracurricular_supervisors')->count(), "extracurriculars x {$semesters} semesters");

        // A subject scored in only one semester belongs to that semester only; unscored assignments go to every semester.
        $scored = [];
        foreach ($this->old('nilais')->whereNull('deleted_at')->select('rombel_pengajar_id', 'tahun_ajaran_semester_id')->distinct()->cursor() as $r) {
            $semesterId = $this->maps['tahun_ajaran_semesters'][$r->tahun_ajaran_semester_id] ?? null;
            if ($semesterId) {
                $scored[$r->rombel_pengajar_id][$semesterId] = true;
            }
        }
        $expected = 0;
        foreach ($this->old('rombel_pengajars')->pluck('id') as $id) {
            $expected += isset($scored[$id]) ? count($scored[$id]) : $semesters;
        }
        $this->compare('rapor_class_group_subjects', $expected, DB::table('rapor_class_group_subjects')->count(), 'assignments x valid semesters');
        $this->compare('rapor_teaching_assignments', $expected, DB::table('rapor_teaching_assignments')->count(), 'same set as class group subjects');

        $this->addInfo('rapor_teaching_assignments without staff', DB::table('rapor_teaching_assignments')->whereNull('staff_id')->count(), 'assignments with no teacher');
        $this->addInfo('rapor_scores without teaching assignment', DB::table('rapor_scores')->whereNull('teaching_assignment_id')->count());
    }

    // ------------------------------------------------------------------------
    // 2. Score count and sum per student per semester
    // ------------------------------------------------------------------------

    private function checkScoreSums(): void
    {
        $old = [];
        $query = $this->old('nilais')->whereNull('deleted_at')
            ->select('pelajar_id', 'tahun_ajaran_semester_id', DB::raw('COUNT(*) AS c'), DB::raw('ROUND(SUM(nilai_angka), 2) AS s'))
            ->groupBy('pelajar_id', 'tahun_ajaran_semester_id');
        foreach ($query->cursor() as $r) {
            $studentId = $this->maps['pelajars'][$r->pelajar_id] ?? null;
            $semesterId = $this->maps['tahun_ajaran_semesters'][$r->tahun_ajaran_semester_id] ?? null;
            if ($studentId && $semesterId) {
                $old[$studentId . '|' . $semesterId] = [(int) $r->c, round((float) $r->s, 2)];
            }
        }

        $new = [];
        $query = DB::table('rapor_scores')->whereNull('deleted_at')
            ->select('student_id', 'semester_id', DB::raw('COUNT(*) AS c'), DB::raw('ROUND(SUM(score), 2) AS s'))
            ->groupBy('student_id', 'semester_id');
        foreach ($query->cursor() as $r) {
            $new[$r->student_id . '|' . $r->semester_id] = [(int) $r->c, round((float) $r->s, 2)];
        }

        $bad = [];
        foreach ($old + $new as $key => $_) {
            $a = $old[$key] ?? null;
            $b = $new[$key] ?? null;
            if ($a === null || $b === null || $a[0] !== $b[0] || abs($a[1] - $b[1]) > 0.005) {
                $bad[] = $key . '  old=' . json_encode($a) . '  new=' . json_encode($b);
            }
        }

        $this->compare('score count+sum per student per semester', count($old) . ' groups, 0 mismatches', count($old) . ' groups, ' . count($bad) . ' mismatches', 'old_nilais vs rapor_scores');
        if ($bad) {
            $this->details['Mismatching student|semester groups'] = array_slice($bad, 0, (int) $this->option('max-rows'));
        }
    }

    // ------------------------------------------------------------------------
    // 3. Printability: scores that belong to a subject of the student's effective class
    // ------------------------------------------------------------------------

    private function checkPrintable(): void
    {
        $classOf = [];
        foreach (DB::table('acd_class_groups')->get(['id', 'semester_id']) as $g) {
            $classOf[$g->id] = $g->semester_id;
        }
        $membership = [];
        foreach (DB::table('acd_class_group_students')->get(['student_id', 'class_group_id']) as $m) {
            $semesterId = $classOf[$m->class_group_id] ?? null;
            if ($semesterId) {
                $membership[$m->student_id][$semesterId][$m->class_group_id] = true;
            }
        }
        $overrides = [];
        foreach (DB::table('rapor_class_overrides')->get(['student_id', 'semester_id', 'class_group_id']) as $o) {
            $overrides[$o->student_id][$o->semester_id] = $o->class_group_id;
        }
        $subjectsOfClass = [];
        foreach (DB::table('rapor_class_group_subjects')->get(['class_group_id', 'subject_id']) as $s) {
            $subjectsOfClass[$s->class_group_id][$s->subject_id] = true;
        }

        $printable = $history = $noClass = 0;
        $historyStudents = [];
        foreach (DB::table('rapor_scores')->whereNull('deleted_at')->select('student_id', 'semester_id', 'subject_id')->cursor() as $s) {
            $classId = $overrides[$s->student_id][$s->semester_id] ?? null;
            if ($classId === null) {
                $classes = array_keys($membership[$s->student_id][$s->semester_id] ?? []);
                $classId = count($classes) === 1 ? $classes[0] : null;
            }

            if ($classId === null) {
                $noClass++;
            } elseif (isset($subjectsOfClass[$classId][$s->subject_id])) {
                $printable++;
            } else {
                $history++;
                $historyStudents[$s->student_id] = true;
            }
        }

        $this->addInfo('scores printable (subject of the effective class)', $printable);
        $this->addInfo('scores kept as class history (not printed)', $history, count($historyStudents) . ' student(s); expected only students who moved class');
        $this->compare('scores of students without a single effective class', 0, $noClass, 'ambiguous or missing class membership');
    }

    // ------------------------------------------------------------------------
    // 4. Shared tables
    // ------------------------------------------------------------------------

    private function checkSharedTables(): void
    {
        $appId = DB::table('core_apps')->where('code', 'rapor')->value('id');
        if ($appId) {
            $byRole = DB::table('user_app_roles as u')->join('user_roles as r', 'r.id', '=', 'u.role_id')
                ->where('u.app_id', $appId)->select('r.name', DB::raw('COUNT(*) AS c'))->groupBy('r.name')->pluck('c', 'name')->all();
            $this->addInfo('user_app_roles for the rapor app', $byRole ? json_encode($byRole) : 'none yet', 'filled by: rapor:copy --steps=access');
        }

        $school = DB::table('core_schools')->first();
        if ($school && Schema::hasColumn('core_schools', 'education_level')) {
            $filled = collect(['nis', 'nds', 'education_level', 'accreditation_status', 'accreditation_year', 'operational_permit_decree', 'operational_permit_date', 'local_government_logo_path'])
                ->filter(fn($column) => ($school->{$column} ?? null) !== null && $school->{$column} !== '')->count();
            $this->addInfo('core_schools new columns filled', "{$filled} of 8", 'filled by: rapor:copy --steps=school');
        }

        // Must stay untouched: extracurriculars live in rapor_* tables.
        $this->compare('acd_extracurriculars (must stay untouched)', 0, DB::table('acd_extracurriculars')->count());
        $this->compare('acd_extracurricular_students (must stay untouched)', 0, DB::table('acd_extracurricular_students')->count());
    }
}
