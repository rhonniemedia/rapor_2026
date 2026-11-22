<?php

namespace App\Console\Commands;

use App\Support\Rapor\LegacyCrypt;
use App\Traits\HasBlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * rapor:copy - copies the legacy report card data (staging tables old_*) into the shared database.
 *
 * Prerequisite: `php artisan rapor:map` (WITHOUT --dry-run) has filled rapor_migration_maps.
 *
 * Write scope:
 * - Default steps (master, structure, class_overrides, scores, attendance, extracurriculars) write ONLY to
 *   rapor_* tables (extracurriculars live in rapor_extracurriculars / _supervisors / _members).
 * - Shared steps (access, school) touch tables of the shared database and must be named explicitly in --steps:
 *     access -> INSERTS rows into user_app_roles for the rapor app only
 *     school -> FILLS the 8 new, still empty core_schools columns (never overwrites a value)
 * - Existing rows of PINTAR / SIMKA / core tables are never modified or deleted.
 *
 * Safety: --dry-run runs everything inside a transaction and rolls it back, so constraints are
 * exercised but nothing is stored. Rows of rapor-owned tables are keyed by deterministic UUIDs
 * (legacy ID, or a UUIDv5 of the legacy ID + semester type), so every step can be re-run safely.
 */
class RaporCopy extends Command
{
    use HasBlindIndex;

    protected $signature = 'rapor:copy
        {--steps= : Comma-separated steps. Default: master,structure,class_overrides,scores,attendance,extracurriculars. Shared steps (access,school) must be named explicitly}
        {--class-overrides= : CSV (nis,semester_code,class_name) for the class_overrides step}
        {--dry-run : Run inside a transaction and roll it back}
        {--max-rows=500 : Maximum CSV report rows per finding type (counts stay complete)}
        {--chunk=500 : Rows per upsert}
        {--force : Skip the backup confirmation}';

    protected $description = 'Copy the legacy report card data into the shared database (idempotent, supports --dry-run).';

    private const RELIGION_ALIAS = ['khonghucu' => 'konghucu'];
    private const OWN_STEPS = ['master', 'structure', 'class_overrides', 'scores', 'attendance', 'extracurriculars'];
    private const SHARED_STEPS = ['access', 'school'];
    private const ORDER = ['master', 'structure', 'class_overrides', 'scores', 'attendance', 'extracurriculars', 'access', 'school'];

    private bool $dry = false;
    private int $maxRows = 500;
    private int $chunk = 500;
    private string $prefix = 'old_';
    private LegacyCrypt $legacy;
    /** @var resource */
    private $fh;

    private array $counts = [];
    private array $written = [];
    private array $inserted = [];   // table => rows sent
    private array $maps = [];       // table => [old => new]
    private array $skipped = [];    // table => [old => true]

    private array $pivotSem = [];        // old pivot id => core semester id
    private array $pivotType = [];       // old pivot id => odd|even
    private array $pivotByYearType = []; // old academic year id => [type => old pivot id]
    private array $coreUserByStaff = []; // staff_id => core user id
    private array $subjectIds = [];      // old subject id => true
    private array $membership = [];      // student_id => [semester_id => [class_group_id]]
    private array $cgInfo = [];          // class_group_id => row
    private array $overrides = [];       // student_id => [semester_id => class_group_id]

    public function handle(): int
    {
        $steps = $this->resolveSteps();
        if ($steps === null) {
            return self::FAILURE;
        }

        $this->dry = (bool) $this->option('dry-run');
        $this->maxRows = max(1, (int) $this->option('max-rows'));
        $this->chunk = max(1, (int) $this->option('chunk'));
        $this->prefix = (string) config('rapor_migration.staging_prefix', 'old_');

        if (! $this->preflight($steps)) {
            return self::FAILURE;
        }

        $shared = array_values(array_intersect($steps, self::SHARED_STEPS));
        if (! $this->dry && ! $this->option('force')) {
            $text = 'Is the database backed up and do you want to write the steps: ' . implode(', ', $steps) . '?';
            if ($shared) {
                $text .= ' (This touches shared tables: ' . implode(', ', $shared) . '.)';
            }
            if (! $this->confirm($text)) {
                $this->warn('Cancelled.');

                return self::FAILURE;
            }
        }

        $this->legacy = new LegacyCrypt();

        $dir = storage_path('app/rapor-map');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/copy-' . date('Ymd-His') . ($this->dry ? '-dryrun' : '') . '.csv';
        $this->fh = fopen($path, 'w');
        $this->csv(['level', 'check', 'table', 'old_id', 'detail']);

        $this->info(($this->dry ? 'DRY-RUN (rolled back at the end)' : 'WRITE MODE') . ': ' . implode(', ', $steps));

        $this->loadMaps();
        $this->loadTime();
        $this->coreUserByStaff = DB::table('users')->whereNotNull('staff_id')->pluck('id', 'staff_id')->all();
        $this->subjectIds = $this->old('mata_pelajarans')->pluck('id')->flip()->all();

        DB::beginTransaction();
        try {
            foreach ($steps as $step) {
                $this->line("-> {$step}");
                match ($step) {
                    'master' => $this->stepMaster(),
                    'structure' => $this->stepStructure(),
                    'class_overrides' => $this->stepClassOverrides(),
                    'scores' => $this->stepScores(),
                    'attendance' => $this->stepAttendance(),
                    'extracurriculars' => $this->stepExtracurriculars(),
                    'access' => $this->stepAccess(),
                    'school' => $this->stepSchool(),
                };
            }

            $tableCounts = [];
            foreach (array_keys($this->inserted) as $table) {
                $tableCounts[] = [$table, $this->inserted[$table], DB::table($table)->count()];
            }

            $this->dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($this->fh);
            $this->error('Failed and rolled back: ' . $e->getMessage());

            return self::FAILURE;
        }

        fclose($this->fh);

        $this->newLine();
        $this->table(['Table', 'Rows sent', 'Rows in table (before rollback)'], $tableCounts);
        $this->printFindings();
        $this->newLine();
        $this->info($this->dry ? 'DRY-RUN finished: everything was rolled back.' : 'Committed.');
        $this->info("Report: {$path}");

        return self::SUCCESS;
    }

    // ========================================================================
    // Setup
    // ========================================================================

    private function resolveSteps(): ?array
    {
        $raw = (string) $this->option('steps');
        $steps = $raw === '' ? self::OWN_STEPS : array_values(array_filter(array_map('trim', explode(',', $raw))));

        $unknown = array_diff($steps, self::ORDER);
        if ($unknown) {
            $this->error('Unknown step(s): ' . implode(', ', $unknown) . '. Valid: ' . implode(', ', self::ORDER));

            return null;
        }

        return array_values(array_intersect(self::ORDER, $steps));
    }

    private function preflight(array $steps): bool
    {
        $ok = true;

        foreach (['rapor_migration_maps', 'rapor_scores', 'rapor_class_group_subjects', 'rapor_class_overrides', 'rapor_extracurriculars', 'rapor_extracurricular_supervisors', 'rapor_extracurricular_members'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Table {$table} does not exist. Run the schema migrations first.");
                $ok = false;
            }
        }

        foreach (['tahun_ajarans', 'tahun_ajaran_semesters', 'jurusans', 'pelajars', 'users', 'rombels@odd', 'rombels@even'] as $mapTable) {
            if (! DB::table('rapor_migration_maps')->where('table_name', $mapTable)->whereNotNull('new_id')->exists()) {
                $this->error("rapor_migration_maps has no '{$mapTable}' mappings. Run 'php artisan rapor:map' (without --dry-run) first.");
                $ok = false;
            }
        }

        if (in_array('class_overrides', $steps, true)) {
            $file = $this->option('class-overrides');
            if (! $file || ! is_readable((string) $file)) {
                $this->error('The class_overrides step needs --class-overrides=file.csv (columns: nis,semester_code,class_name).');
                $ok = false;
            }
        }

        if (in_array('access', $steps, true)) {
            if (! DB::table('core_apps')->where('code', 'rapor')->exists()) {
                $this->error("core_apps has no row with code 'rapor'.");
                $ok = false;
            }
            if (DB::table('user_roles')->whereIn('name', ['superadmin', 'admin', 'user'])->count() < 3) {
                $this->error('user_roles must contain superadmin, admin, and user.');
                $ok = false;
            }
        }

        return $ok;
    }

    private function loadMaps(): void
    {
        foreach (DB::table('rapor_migration_maps')->get() as $row) {
            if ($row->note === 'skipped' || $row->new_id === null) {
                $this->skipped[$row->table_name][$row->old_id] = true;
            } else {
                $this->maps[$row->table_name][$row->old_id] = $row->new_id;
            }
        }
    }

    private function loadTime(): void
    {
        $semesterType = DB::table('core_semesters')->pluck('type', 'id')->all();
        foreach ($this->old('tahun_ajaran_semesters')->get() as $pivot) {
            $semesterId = $this->map('tahun_ajaran_semesters', $pivot->id);
            $type = $semesterId ? ($semesterType[$semesterId] ?? null) : null;
            if (! $type) {
                continue;
            }
            $this->pivotSem[$pivot->id] = $semesterId;
            $this->pivotType[$pivot->id] = $type;
            $this->pivotByYearType[$pivot->tahun_ajaran_id][$type] = $pivot->id;
        }
    }

    private function loadMemberships(): void
    {
        if ($this->cgInfo) {
            return;
        }
        foreach (DB::table('acd_class_groups')->get(['id', 'semester_id', 'homeroom_teacher_id', 'name']) as $group) {
            $this->cgInfo[$group->id] = $group;
        }
        foreach (DB::table('acd_class_group_students')->get(['student_id', 'class_group_id']) as $member) {
            $semesterId = $this->cgInfo[$member->class_group_id]->semester_id ?? null;
            if ($semesterId) {
                $this->membership[$member->student_id][$semesterId][] = $member->class_group_id;
            }
        }
        foreach (DB::table('rapor_class_overrides')->get(['student_id', 'semester_id', 'class_group_id']) as $override) {
            $this->overrides[$override->student_id][$override->semester_id] = $override->class_group_id;
        }
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function old(string $table)
    {
        return DB::table($this->prefix . $table);
    }

    private function map(string $table, ?string $old): ?string
    {
        return $old === null ? null : ($this->maps[$table][$old] ?? null);
    }

    /** Core user id (users.id) of a legacy user, via staff_data. */
    private function coreUser(?string $oldUserId): ?string
    {
        $staffId = $this->map('users', $oldUserId);

        return $staffId ? ($this->coreUserByStaff[$staffId] ?? null) : null;
    }

    private function uuid5(string $name): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'rapor:' . $name)->toString();
    }

    private function csv(array $row): void
    {
        fputcsv($this->fh, $row, ',', '"', '\\');
    }

    private function flag(string $level, string $check, string $table, ?string $id, string $detail = ''): void
    {
        $key = $level . '|' . $check;
        $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;

        $this->written[$check] = $this->written[$check] ?? 0;
        if ($this->written[$check] < $this->maxRows) {
            $this->csv([$level, $check, $table, $id, $detail]);
            $this->written[$check]++;
        }
    }

    /** Upsert by primary key in chunks. */
    private function write(string $table, array $rows, array $updateColumns): void
    {
        $this->inserted[$table] = ($this->inserted[$table] ?? 0) + count($rows);
        foreach (array_chunk($rows, $this->chunk) as $chunk) {
            DB::table($table)->upsert($chunk, ['id'], $updateColumns);
        }
    }

    /** Insert rows that do not exist yet (used for shared tables: never updates an existing row). */
    private function insertNew(string $table, array $rows): void
    {
        $this->inserted[$table] = ($this->inserted[$table] ?? 0) + count($rows);
        foreach (array_chunk($rows, $this->chunk) as $chunk) {
            DB::table($table)->insertOrIgnore($chunk);
        }
    }

    private function activeStatus(?string $status): string
    {
        return $status === 'aktif' ? 'active' : 'archived';
    }

    // ========================================================================
    // master: tables without dependency on class groups
    // ========================================================================

    private function stepMaster(): void
    {
        $rows = [];
        foreach ($this->old('mata_pelajaran_kelompoks')->get() as $r) {
            $rows[] = ['id' => $r->id, 'name' => $r->nama, 'code' => $r->kode, 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_subject_groups', $rows, ['name', 'code', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('mata_pelajarans')->get() as $r) {
            $religion = null;
            if (trim((string) $r->agama_terkait) !== '') {
                $value = mb_strtolower(trim($r->agama_terkait));
                $religion = $this->canonicalReligion(self::RELIGION_ALIAS[$value] ?? $value);
                if ($religion === null) {
                    $this->flag('warn', 'subject_religion_out_of_enum', 'mata_pelajarans', $r->id, $value);
                }
            }
            $rows[] = [
                'id' => $r->id, 'name' => $r->nama, 'code' => $r->kode,
                'is_religion_subject' => (int) $r->is_mapel_agama,
                'religion' => $religion,
                'religion_hash' => $religion !== null ? $this->blindIndexHash($religion) : null,
                'status' => $this->activeStatus($r->status),
                'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
            ];
        }
        $this->write('rapor_subjects', $rows, ['name', 'code', 'is_religion_subject', 'religion', 'religion_hash', 'status', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('kurikulums')->get() as $r) {
            $rows[] = ['id' => $r->id, 'name' => $r->nama, 'code' => $r->kode, 'description' => $r->deskripsi, 'status' => $this->activeStatus($r->status), 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_curricula', $rows, ['name', 'code', 'description', 'status', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('tahun_ajaran_kurikulums')->get() as $r) {
            $yearId = $this->map('tahun_ajarans', $r->tahun_ajaran_id);
            if ($yearId === null) {
                $this->flag('warn', 'curriculum_year_no_target', 'tahun_ajaran_kurikulums', $r->id);

                continue;
            }
            $rows[] = ['id' => $r->id, 'curriculum_id' => $r->kurikulum_id, 'academic_year_id' => $yearId, 'status' => $r->status === 'aktif' ? 'active' : 'inactive', 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_academic_year_curricula', $rows, ['curriculum_id', 'academic_year_id', 'status', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('kurikulum_mata_pelajarans')->get() as $r) {
            $rows[] = ['id' => $r->id, 'curriculum_id' => $r->kurikulum_id, 'subject_id' => $r->mata_pelajaran_id, 'subject_group_id' => $r->kelompok_id, 'grade_level' => $r->tingkat, 'sort_order' => $r->urutan, 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_curriculum_subjects', $rows, ['curriculum_id', 'subject_id', 'subject_group_id', 'grade_level', 'sort_order', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('jurusan_mata_pelajarans')->get() as $r) {
            $concentrationId = $this->map('jurusans', $r->jurusan_id);
            if ($concentrationId === null) {
                $this->flag('warn', 'concentration_subject_no_target', 'jurusan_mata_pelajarans', $r->id);

                continue;
            }
            $rows[] = ['id' => $r->id, 'concentration_id' => $concentrationId, 'subject_id' => $r->mata_pelajaran_id, 'curriculum_id' => $r->kurikulum_id, 'type' => $r->status === 'pilihan' ? 'elective' : 'required', 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_concentration_subjects', $rows, ['concentration_id', 'subject_id', 'curriculum_id', 'type', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('pengaturans')->get() as $r) {
            $semesterId = $this->map('tahun_ajaran_semesters', $r->tahun_ajaran_semester_id);
            if ($semesterId === null) {
                $this->flag('warn', 'setting_semester_no_target', 'pengaturans', $r->id);

                continue;
            }
            $headmaster = $this->map('users', $r->kepala_sekolah_id);
            if ($headmaster === null) {
                $this->flag('warn', 'setting_headmaster_no_staff', 'pengaturans', $r->id);
            }
            $config = $r->konfigurasi_tampilan;
            if ($config !== null) {
                json_decode($config);
                $config = json_last_error() === JSON_ERROR_NONE ? $config : null;
            }
            $rows[] = ['id' => $r->id, 'semester_id' => $semesterId, 'headmaster_staff_id' => $headmaster, 'report_date' => $r->tanggal_rapor, 'display_config' => $config, 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_settings', $rows, ['semester_id', 'headmaster_staff_id', 'report_date', 'display_config', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('template_nilai_capaians')->get() as $r) {
            $semesterId = $this->map('tahun_ajaran_semesters', $r->tahun_ajaran_semester_id);
            if ($semesterId === null) {
                $this->flag('warn', 'template_score_semester_no_target', 'template_nilai_capaians', $r->id);

                continue;
            }
            $subjectId = $r->mata_pelajaran_id !== null && isset($this->subjectIds[$r->mata_pelajaran_id]) ? $r->mata_pelajaran_id : null;
            $rows[] = [
                'id' => $r->id, 'semester_id' => $semesterId, 'subject_id' => $subjectId, 'grade_level' => $r->tingkat,
                'min_range' => $r->rentang_min, 'max_range' => $r->rentang_max, 'predicate' => $r->predikat, 'description' => $r->deskripsi,
                'is_active' => (int) $r->aktif, 'created_by' => $this->coreUser($r->created_by), 'updated_by' => $this->coreUser($r->updated_by),
                'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
            ];
        }
        $this->write('rapor_score_achievement_templates', $rows, ['semester_id', 'subject_id', 'grade_level', 'min_range', 'max_range', 'predicate', 'description', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('template_kokurikuler_capaians')->get() as $r) {
            $semesterId = $this->map('tahun_ajaran_semesters', $r->tahun_ajaran_semester_id);
            if ($semesterId === null) {
                $this->flag('warn', 'template_cocurricular_semester_no_target', 'template_kokurikuler_capaians', $r->id);

                continue;
            }
            $rows[] = [
                'id' => $r->id, 'semester_id' => $semesterId, 'subdimension' => $r->subdimensi, 'grade_level' => $r->tingkat,
                'predicate' => $r->predikat, 'description' => $r->deskripsi, 'is_active' => (int) $r->aktif,
                'created_by' => $this->coreUser($r->created_by), 'updated_by' => $this->coreUser($r->updated_by),
                'created_at' => $r->created_at, 'updated_at' => $r->updated_at,
            ];
        }
        $this->write('rapor_cocurricular_achievement_templates', $rows, ['semester_id', 'subdimension', 'grade_level', 'predicate', 'description', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at']);

        // Religion teachers: specialization encrypted WITHOUT serialize (shared key), SHA-256 hash (staff style).
        $skippedUsers = $this->skipped['users'] ?? [];
        $rows = [];
        foreach ($this->old('users')->get() as $u) {
            $staffId = isset($skippedUsers[$u->id]) ? null : $this->map('users', $u->id);
            if ($staffId === null || (! (int) $u->is_guru_agama && empty($u->spesialisasi_agama))) {
                continue;
            }
            $specialization = null;
            if (! empty($u->spesialisasi_agama)) {
                try {
                    $value = mb_strtolower(trim((string) $this->legacy->serialized($u->spesialisasi_agama)));
                    $specialization = $this->canonicalReligion(self::RELIGION_ALIAS[$value] ?? $value);
                } catch (\Throwable $e) {
                    $specialization = null;
                }
                if ($specialization === null) {
                    $this->flag('warn', 'specialization_unreadable_or_out_of_enum', 'users', $u->id);
                }
            }
            $rows[] = [
                'id' => $this->uuid5('rt:' . $u->id), 'staff_id' => $staffId, 'is_religion_teacher' => (int) $u->is_guru_agama,
                'specialization_encrypted' => $specialization !== null ? Crypt::encryptString($specialization) : null,
                'specialization_hash' => $specialization !== null ? hash('sha256', $specialization) : null,
                'created_at' => $u->created_at, 'updated_at' => $u->updated_at,
            ];
        }
        $this->write('rapor_religion_teachers', $rows, ['staff_id', 'is_religion_teacher', 'specialization_encrypted', 'specialization_hash', 'created_at', 'updated_at']);
    }

    // ========================================================================
    // structure: subjects and teachers per class group per semester
    // ========================================================================

    private function stepStructure(): void
    {
        $p = $this->prefix;
        $rombels = $this->old('rombels')->get()->keyBy('id');

        // Curriculum lookups (initial templates only: used for group, order, and required/elective).
        $yearCurriculum = $this->old('tahun_ajaran_kurikulums')->pluck('kurikulum_id', 'id')->all();
        $kms = [];
        foreach ($this->old('kurikulum_mata_pelajarans')->get() as $r) {
            $kms[$r->kurikulum_id][$r->mata_pelajaran_id][$r->tingkat] = $r;
        }
        $jm = [];
        foreach ($this->old('jurusan_mata_pelajarans')->get() as $r) {
            $jm[$r->jurusan_id][$r->mata_pelajaran_id][$r->kurikulum_id ?? ''] = $r;
        }

        // Semesters in which a subject of a study group has scores; teacher recorded on scores.
        $scoredTypes = [];
        foreach (DB::table($p . 'nilais as n')->whereNull('n.deleted_at')->select('n.rombel_pengajar_id', 'n.tahun_ajaran_semester_id')->distinct()->get() as $r) {
            $type = $this->pivotType[$r->tahun_ajaran_semester_id] ?? null;
            if ($type) {
                $scoredTypes[$r->rombel_pengajar_id][$type] = true;
            }
        }
        $teacherFromScores = [];
        foreach (DB::table($p . 'nilais')->whereNull('deleted_at')->whereNotNull('guru_id')
            ->select('rombel_pengajar_id', 'tahun_ajaran_semester_id', DB::raw('MIN(guru_id) g'))
            ->groupBy('rombel_pengajar_id', 'tahun_ajaran_semester_id')->get() as $r) {
            $teacherFromScores[$r->rombel_pengajar_id . '|' . $r->tahun_ajaran_semester_id] = $r->g;
        }

        // Reference curriculum per class group.
        $rows = [];
        foreach ($rombels as $rombel) {
            if ($rombel->tahun_ajaran_kurikulum_id === null) {
                continue;
            }
            foreach (['odd', 'even'] as $type) {
                $classGroupId = $this->map('rombels@' . $type, $rombel->id);
                if ($classGroupId) {
                    $rows[] = ['id' => $this->uuid5("cgc:{$rombel->id}:{$type}"), 'class_group_id' => $classGroupId, 'academic_year_curriculum_id' => $rombel->tahun_ajaran_kurikulum_id, 'created_at' => now(), 'updated_at' => now()];
                }
            }
        }
        $this->write('rapor_class_group_curricula', $rows, ['class_group_id', 'academic_year_curriculum_id', 'updated_at']);

        $subjectRows = [];
        $teacherRows = [];
        foreach ($this->old('rombel_pengajars')->get() as $rp) {
            $rombel = $rombels[$rp->rombel_id] ?? null;
            if ($rombel === null || ! isset($this->subjectIds[$rp->mata_pelajaran_id])) {
                $this->flag('warn', 'assignment_orphan', 'rombel_pengajars', $rp->id);

                continue;
            }

            // A subject scored in only one semester is valid in that semester only; otherwise in both.
            $types = isset($scoredTypes[$rp->id]) ? array_keys($scoredTypes[$rp->id]) : ['odd', 'even'];

            $curriculumId = $yearCurriculum[$rombel->tahun_ajaran_kurikulum_id] ?? null;
            $kmsRow = $curriculumId ? ($kms[$curriculumId][$rp->mata_pelajaran_id][$rombel->tingkat] ?? null) : null;
            $jmRows = $jm[$rombel->jurusan_id][$rp->mata_pelajaran_id] ?? [];
            $jmRow = ($curriculumId ? ($jmRows[$curriculumId] ?? null) : null) ?? ($jmRows[''] ?? null);

            foreach ($types as $type) {
                $classGroupId = $this->map('rombels@' . $type, $rombel->id);
                if ($classGroupId === null) {
                    $this->flag('warn', 'assignment_class_group_no_target', 'rombel_pengajars', $rp->id, $type);

                    continue;
                }

                $subjectRows[] = [
                    'id' => $this->uuid5("cgs:{$rp->id}:{$type}"), 'class_group_id' => $classGroupId, 'subject_id' => $rp->mata_pelajaran_id,
                    'subject_group_id' => $kmsRow->kelompok_id ?? null, 'sort_order' => $kmsRow->urutan ?? null,
                    'type' => ($jmRow->status ?? 'wajib') === 'pilihan' ? 'elective' : 'required',
                    'created_at' => $rp->created_at, 'updated_at' => $rp->updated_at,
                ];

                // Teacher: from the scores of that semester; otherwise the legacy assignment (reported as a guess).
                $pivotId = $this->pivotByYearType[$rombel->tahun_ajaran_id][$type] ?? null;
                $teacher = $pivotId ? ($teacherFromScores[$rp->id . '|' . $pivotId] ?? null) : null;
                if ($teacher === null && $rp->guru_id !== null) {
                    $teacher = $rp->guru_id;
                    $this->flag('info', 'teacher_guessed_from_assignment', 'rombel_pengajars', $rp->id, $type);
                }
                $staffId = $this->map('users', $teacher);
                if ($teacher !== null && $staffId === null) {
                    $this->flag('warn', 'teacher_no_staff_match', 'rombel_pengajars', $rp->id, $type);
                }

                $teacherRows[] = [
                    'id' => $this->uuid5("ta:{$rp->id}:{$type}"), 'class_group_id' => $classGroupId, 'subject_id' => $rp->mata_pelajaran_id,
                    'staff_id' => $staffId, 'created_at' => $rp->created_at, 'updated_at' => $rp->updated_at,
                ];
            }
        }
        $this->write('rapor_class_group_subjects', $subjectRows, ['class_group_id', 'subject_id', 'subject_group_id', 'sort_order', 'type', 'created_at', 'updated_at']);
        $this->write('rapor_teaching_assignments', $teacherRows, ['class_group_id', 'subject_id', 'staff_id', 'created_at', 'updated_at']);
    }

    // ========================================================================
    // class_overrides: effective class of a student per semester (shared membership is never changed)
    // ========================================================================

    private function stepClassOverrides(): void
    {
        $handle = fopen((string) $this->option('class-overrides'), 'r');
        $header = array_map(fn($name) => strtolower(trim((string) $name)), fgetcsv($handle, 0, ',', '"', '\\') ?: []);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count(array_filter($line, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = array_combine($header, array_pad($line, count($header), ''));
            $nis = trim($row['nis'] ?? '');
            $code = trim($row['semester_code'] ?? '');
            $className = trim($row['class_name'] ?? '');

            $students = DB::table('acd_students')->where('nis', $nis)->pluck('id');
            $semester = DB::table('core_semesters')->where('code', $code)->first();
            $groups = $semester ? DB::table('acd_class_groups')->where('semester_id', $semester->id)->where('name', $className)->pluck('id') : collect();

            if ($students->count() !== 1 || ! $semester || $groups->count() !== 1) {
                $this->flag('warn', 'class_override_unresolved', 'class-overrides', $nis, "semester={$code} class={$className}");

                continue;
            }

            $rows[] = [
                'id' => $this->uuid5("co:{$students->first()}:{$semester->id}"), 'student_id' => $students->first(), 'semester_id' => $semester->id,
                'class_group_id' => $groups->first(), 'reason' => 'legacy_roster', 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        fclose($handle);

        $this->write('rapor_class_overrides', $rows, ['class_group_id', 'reason', 'updated_at']);
    }

    // ========================================================================
    // scores
    // ========================================================================

    private function stepScores(): void
    {
        $assignmentIds = DB::table('rapor_teaching_assignments')->pluck('id')->flip()->all();
        $rows = [];
        foreach ($this->old('nilais')->whereNull('deleted_at')->orderBy('id')->cursor() as $n) {
            $studentId = $this->map('pelajars', $n->pelajar_id);
            $semesterId = $this->pivotSem[$n->tahun_ajaran_semester_id] ?? null;
            $type = $this->pivotType[$n->tahun_ajaran_semester_id] ?? null;

            if ($studentId === null || $semesterId === null || ! isset($this->subjectIds[$n->mata_pelajaran_id])) {
                $this->flag('warn', 'score_skipped', 'nilais', $n->id, $studentId === null ? 'student' : ($semesterId === null ? 'semester' : 'subject'));

                continue;
            }

            $assignmentId = $this->uuid5("ta:{$n->rombel_pengajar_id}:{$type}");
            $rows[] = [
                'id' => $n->id, 'student_id' => $studentId, 'subject_id' => $n->mata_pelajaran_id,
                'teaching_assignment_id' => isset($assignmentIds[$assignmentId]) ? $assignmentId : null,
                'semester_id' => $semesterId, 'staff_id' => $this->map('users', $n->guru_id),
                'score' => $n->nilai_angka, 'predicate' => $n->predikat, 'competency_description' => $n->capaian_kompetensi,
                'created_by' => $this->coreUser($n->created_by), 'updated_by' => $this->coreUser($n->updated_by),
                'created_at' => $n->created_at, 'updated_at' => $n->updated_at,
            ];
        }
        $this->write('rapor_scores', $rows, ['student_id', 'subject_id', 'teaching_assignment_id', 'semester_id', 'staff_id', 'score', 'predicate', 'competency_description', 'created_by', 'updated_by', 'created_at', 'updated_at']);
    }

    // ========================================================================
    // attendance, homeroom notes, cocurriculars
    // ========================================================================

    /** Effective class of a student in a semester: override first, then a single shared membership. */
    private function effectiveClass(string $studentId, string $semesterId, ?string $teacherStaffId): ?string
    {
        if (isset($this->overrides[$studentId][$semesterId])) {
            return $this->overrides[$studentId][$semesterId];
        }

        $candidates = array_values(array_unique($this->membership[$studentId][$semesterId] ?? []));
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        if (count($candidates) > 1 && $teacherStaffId !== null) {
            $hits = array_values(array_filter($candidates, fn($id) => ($this->cgInfo[$id]->homeroom_teacher_id ?? null) === $teacherStaffId));

            return count($hits) === 1 ? $hits[0] : null;
        }

        return null;
    }

    private function stepAttendance(): void
    {
        $this->loadMemberships();

        $rows = [];
        foreach ($this->old('kehadirans')->orderBy('id')->cursor() as $k) {
            $studentId = $this->map('pelajars', $k->pelajar_id);
            $type = $this->pivotType[$k->tahun_ajaran_semester_id] ?? null;
            $classGroupId = $type ? $this->map('rombels@' . $type, $k->rombel_id) : null;

            if ($studentId === null || $classGroupId === null) {
                $this->flag('warn', 'attendance_skipped', 'kehadirans', $k->id);

                continue;
            }
            $rows[] = ['id' => $k->id, 'student_id' => $studentId, 'class_group_id' => $classGroupId, 'sick_count' => $k->jumlah_sakit, 'permission_count' => $k->jumlah_izin, 'unexcused_count' => $k->jumlah_tanpa_keterangan, 'created_at' => $k->created_at, 'updated_at' => $k->updated_at];
        }
        $this->write('rapor_attendances', $rows, ['student_id', 'class_group_id', 'sick_count', 'permission_count', 'unexcused_count', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('catatan_wali_kelas')->orderBy('id')->cursor() as $r) {
            $studentId = $this->map('pelajars', $r->pelajar_id);
            $semesterId = $this->pivotSem[$r->tahun_ajaran_semester_id] ?? null;
            $classGroupId = ($studentId && $semesterId) ? $this->effectiveClass($studentId, $semesterId, $this->map('users', $r->guru_id)) : null;

            if ($classGroupId === null) {
                $this->flag('warn', 'homeroom_note_skipped', 'catatan_wali_kelas', $r->id);

                continue;
            }
            $rows[] = ['id' => $r->id, 'student_id' => $studentId, 'class_group_id' => $classGroupId, 'staff_id' => $this->map('users', $r->guru_id), 'note' => $r->catatan, 'entered_at' => $r->tanggal_input, 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_homeroom_notes', $rows, ['student_id', 'class_group_id', 'staff_id', 'note', 'entered_at', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('kokurikulers')->orderBy('id')->cursor() as $r) {
            $studentId = $this->map('pelajars', $r->pelajar_id);
            $semesterId = $this->pivotSem[$r->tahun_ajaran_semester_id] ?? null;
            $classGroupId = ($studentId && $semesterId) ? $this->effectiveClass($studentId, $semesterId, $this->map('users', $r->guru_id)) : null;

            if ($classGroupId === null) {
                $this->flag('warn', 'cocurricular_skipped', 'kokurikulers', $r->id);

                continue;
            }
            $rows[] = ['id' => $r->id, 'student_id' => $studentId, 'class_group_id' => $classGroupId, 'staff_id' => $this->map('users', $r->guru_id), 'predicate' => $r->predikat, 'achievement' => $r->capaian, 'entered_date' => $r->tanggal_input, 'created_at' => $r->created_at, 'updated_at' => $r->updated_at];
        }
        $this->write('rapor_cocurriculars', $rows, ['student_id', 'class_group_id', 'staff_id', 'predicate', 'achievement', 'entered_date', 'created_at', 'updated_at']);
    }

    // ========================================================================
    // extracurriculars: global extracurriculars, supervisor per semester, members per semester (rapor_* only)
    // ========================================================================

    private function stepExtracurriculars(): void
    {
        $extracurriculars = $this->old('ekstrakurikulers')->get();
        $known = $extracurriculars->pluck('id')->flip()->all();

        $rows = [];
        foreach ($extracurriculars as $e) {
            $rows[] = ['id' => $e->id, 'name' => $e->nama, 'description' => $e->deskripsi, 'status' => $this->activeStatus($e->status), 'created_at' => $e->created_at, 'updated_at' => $e->updated_at];
        }
        $this->write('rapor_extracurriculars', $rows, ['name', 'description', 'status', 'created_at', 'updated_at']);

        // The legacy data has ONE supervisor per extracurricular (no semester): it is copied to every mapped semester.
        $semesterIds = array_values(array_unique(array_values($this->pivotSem)));
        $rows = [];
        foreach ($extracurriculars as $e) {
            $staffId = $this->map('users', $e->pembina_id);
            if ($staffId === null) {
                $this->flag('warn', 'extracurricular_supervisor_no_staff', 'ekstrakurikulers', $e->id);
            }
            foreach ($semesterIds as $semesterId) {
                $rows[] = ['id' => $this->uuid5("es:{$e->id}:{$semesterId}"), 'extracurricular_id' => $e->id, 'semester_id' => $semesterId, 'staff_id' => $staffId, 'created_at' => $e->created_at, 'updated_at' => $e->updated_at];
            }
        }
        $this->write('rapor_extracurricular_supervisors', $rows, ['extracurricular_id', 'semester_id', 'staff_id', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('ekskul_pelajars')->orderBy('id')->cursor() as $m) {
            $semesterId = $this->pivotSem[$m->tahun_ajaran_semester_id] ?? null;
            $studentId = $this->map('pelajars', $m->pelajar_id);
            if ($semesterId === null || $studentId === null || ! isset($known[$m->ekstrakurikuler_id])) {
                $this->flag('warn', 'extracurricular_member_skipped', 'ekskul_pelajars', $m->id);

                continue;
            }
            $rows[] = [
                'id' => $m->id, 'semester_id' => $semesterId, 'extracurricular_id' => $m->ekstrakurikuler_id, 'student_id' => $studentId,
                'score' => $m->nilai, 'description' => $m->deskripsi,
                'created_by' => $this->coreUser($m->created_by), 'updated_by' => $this->coreUser($m->updated_by),
                'created_at' => $m->created_at, 'updated_at' => $m->updated_at,
            ];
        }
        $this->write('rapor_extracurricular_members', $rows, ['semester_id', 'extracurricular_id', 'student_id', 'score', 'description', 'created_by', 'updated_by', 'created_at', 'updated_at']);

        $rows = [];
        foreach ($this->old('template_ekstrakurikuler_deskripsis')->get() as $t) {
            $semesterId = $this->pivotSem[$t->tahun_ajaran_semester_id] ?? null;
            if ($semesterId === null || ($t->ekstrakurikuler_id !== null && ! isset($known[$t->ekstrakurikuler_id]))) {
                $this->flag('warn', 'template_extracurricular_skipped', 'template_ekstrakurikuler_deskripsis', $t->id);

                continue;
            }
            $rows[] = [
                'id' => $t->id, 'semester_id' => $semesterId, 'extracurricular_id' => $t->ekstrakurikuler_id,
                'predicate' => $t->predikat, 'description' => $t->deskripsi, 'use_placeholder' => (int) $t->gunakan_placeholder, 'is_active' => (int) $t->aktif,
                'created_by' => $this->coreUser($t->created_by), 'updated_by' => $this->coreUser($t->updated_by),
                'created_at' => $t->created_at, 'updated_at' => $t->updated_at,
            ];
        }
        $this->write('rapor_extracurricular_description_templates', $rows, ['semester_id', 'extracurricular_id', 'predicate', 'description', 'use_placeholder', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at']);
    }

    // ========================================================================
    // SHARED STEP access: INSERTS new user_app_roles rows for the rapor app only
    // ========================================================================

    private function stepAccess(): void
    {
        $appId = DB::table('core_apps')->where('code', 'rapor')->value('id');
        $roleIds = DB::table('user_roles')->whereIn('name', ['superadmin', 'admin', 'user'])->pluck('id', 'name')->all();
        $skippedUsers = $this->skipped['users'] ?? [];

        $roleNames = [];
        $roleRows = $this->old('model_has_roles as mr')->join($this->prefix . 'roles as r', 'r.id', '=', 'mr.role_id')->get(['mr.model_id', 'r.name']);
        foreach ($roleRows as $r) {
            $roleNames[$r->model_id][] = mb_strtolower($r->name);
        }

        $existing = [];
        foreach (DB::table('user_app_roles')->where('app_id', $appId)->get(['user_id', 'role_id']) as $r) {
            $existing[$r->user_id . '|' . $r->role_id] = true;
        }

        $rows = [];
        foreach ($this->old('users')->get() as $u) {
            if (isset($skippedUsers[$u->id])) {
                continue;
            }
            if ($u->status !== 'aktif') {
                $this->flag('info', 'access_skipped_inactive_user', 'users', $u->id);

                continue;
            }
            $userId = $this->coreUser($u->id);
            if ($userId === null) {
                $this->flag('warn', 'access_no_core_user', 'users', $u->id, 'users are not created by this command');

                continue;
            }

            $names = $roleNames[$u->id] ?? [];
            $role = in_array('superadmin', $names, true) ? 'superadmin' : (in_array('admin', $names, true) ? 'admin' : 'user');
            if (isset($existing[$userId . '|' . $roleIds[$role]])) {
                continue;
            }
            $rows[] = ['id' => (string) Str::uuid(), 'user_id' => $userId, 'app_id' => $appId, 'role_id' => $roleIds[$role], 'created_at' => now(), 'updated_at' => now()];
        }
        $this->insertNew('user_app_roles', $rows);
    }

    // ========================================================================
    // SHARED STEP school: fills EMPTY core_schools columns only (never overwrites)
    // ========================================================================

    private function stepSchool(): void
    {
        $school = DB::table('core_schools')->first();
        $old = $this->old('data_sekolahs')->first();
        if (! $school || ! $old) {
            throw new \RuntimeException('core_schools or data_sekolahs has no row.');
        }
        if ((string) $school->npsn !== (string) $old->npsn) {
            throw new \RuntimeException('NPSN of core_schools differs from data_sekolahs; refusing to touch core_schools.');
        }

        $columns = [
            'nis' => 'nis', 'nds' => 'nds', 'education_level' => 'jenjang_pendidikan', 'accreditation_status' => 'status_akreditasi',
            'accreditation_year' => 'tahun_akreditasi', 'operational_permit_decree' => 'sk_izin_operasional',
            'operational_permit_date' => 'tanggal_sk_izin_operasional', 'local_government_logo_path' => 'logo_pemda_path',
        ];

        $update = [];
        foreach ($columns as $target => $source) {
            $value = $old->{$source};
            if ($value === null || $value === '') {
                continue;
            }
            $current = $school->{$target} ?? null;
            if ($current === null || $current === '') {
                $update[$target] = $value;
            } elseif ((string) $current !== (string) $value) {
                $this->flag('info', 'school_value_differs_not_overwritten', 'core_schools', $school->id, $target);
            }
        }

        if ($update) {
            DB::table('core_schools')->where('id', $school->id)->update($update);
        }
        $this->flag('info', 'school_columns_filled', 'core_schools', $school->id, $update ? implode(',', array_keys($update)) : 'none');
    }

    // ========================================================================

    private function printFindings(): void
    {
        $this->newLine();
        $this->info('FINDINGS');
        $rows = [];
        foreach ($this->counts as $key => $n) {
            [$level, $check] = explode('|', $key, 2);
            $rows[] = [strtoupper($level), $check, $n];
        }
        usort($rows, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $this->table(['Level', 'Finding', 'Count'], $rows ?: [['-', 'no findings', 0]]);
    }
}
