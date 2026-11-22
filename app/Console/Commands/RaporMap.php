<?php

namespace App\Console\Commands;

use App\Enums\Student\Profession;
use App\Support\Rapor\LegacyCrypt;
use App\Traits\HasBlindIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * rapor:map - mapping step + mismatch report (handoff part 6).
 *
 * - Reads the staging tables old_* (import of the legacy report card database) and the target tables.
 *   It does NOT copy report card data.
 * - --dry-run: report only (CSV + on-screen summary). Without it: writes rapor_migration_maps.
 * - Never guesses ambiguous data: everything goes into the report. Manual decisions via --overrides=file.csv.
 * - Idempotent: mappings are written with upsert (table_name, old_id).
 *
 * Mapping table names (rapor_migration_maps.table_name):
 *   tahun_ajarans, tahun_ajaran_semesters (pivot ID -> core_semesters), jurusans, pelajars (-> acd_students),
 *   users (-> staff_data), rombels@odd / rombels@even (-> acd_class_groups of the odd/even semester).
 */
class RaporMap extends Command
{
    use HasBlindIndex;

    protected $signature = 'rapor:map
        {--dry-run : Report only, do not write any mapping}
        {--overrides= : CSV with manual decisions (columns: table_name,old_id,new_id)}
        {--max-rows=500 : Maximum CSV rows per finding type (counts stay complete)}
        {--force : Skip the backup confirmation}';

    protected $description = 'Map legacy report card data to the shared tables and report mismatches (idempotent, supports --dry-run).';

    /** The only known religion alias (seen in mata_pelajarans). Everything else is reported. */
    private const RELIGION_ALIAS = ['khonghucu' => 'konghucu'];

    private bool $dry = false;
    private int $maxRows = 500;
    private string $prefix = 'old_';
    private LegacyCrypt $legacy;
    /** @var resource */
    private $fh;

    private array $counts = [];     // "level|check" => n
    private array $written = [];    // check => CSV rows written
    private array $stats = [];      // label => [result => n]
    private array $maps = [];       // table => [old => new]
    private array $mapNotes = [];   // table => [old => note]
    private array $manual = [];     // table => [old => new]
    private array $skipped = [];    // table => [old => true] (excluded by decision, e.g. a user to be dropped)
    private array $acknowledged = []; // 'class_change@odd|even' => [old pelajar id => true] (accepted class differences)
    private array $refUsers = [];   // old user id => [source => n]

    private array $pivot = [];      // pivotId => [year, type, sem]
    private array $yearPivots = []; // oldYearId => [type => pivotId]
    private array $cgInfo = [];     // class_group_id => row
    private array $cgBySemester = [];
    private array $membership = []; // student_id => [semester_id => [class_group_id]]
    private array $cgMembers = [];  // class_group_id => [student_id => true]
    private array $studentReligion = []; // old pelajar id => enum value
    private array $subjects = [];   // old subject id => ['religion' => ?string, 'is_religion' => bool]
    private array $rombelYear = []; // old rombel id => old academic year id
    private array $scoreMismatchStudents = []; // old pelajar id => true

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->maxRows = max(1, (int) $this->option('max-rows'));
        $this->prefix = (string) config('rapor_migration.staging_prefix', 'old_');

        if (! $this->preflight()) {
            return self::FAILURE;
        }

        if (! $this->dry && ! $this->option('force')
            && ! $this->confirm('Is the database backed up and do you want to write the mappings?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $this->legacy = new LegacyCrypt();

        $dir = storage_path('app/rapor-map');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . '/' . date('Ymd-His') . ($this->dry ? '-dryrun' : '') . '.csv';
        $this->fh = fopen($path, 'w');
        $this->csv(['level', 'check', 'table', 'old_id', 'detail']);

        $this->info($this->dry ? 'DRY-RUN MODE: nothing is written.' : 'WRITE MODE: writing rapor_migration_maps.');

        $this->loadManual();
        $this->mapTime();
        $this->mapConcentrations();
        $this->loadMemberships();
        $this->loadSubjects();
        $this->mapStudents();
        $this->mapGuardians();
        $this->mapStaff();
        $this->mapClassGroups();
        $this->checkTeaching();
        $this->checkScores();
        $this->checkAttendance();
        $this->checkNotesAndCocurriculars();
        $this->checkExtracurriculars();
        $this->checkTemplatesAndSettings();
        $this->checkCurricula();
        $this->checkSchool();
        $this->reportUserRefs();

        fclose($this->fh);

        if (! $this->dry) {
            $this->persist();
        }

        $this->printSummary();
        $this->newLine();
        $this->info("Full report: {$path}");

        return self::SUCCESS;
    }

    // ========================================================================
    // Preflight
    // ========================================================================

    private function preflight(): bool
    {
        $ok = true;

        $required = [
            'pelajars', 'orang_tua_walis', 'users', 'roles', 'model_has_roles', 'rombels', 'rombel_pelajars',
            'rombel_pengajars', 'nilais', 'kehadirans', 'catatan_wali_kelas', 'kokurikulers', 'ekstrakurikulers',
            'ekskul_pelajars', 'mata_pelajarans', 'kurikulums', 'kurikulum_mata_pelajarans',
            'jurusan_mata_pelajarans', 'jurusans', 'tahun_ajarans', 'semesters', 'tahun_ajaran_semesters',
            'tahun_ajaran_kurikulums', 'pengaturans', 'data_sekolahs', 'template_nilai_capaians',
            'template_kokurikuler_capaians', 'template_ekstrakurikuler_deskripsis',
        ];
        $missing = array_filter($required, fn($table) => ! Schema::hasTable($this->prefix . $table));
        if ($missing) {
            $this->error('Staging tables are missing: ' . implode(', ', array_map(fn($table) => $this->prefix . $table, $missing)));
            $ok = false;
        }

        foreach (['rapor_migration_maps', 'rapor_scores', 'rapor_class_group_subjects', 'rapor_extracurriculars'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Target table {$table} does not exist. Run the schema migrations first.");
                $ok = false;
            }
        }

        $columns = [
            'core_schools' => 'education_level',
        ];
        foreach ($columns as $table => $column) {
            if (! Schema::hasColumn($table, $column)) {
                $this->error("Column {$table}.{$column} does not exist. Run the schema migrations first.");
                $ok = false;
            }
        }

        if (! DB::table('core_apps')->where('code', 'rapor')->exists()) {
            $this->error("core_apps has no row with code 'rapor'.");
            $ok = false;
        }

        if (DB::table('user_roles')->whereIn('name', ['superadmin', 'admin', 'user'])->count() < 3) {
            $this->error('user_roles must contain superadmin, admin, and user.');
            $ok = false;
        }

        if ((string) config('rapor_migration.legacy_key') === '') {
            $this->error('RAPOR_LEGACY_APP_KEY is not set in .env.');
            $ok = false;
        }

        // Guard: APP_KEY of this project must be the SHARED key. If it is not, every NISN match would
        // fail silently and anything written later would be unreadable by PINTAR/SIMKA.
        $sample = DB::table('acd_students_vault')->whereNotNull('nisn_encrypted')->value('nisn_encrypted');
        if ($sample !== null) {
            try {
                Crypt::decryptString($sample);
            } catch (\Throwable $e) {
                $this->error('APP_KEY of this project cannot decrypt acd_students_vault. It is not the shared key; copy it from PINTAR.');
                $ok = false;
            }
        }

        return $ok;
    }

    // ========================================================================
    // General helpers
    // ========================================================================

    private function old(string $table)
    {
        return DB::table($this->prefix . $table);
    }

    private function csv(array $row): void
    {
        fputcsv($this->fh, $row, ',', '"', '\\');
    }

    /** Record one finding. Counts are always complete; CSV rows are capped per check by --max-rows. */
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

    private function bump(string $label, string $key, int $n = 1): void
    {
        $this->stats[$label][$key] = ($this->stats[$label][$key] ?? 0) + $n;
    }

    /** Decrypt one column with the legacy key; failures are reported, never guessed. */
    private function dec(string $mode, ?string $value, string $table, string $column, ?string $id): ?string
    {
        $label = $table . '.' . $column;

        if ($value === null || $value === '') {
            $this->bump($label, 'empty');

            return null;
        }

        try {
            $output = $mode === 's' ? $this->legacy->serialized($value) : $this->legacy->plain($value);
            $this->bump($label, 'ok');

            if (is_scalar($output)) {
                return (string) $output;
            }

            return is_object($output) && method_exists($output, '__toString') ? (string) $output : null;
        } catch (\Throwable $e) {
            $this->bump($label, 'failed');
            $this->flag('warn', 'decrypt_failed', $table, $id, $column);

            return null;
        }
    }

    private function setMap(string $table, string $old, ?string $new, ?string $note = null): void
    {
        $this->maps[$table][$old] = $new;
        $this->mapNotes[$table][$old] = $note;
    }

    private function getMap(string $table, ?string $old): ?string
    {
        if ($old === null) {
            return null;
        }

        return $this->manual[$table][$old] ?? $this->maps[$table][$old] ?? null;
    }

    private function ref(?string $userId, string $source): void
    {
        if ($userId === null || $userId === '') {
            return;
        }
        $this->refUsers[$userId][$source] = ($this->refUsers[$userId][$source] ?? 0) + 1;
    }

    private function phone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '0') ? '62' . substr($digits, 1) : $digits;
    }

    private function loadManual(): void
    {
        foreach (DB::table('rapor_migration_maps')->whereIn('note', ['manual', 'skipped'])->get() as $row) {
            if ($row->note === 'skipped') {
                $this->skipped[$row->table_name][$row->old_id] = true;
            } else {
                $this->manual[$row->table_name][$row->old_id] = $row->new_id;
            }
        }

        $file = $this->option('overrides');
        if ($file) {
            if (! is_readable($file)) {
                $this->warn("Overrides file is not readable: {$file}");

                return;
            }
            $handle = fopen($file, 'r');
            fgetcsv($handle, 0, ',', '"', '\\'); // header
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if (count($row) >= 3 && $row[0] !== '' && $row[1] !== '') {
                    $value = trim($row[2]);
                    if (strtoupper($value) === 'SKIP') {
                        // Intentionally excluded (e.g. a legacy user that must not be migrated).
                        $this->skipped[trim($row[0])][trim($row[1])] = true;
                    } elseif (strtoupper($value) === 'ACK') {
                        // Accepted difference (e.g. class_change@even: the student moved class after the legacy roster).
                        $this->acknowledged[trim($row[0])][trim($row[1])] = true;
                    } else {
                        $this->manual[trim($row[0])][trim($row[1])] = $value ?: null;
                    }
                }
            }
            fclose($handle);
        }
    }

    // ========================================================================
    // 1. Academic years, semesters, concentrations
    // ========================================================================

    private function mapTime(): void
    {
        foreach ($this->old('tahun_ajarans')->get() as $year) {
            $target = DB::table('core_academic_years')->where('id', $year->id)->first()
                ?? DB::table('core_academic_years')->where('name', $year->nama)->first();

            if (! $target) {
                $this->flag('warn', 'academic_year_no_target', 'tahun_ajarans', $year->id, (string) $year->nama);

                continue;
            }
            $this->setMap('tahun_ajarans', $year->id, $target->id, $target->id === $year->id ? 'same_id' : 'by_name');
            $this->bump('tahun_ajarans', 'mapped');
        }

        $semesters = $this->old('semesters')->get()->keyBy('id');
        foreach ($this->old('tahun_ajaran_semesters')->get() as $pivot) {
            $semester = $semesters[$pivot->semester_id] ?? null;
            $type = match ((int) ($semester->urutan ?? 0)) {
                1 => 'odd',
                2 => 'even',
                default => null,
            };

            if ($type === null && $semester) {
                $name = mb_strtolower((string) $semester->nama);
                $type = str_contains($name, 'ganjil') ? 'odd' : (str_contains($name, 'genap') ? 'even' : null);
                if ($type) {
                    $this->flag('info', 'semester_order_empty_used_name', 'semesters', $semester->id);
                }
            }
            if ($type === null) {
                $this->flag('warn', 'semester_type_unknown', 'tahun_ajaran_semesters', $pivot->id);

                continue;
            }

            $newYear = $this->getMap('tahun_ajarans', $pivot->tahun_ajaran_id);
            if (! $newYear) {
                $this->flag('warn', 'semester_academic_year_no_target', 'tahun_ajaran_semesters', $pivot->id);

                continue;
            }

            $target = DB::table('core_semesters')->where('academic_year_id', $newYear)->where('type', $type)->first();
            if (! $target) {
                $this->flag('warn', 'semester_no_target', 'tahun_ajaran_semesters', $pivot->id, "type={$type}");

                continue;
            }

            $this->setMap('tahun_ajaran_semesters', $pivot->id, $target->id, 'by_year_type');
            $this->pivot[$pivot->id] = ['year' => $pivot->tahun_ajaran_id, 'type' => $type, 'sem' => $target->id];
            $this->yearPivots[$pivot->tahun_ajaran_id][$type] = $pivot->id;
            $this->bump('tahun_ajaran_semesters', 'mapped');
        }
    }

    private function mapConcentrations(): void
    {
        foreach ($this->old('jurusans')->get() as $major) {
            $target = DB::table('core_concentrations')->where('id', $major->id)->first()
                ?? DB::table('core_concentrations')->where('code', $major->kode)->first();

            if (! $target) {
                $this->flag('warn', 'concentration_no_target', 'jurusans', $major->id, (string) $major->kode);

                continue;
            }
            $this->setMap('jurusans', $major->id, $target->id, $target->id === $major->id ? 'same_id' : 'by_code');
            $this->bump('jurusans', 'mapped');

            if (($major->status === 'aktif') !== ($target->status === 'active')) {
                $this->flag('info', 'concentration_status_differs', 'jurusans', $major->id, "rapor={$major->status} core={$target->status} (core status wins)");
            }
        }
    }

    private function loadMemberships(): void
    {
        $groups = DB::table('acd_class_groups')
            ->get(['id', 'semester_id', 'homeroom_teacher_id', 'name', 'concentration_id', 'grade_level', 'group_number']);
        foreach ($groups as $group) {
            $this->cgInfo[$group->id] = $group;
            $this->cgBySemester[$group->semester_id][] = $group;
        }

        foreach (DB::table('acd_class_group_students')->get(['student_id', 'class_group_id']) as $member) {
            $semesterId = $this->cgInfo[$member->class_group_id]->semester_id ?? null;
            if (! $semesterId) {
                continue;
            }
            $this->membership[$member->student_id][$semesterId][] = $member->class_group_id;
            $this->cgMembers[$member->class_group_id][$member->student_id] = true;
        }
    }

    private function loadSubjects(): void
    {
        foreach ($this->old('mata_pelajarans')->get() as $subject) {
            $canonical = null;
            if ($subject->agama_terkait !== null && trim($subject->agama_terkait) !== '') {
                $value = mb_strtolower(trim($subject->agama_terkait));
                $value = self::RELIGION_ALIAS[$value] ?? $value;
                $canonical = $this->canonicalReligion($value);
                if ($canonical === null) {
                    $this->flag('warn', 'subject_religion_out_of_enum', 'mata_pelajarans', $subject->id, $value);
                }
            }
            if ((int) $subject->is_mapel_agama === 1 && $canonical === null) {
                $this->flag('warn', 'religion_subject_without_religion', 'mata_pelajarans', $subject->id);
            }
            if ((int) $subject->is_mapel_agama === 0 && $canonical !== null) {
                $this->flag('warn', 'non_religion_subject_has_religion', 'mata_pelajarans', $subject->id);
            }
            $this->subjects[$subject->id] = ['religion' => $canonical, 'is_religion' => (int) $subject->is_mapel_agama === 1];
        }
        $this->bump('mata_pelajarans', 'checked', count($this->subjects));
    }

    // ========================================================================
    // 2. Students
    // ========================================================================

    private function mapStudents(): void
    {
        $byNisn = [];
        foreach (DB::table('acd_students_vault')->get(['student_id', 'nisn_hash', 'dob_hash', 'religion_hash']) as $vault) {
            $byNisn[$vault->nisn_hash] = $vault;
        }

        $duplicateNis = $this->old('pelajars')
            ->whereNotNull('nomor_induk')->where('nomor_induk', '!=', '')
            ->select('nomor_induk', DB::raw('COUNT(*) AS c'))->groupBy('nomor_induk')
            ->having('c', '>', 1)->pluck('c', 'nomor_induk')->all();

        $inClassGroup = $this->old('rombel_pelajars')->distinct()->pluck('pelajar_id')->flip()->all();
        $claimed = []; // student_id => old pelajar id

        // Identity guard: a NISN match must also agree on NIS and name (catches NISNs swapped between students).
        $pintarStudents = DB::table('acd_students')->get(['id', 'nis', 'name'])->keyBy('id');
        $normalizeName = fn($value) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $value)));

        foreach ($this->old('pelajars')->orderBy('id')->cursor() as $student) {
            $this->bump('pelajars', 'checked');

            if (isset($this->skipped['pelajars'][$student->id])) {
                $this->bump('pelajars', 'skipped_by_decision');

                continue;
            }

            $nisn = $this->dec('p', $student->nisn, 'pelajars', 'nisn', $student->id);
            $this->dec('p', $student->alamat, 'pelajars', 'alamat', $student->id);
            $this->dec('p', $student->telepon, 'pelajars', 'telepon', $student->id);
            $religion = $this->dec('s', $student->agama, 'pelajars', 'agama', $student->id);
            $dob = $this->dec('s', $student->tanggal_lahir, 'pelajars', 'tanggal_lahir', $student->id);

            // Religion -> enum (values outside the list are reported, never guessed).
            $canonical = null;
            if ($religion !== null) {
                $normalized = mb_strtolower(trim($religion));
                $normalized = self::RELIGION_ALIAS[$normalized] ?? $normalized;
                $canonical = $this->canonicalReligion($normalized);
                if ($canonical === null) {
                    $this->flag('warn', 'religion_out_of_enum', 'pelajars', $student->id, $normalized);
                } else {
                    $this->studentReligion[$student->id] = $canonical;
                }
            }

            // Date of birth: only Y-m-d is accepted (HasBlindIndex rule).
            $dobNormalized = null;
            if ($dob !== null) {
                $dobNormalized = $this->normalizeDob($dob);
                if ($dobNormalized === null) {
                    $this->flag('warn', 'dob_unparsable', 'pelajars', $student->id);
                }
            }

            if (! in_array($student->jenis_kelamin, ['L', 'P'], true)) {
                $this->flag('warn', 'gender_invalid', 'pelajars', $student->id);
            }
            if ($student->pada_tanggal === null) {
                $this->flag('warn', 'entry_date_missing', 'pelajars', $student->id);
            }

            $childOrder = $student->anak_ke === null ? '' : trim((string) $student->anak_ke);
            if ($childOrder === '') {
                $this->flag('info', 'child_order_empty', 'pelajars', $student->id);
            } elseif (! ctype_digit($childOrder)) {
                $this->flag('warn', 'child_order_non_numeric', 'pelajars', $student->id);
            }

            if (preg_match('/^\s*(XII|XI|X)(?![A-Za-z])/i', (string) $student->diterima_di_kelas) !== 1) {
                $this->flag('warn', 'entry_grade_unparsable', 'pelajars', $student->id);
            }

            if (isset($duplicateNis[$student->nomor_induk])) {
                $this->flag('warn', 'duplicate_nis', 'pelajars', $student->id, 'count=' . $duplicateNis[$student->nomor_induk]);
            }

            if (! isset($inClassGroup[$student->id])) {
                $this->flag('info', 'student_without_class_group', 'pelajars', $student->id);
            }

            // Match to PINTAR: NISN (legacy key) -> blindIndexHash (shared key).
            if ($nisn === null || trim($nisn) === '') {
                // Decrypt failures are already reported as decrypt_failed.
                if ($nisn !== null || $student->nisn === null || $student->nisn === '') {
                    $this->flag('warn', 'student_without_nisn', 'pelajars', $student->id);
                }

                continue;
            }

            $manual = $this->manual['pelajars'][$student->id] ?? null;
            $studentId = $manual;
            $vault = null;
            if ($studentId === null) {
                $vault = $byNisn[$this->blindIndexHash($nisn)] ?? null;
                $studentId = $vault->student_id ?? null;
            }

            if ($studentId === null) {
                $this->flag('warn', 'student_no_pintar_match', 'pelajars', $student->id);

                continue;
            }

            if (isset($claimed[$studentId])) {
                $this->flag('warn', 'two_students_one_pintar_match', 'pelajars', $student->id, 'conflicts with ' . $claimed[$studentId]);

                continue;
            }
            $claimed[$studentId] = $student->id;
            $this->setMap('pelajars', $student->id, $studentId, $manual ? 'manual' : 'nisn');
            $this->bump('pelajars', 'mapped');

            $pintar = $pintarStudents[$studentId] ?? null;
            if ($pintar !== null) {
                $legacyNis = trim((string) $student->nomor_induk);
                if ($pintar->nis !== null && $legacyNis !== '' && trim((string) $pintar->nis) !== $legacyNis) {
                    $this->flag('warn', 'nis_differs_from_pintar', 'pelajars', $student->id, "rapor_nis={$legacyNis} pintar_nis={$pintar->nis}");
                }
                if ($normalizeName($student->nama_lengkap) !== $normalizeName($pintar->name)) {
                    $this->flag('info', 'name_differs_from_pintar', 'pelajars', $student->id, "rapor_nis={$legacyNis}");
                }
            }

            // Sanity check: data that should be identical must be identical (detects wrong matches).
            if ($vault !== null) {
                if ($dobNormalized !== null && $vault->dob_hash !== null
                    && ! hash_equals($vault->dob_hash, $this->blindIndexHash($dobNormalized))) {
                    $this->flag('info', 'dob_differs_from_pintar', 'pelajars', $student->id);
                }
                if ($canonical !== null && $vault->religion_hash !== null
                    && ! hash_equals($vault->religion_hash, $this->blindIndexHash($canonical))) {
                    $this->flag('info', 'religion_differs_from_pintar', 'pelajars', $student->id);
                }
            }
        }
    }

    // ========================================================================
    // 3. Parents / guardians
    // ========================================================================

    private function mapGuardians(): void
    {
        $existing = [];
        foreach (DB::table('acd_guardians')->get(['student_id', 'relationship']) as $guardian) {
            $existing[$guardian->student_id . '|' . $guardian->relationship] = true;
        }

        $relationMap = ['ayah' => 'father', 'ibu' => 'mother', 'wali' => 'guardian'];
        $statusMap = ['masih-hidup' => 'alive', 'sudah-meninggal' => 'deceased'];
        $seen = [];

        foreach ($this->old('orang_tua_walis')->orderBy('id')->cursor() as $row) {
            $this->bump('orang_tua_walis', 'checked');

            $this->dec('p', $row->nama, 'orang_tua_walis', 'nama', $row->id);
            $this->dec('p', $row->telepon, 'orang_tua_walis', 'telepon', $row->id);
            $this->dec('p', $row->alamat, 'orang_tua_walis', 'alamat', $row->id);

            $relationship = $relationMap[mb_strtolower(trim((string) $row->hubungan))] ?? null;
            if ($relationship === null) {
                $this->flag('warn', 'guardian_relationship_unknown', 'orang_tua_walis', $row->id);
            }
            if (! isset($statusMap[mb_strtolower(trim((string) $row->status))])) {
                $this->flag('warn', 'guardian_status_unknown', 'orang_tua_walis', $row->id);
            }
            if ($row->pekerjaan !== null && Profession::tryFrom(trim($row->pekerjaan)) === null) {
                $this->flag('warn', 'guardian_occupation_out_of_enum', 'orang_tua_walis', $row->id, (string) $row->pekerjaan);
            }

            $studentId = $this->getMap('pelajars', $row->pelajar_id);
            if ($studentId === null) {
                $this->bump('orang_tua_walis', 'skipped_student_unmatched');

                continue;
            }
            if ($relationship === null) {
                continue;
            }

            $key = $studentId . '|' . $relationship;
            if (isset($seen[$key])) {
                $this->flag('warn', 'guardian_duplicate_relationship', 'orang_tua_walis', $row->id, $relationship);

                continue;
            }
            $seen[$key] = true;

            $this->bump('orang_tua_walis', isset($existing[$key]) ? 'already_in_pintar' : 'will_create');
        }
    }

    // ========================================================================
    // 4. Staff: legacy users -> staff_data
    // ========================================================================

    private function mapStaff(): void
    {
        $byNip = $byEmail = $bySlug = $byPhone = [];
        $vaults = DB::table('staff_data_vault')->get(['staff_id', 'nip_hash', 'email_hash', 'phone_number_encrypted']);
        foreach ($vaults as $vault) {
            if ($vault->nip_hash) {
                $byNip[$vault->nip_hash][] = $vault->staff_id;
            }
            if ($vault->email_hash) {
                $byEmail[$vault->email_hash][] = $vault->staff_id;
            }
            if ($vault->phone_number_encrypted) {
                try {
                    $normalized = $this->phone(Crypt::decryptString($vault->phone_number_encrypted));
                    if ($normalized) {
                        $byPhone[$normalized][] = $vault->staff_id;
                    }
                } catch (\Throwable $e) {
                    $this->flag('warn', 'staff_phone_decrypt_failed', 'staff_data_vault', $vault->staff_id);
                }
            }
        }
        foreach (DB::table('staff_data')->get(['id', 'slug']) as $staff) {
            $bySlug[$staff->slug][] = $staff->id;
        }

        // Legacy roles (Spatie) -> the three target roles.
        $roleNames = [];
        $roleRows = $this->old('model_has_roles as mr')
            ->join($this->prefix . 'roles as r', 'r.id', '=', 'mr.role_id')
            ->get(['mr.model_id', 'r.name']);
        foreach ($roleRows as $roleRow) {
            $roleNames[$roleRow->model_id][] = mb_strtolower($roleRow->name);
        }

        $coreUserByStaff = DB::table('users')->whereNotNull('staff_id')->pluck('id', 'staff_id')->all();
        $usernames = DB::table('users')->pluck('username')->map(fn($username) => mb_strtolower($username))->flip()->all();
        $usedStaff = [];

        foreach ($this->old('users')->orderBy('id')->get() as $user) {
            $this->bump('users', 'checked');

            if (isset($this->skipped['users'][$user->id])) {
                $this->bump('users', 'skipped_by_decision');

                continue;
            }

            $email = $this->dec('s', $user->email, 'users', 'email', $user->id);
            $nip = $this->dec('s', $user->nip, 'users', 'nip', $user->id);
            $telephone = $this->dec('s', $user->telephone, 'users', 'telephone', $user->id);
            $specialization = $this->dec('s', $user->spesialisasi_agama, 'users', 'spesialisasi_agama', $user->id);
            if ($specialization !== null
                && $this->canonicalReligion(self::RELIGION_ALIAS[mb_strtolower(trim($specialization))] ?? $specialization) === null) {
                $this->flag('warn', 'specialization_out_of_enum', 'users', $user->id, $specialization);
            }

            $names = $roleNames[$user->id] ?? [];
            $role = in_array('superadmin', $names, true) ? 'superadmin' : (in_array('admin', $names, true) ? 'admin' : 'user');
            $this->bump('users.new_role', $role);

            $candidates = [
                'nip' => $nip ? ($byNip[hash('sha256', trim($nip))] ?? []) : [],
                'email' => $email ? ($byEmail[hash('sha256', mb_strtolower(trim($email)))] ?? []) : [],
                'slug' => $user->slug ? ($bySlug[$user->slug] ?? []) : [],
                'phone' => $telephone ? ($byPhone[$this->phone($telephone)] ?? []) : [],
            ];

            $resolved = $this->manual['users'][$user->id] ?? null;
            $method = $resolved ? 'manual' : null;
            $ambiguous = false;

            if ($resolved === null) {
                foreach ($candidates as $candidateMethod => $ids) {
                    $ids = array_values(array_unique($ids));
                    if (count($ids) === 1) {
                        $resolved = $ids[0];
                        $method = $candidateMethod;
                        break;
                    }
                    if (count($ids) > 1) {
                        $ambiguous = true;
                        $this->flag('warn', 'user_staff_ambiguous', 'users', $user->id, "via={$candidateMethod} candidates=" . count($ids));
                        break;
                    }
                }
            }

            if ($resolved === null) {
                if (! $ambiguous) {
                    $this->flag('warn', 'user_no_staff_match', 'users', $user->id, "name={$user->name}; slug={$user->slug}; role={$role}");
                }
                if ($role === 'superadmin') {
                    $this->flag('warn', 'superadmin_no_staff_match', 'users', $user->id, (string) $user->name);
                }
                $proposed = mb_strtolower((string) $user->slug);
                if ($proposed === '') {
                    $this->flag('warn', 'non_staff_user_no_username', 'users', $user->id);
                } elseif (isset($usernames[$proposed])) {
                    $this->flag('warn', 'proposed_username_conflict', 'users', $user->id, $proposed);
                } else {
                    $this->flag('info', 'non_staff_username_from_slug', 'users', $user->id, $proposed);
                }

                continue;
            }

            // Contradicting identity signals = possible wrong match.
            foreach ($candidates as $candidateMethod => $ids) {
                $ids = array_values(array_unique($ids));
                if ($candidateMethod !== $method && count($ids) === 1 && $ids[0] !== $resolved) {
                    $this->flag('warn', 'user_identity_conflict', 'users', $user->id, "chosen via {$method}, but {$candidateMethod} points to another staff");
                }
            }

            if (isset($usedStaff[$resolved])) {
                $this->flag('warn', 'two_users_one_staff', 'users', $user->id, 'conflicts with ' . $usedStaff[$resolved]);

                continue;
            }
            $usedStaff[$resolved] = $user->id;

            $this->setMap('users', $user->id, $resolved, $method);
            $this->bump('users.matched_via', $method);

            if (isset($coreUserByStaff[$resolved])) {
                $this->bump('users', 'core_user_exists');
            } else {
                $this->bump('users', 'core_user_will_create');
                $proposed = mb_strtolower((string) $user->slug);
                if ($proposed !== '' && isset($usernames[$proposed])) {
                    $this->flag('warn', 'proposed_username_conflict', 'users', $user->id, $proposed);
                }
            }
        }
    }

    // ========================================================================
    // 5. Study groups (rombel) -> class groups (per semester)
    // ========================================================================

    private function mapClassGroups(): void
    {
        $slugToUser = $this->old('users')->pluck('id', 'slug')->all();
        $normalize = fn($value) => mb_strtolower(preg_replace('/\s+/', '', (string) $value));

        $members = [];
        foreach ($this->old('rombel_pelajars')->get(['rombel_id', 'pelajar_id']) as $row) {
            $members[$row->rombel_id][] = $row->pelajar_id;
        }

        // Students with more than one study group in a single academic year (moved class).
        $multiple = $this->old('rombel_pelajars as rp')
            ->join($this->prefix . 'rombels as r', 'r.id', '=', 'rp.rombel_id')
            ->groupBy('rp.pelajar_id', 'r.tahun_ajaran_id')
            ->havingRaw('COUNT(*) > 1')
            ->select('rp.pelajar_id', 'r.tahun_ajaran_id', DB::raw('COUNT(*) c'))->get();
        foreach ($multiple as $row) {
            $this->flag('info', 'student_multiple_class_groups_in_year', 'pelajars', $row->pelajar_id, "count={$row->c}");
        }

        foreach ($this->old('rombels')->orderBy('nama')->get() as $rombel) {
            $this->bump('rombels', 'checked');

            $concentrationId = $this->getMap('jurusans', $rombel->jurusan_id);
            if (! $concentrationId) {
                $this->flag('warn', 'class_group_concentration_no_target', 'rombels', $rombel->id);

                continue;
            }

            $staffId = null;
            if ($rombel->wali_kelas_slug === null || $rombel->wali_kelas_slug === '') {
                $this->flag('info', 'class_group_without_homeroom', 'rombels', $rombel->id, (string) $rombel->nama);
            } else {
                $userId = $slugToUser[$rombel->wali_kelas_slug] ?? null;
                if ($userId === null) {
                    $this->flag('warn', 'homeroom_slug_not_found', 'rombels', $rombel->id, (string) $rombel->nama);
                } else {
                    $this->ref($userId, 'rombels.wali_kelas');
                    $staffId = $this->getMap('users', $userId);
                    if ($staffId === null) {
                        $this->flag('warn', 'homeroom_no_staff_match', 'rombels', $rombel->id, (string) $rombel->nama);
                    }
                }
            }

            foreach (['odd', 'even'] as $type) {
                $mapKey = 'rombels@' . $type;
                $pivotId = $this->yearPivots[$rombel->tahun_ajaran_id][$type] ?? null;
                if (! $pivotId) {
                    $this->flag('warn', 'class_group_semester_no_target', 'rombels', $rombel->id, "{$rombel->nama} {$type}");

                    continue;
                }
                $semesterId = $this->pivot[$pivotId]['sem'];

                $hitId = $this->manual[$mapKey][$rombel->id] ?? null;
                $how = $hitId ? 'manual' : null;

                if ($hitId === null) {
                    $candidates = array_values(array_filter(
                        $this->cgBySemester[$semesterId] ?? [],
                        fn($group) => $group->concentration_id === $concentrationId && (string) $group->grade_level === (string) $rombel->tingkat
                    ));
                    $byName = array_values(array_filter($candidates, fn($group) => $normalize($group->name) === $normalize($rombel->nama)));

                    if (count($byName) === 1) {
                        $hitId = $byName[0]->id;
                        $how = 'name';
                    } else {
                        $number = preg_match('/(\d+)\s*$/', (string) $rombel->nama, $matches) === 1 ? (int) $matches[1] : 1;
                        $byNumber = array_values(array_filter($candidates, fn($group) => (int) $group->group_number === $number));
                        if (count($byNumber) === 1) {
                            $hitId = $byNumber[0]->id;
                            $how = 'group_number';
                            $this->flag('info', 'class_group_matched_by_group_number', 'rombels', $rombel->id, "{$rombel->nama} {$type}");
                        } else {
                            $check = count($byName) > 1 || count($byNumber) > 1 ? 'class_group_match_ambiguous' : 'class_group_no_match';
                            $this->flag('warn', $check, 'rombels', $rombel->id, "{$rombel->nama} {$type}");

                            continue;
                        }
                    }
                }

                $this->setMap($mapKey, $rombel->id, $hitId, $how);
                $this->bump('rombels', "mapped_{$type}");

                $group = $this->cgInfo[$hitId] ?? null;
                if ($group && $staffId !== null) {
                    if ($group->homeroom_teacher_id === null) {
                        $this->flag('info', 'pintar_without_homeroom', 'rombels', $rombel->id, "{$rombel->nama} {$type}");
                    } elseif ($group->homeroom_teacher_id !== $staffId) {
                        $this->flag('info', 'homeroom_differs_from_pintar', 'rombels', $rombel->id, "{$rombel->nama} {$type}");
                    }
                }

                // Membership: legacy students of this rombel vs PINTAR class group members.
                $both = $missing = $unmatched = $acknowledged = 0;
                $missingStudents = [];
                foreach ($members[$rombel->id] ?? [] as $oldStudentId) {
                    $studentId = $this->getMap('pelajars', $oldStudentId);
                    if ($studentId === null) {
                        $unmatched++;
                    } elseif (isset($this->cgMembers[$hitId][$studentId])) {
                        $both++;
                    } elseif (isset($this->acknowledged["class_change@{$type}"][$oldStudentId])) {
                        $acknowledged++;
                    } else {
                        $missing++;
                        $missingStudents[] = $oldStudentId;
                    }
                }
                $this->bump('class_group_members', 'matched', $both);
                $this->bump('class_group_members', 'acknowledged_class_change', $acknowledged);
                if ($missing > 0) {
                    $this->flag('warn', 'class_members_differ_from_pintar', 'rombels', $rombel->id,
                        "{$rombel->nama} {$type}: matched={$both} not_in_pintar={$missing} unmatched_students={$unmatched}");
                    foreach ($missingStudents as $oldStudentId) {
                        $this->flag('info', 'student_not_in_pintar_class_group', 'pelajars', $oldStudentId, "{$rombel->nama} {$type}");
                    }
                }
            }
        }
    }

    // ========================================================================
    // 6. Teacher per subject + semester
    // ========================================================================

    private function checkTeaching(): void
    {
        $assignments = $this->old('rombel_pengajars')->get();
        $rombelIds = $this->old('rombels')->pluck('id')->flip()->all();

        foreach ($assignments as $assignment) {
            if (! isset($rombelIds[$assignment->rombel_id])) {
                $this->flag('warn', 'assignment_class_group_missing', 'rombel_pengajars', $assignment->id);
            }
            if (! isset($this->subjects[$assignment->mata_pelajaran_id])) {
                $this->flag('warn', 'assignment_subject_missing', 'rombel_pengajars', $assignment->id);
            }
            $this->ref($assignment->guru_id, 'rombel_pengajars.guru_id');
        }

        $p = $this->prefix;

        // More than one teacher for the same subject + study group + semester = manual conflict.
        $conflicts = DB::table($p . 'nilais as n')
            ->join($p . 'rombel_pengajars as rp', 'rp.id', '=', 'n.rombel_pengajar_id')
            ->whereNull('n.deleted_at')->whereNotNull('n.guru_id')
            ->groupBy('rp.rombel_id', 'n.mata_pelajaran_id', 'n.tahun_ajaran_semester_id')
            ->havingRaw('COUNT(DISTINCT n.guru_id) > 1')
            ->select('rp.rombel_id', 'n.mata_pelajaran_id', 'n.tahun_ajaran_semester_id', DB::raw('COUNT(DISTINCT n.guru_id) g'))
            ->get();
        foreach ($conflicts as $conflict) {
            $this->flag('warn', 'teacher_conflict_subject_semester', 'rombel_pengajars', $conflict->rombel_id,
                "subject={$conflict->mata_pelajaran_id} semester={$conflict->tahun_ajaran_semester_id} teachers={$conflict->g}");
        }

        // Teacher recorded on scores, per (assignment, semester).
        $fromScores = DB::table($p . 'nilais')
            ->whereNull('deleted_at')->whereNotNull('guru_id')
            ->select('rombel_pengajar_id', 'tahun_ajaran_semester_id', DB::raw('MIN(guru_id) g'))
            ->groupBy('rombel_pengajar_id', 'tahun_ajaran_semester_id')->get();
        $known = [];
        foreach ($fromScores as $row) {
            $known[$row->rombel_pengajar_id . '|' . $row->tahun_ajaran_semester_id] = $row->g;
            $this->ref($row->g, 'nilais.guru_id');
        }

        foreach ($assignments as $assignment) {
            $pivots = $this->yearPivots[$this->yearOfRombel($assignment->rombel_id)] ?? [];
            foreach ($pivots as $pivotId) {
                $key = $assignment->id . '|' . $pivotId;
                if (isset($known[$key])) {
                    $this->bump('teacher_per_semester', 'from_scores');
                    if ($assignment->guru_id !== null && $known[$key] !== $assignment->guru_id) {
                        $this->flag('info', 'score_teacher_differs_from_assignment', 'rombel_pengajars', $assignment->id, "semester={$pivotId}");
                    }
                } elseif ($assignment->guru_id !== null) {
                    $this->bump('teacher_per_semester', 'guessed_from_assignment');
                } else {
                    $this->flag('info', 'assignment_without_teacher', 'rombel_pengajars', $assignment->id, "semester={$pivotId}");
                }
            }
        }

        // A subject scored in only one semester is valid in that semester only.
        $perSubject = DB::table($p . 'nilais as n')
            ->join($p . 'rombel_pengajars as rp', 'rp.id', '=', 'n.rombel_pengajar_id')
            ->whereNull('n.deleted_at')
            ->groupBy('rp.rombel_id', 'n.mata_pelajaran_id')
            ->select('rp.rombel_id', 'n.mata_pelajaran_id', DB::raw('COUNT(DISTINCT n.tahun_ajaran_semester_id) s'))->get();
        foreach ($perSubject as $row) {
            $this->bump('subject_per_class_group', $row->s == 1 ? 'single_semester' : 'both_semesters');
        }
    }

    private function yearOfRombel(string $rombelId): ?string
    {
        if (! $this->rombelYear) {
            $this->rombelYear = $this->old('rombels')->pluck('tahun_ajaran_id', 'id')->all();
        }

        return $this->rombelYear[$rombelId] ?? null;
    }

    // ========================================================================
    // 7. Scores
    // ========================================================================

    private function checkScores(): void
    {
        $assignments = $this->old('rombel_pengajars')->get(['id', 'rombel_id', 'mata_pelajaran_id'])->keyBy('id');

        foreach ($this->old('nilais')->whereNull('deleted_at')->orderBy('id')->cursor() as $score) {
            $this->bump('nilais', 'checked');
            $this->ref($score->created_by, 'nilais.created_by');
            $this->ref($score->updated_by, 'nilais.updated_by');

            $studentId = $this->getMap('pelajars', $score->pelajar_id);
            $semesterId = $this->getMap('tahun_ajaran_semesters', $score->tahun_ajaran_semester_id);
            $type = $this->pivot[$score->tahun_ajaran_semester_id]['type'] ?? null;

            if ($studentId === null) {
                $this->flag('warn', 'score_student_no_match', 'nilais', $score->id);

                continue;
            }
            if ($semesterId === null || $type === null) {
                $this->flag('warn', 'score_semester_no_target', 'nilais', $score->id);

                continue;
            }
            if (! isset($this->subjects[$score->mata_pelajaran_id])) {
                $this->flag('warn', 'score_subject_missing', 'nilais', $score->id);

                continue;
            }

            if ($score->nilai_angka < 0 || $score->nilai_angka > 100) {
                $this->flag('warn', 'score_out_of_range', 'nilais', $score->id);
            }
            if ($score->predikat === null) {
                $this->bump('nilais', 'without_predicate');
            }

            $assignment = $assignments[$score->rombel_pengajar_id] ?? null;
            if ($assignment === null) {
                $this->flag('warn', 'score_orphan_assignment', 'nilais', $score->id);
            } else {
                if ($assignment->mata_pelajaran_id !== $score->mata_pelajaran_id) {
                    $this->flag('warn', 'score_subject_differs_from_assignment', 'nilais', $score->id);
                }
                $classGroupId = $this->getMap('rombels@' . $type, $assignment->rombel_id);
                if ($classGroupId !== null && ! isset($this->cgMembers[$classGroupId][$studentId])) {
                    if (isset($this->acknowledged["class_change@{$type}"][$score->pelajar_id])) {
                        // Accepted: the score belongs to the former class and stays as history (not printed).
                        $this->flag('info', 'score_kept_as_class_history', 'nilais', $score->id, "student={$score->pelajar_id} rombel={$assignment->rombel_id} {$type}");
                    } else {
                        $inSemester = $this->membership[$studentId][$semesterId] ?? [];
                        $this->flag('warn', $inSemester ? 'score_class_not_student_class' : 'score_student_no_class_in_semester', 'nilais', $score->id, "student={$score->pelajar_id} rombel={$assignment->rombel_id} {$type}");
                        $this->scoreMismatchStudents[$score->pelajar_id] = true;
                    }
                }
            }

            // A religion subject must match the student's religion (compare enum values, not hashes).
            $subject = $this->subjects[$score->mata_pelajaran_id];
            if ($subject['is_religion'] && $subject['religion'] !== null) {
                $studentReligion = $this->studentReligion[$score->pelajar_id] ?? null;
                if ($studentReligion !== null && $studentReligion !== $subject['religion']) {
                    $this->flag('warn', 'score_religion_subject_mismatch', 'nilais', $score->id);
                }
            }
        }

        $this->bump('nilais', 'class_mismatch_distinct_students', count($this->scoreMismatchStudents));
    }

    // ========================================================================
    // 8. Attendance
    // ========================================================================

    private function checkAttendance(): void
    {
        foreach ($this->old('kehadirans')->orderBy('id')->cursor() as $attendance) {
            $this->bump('kehadirans', 'checked');
            $studentId = $this->getMap('pelajars', $attendance->pelajar_id);
            $type = $this->pivot[$attendance->tahun_ajaran_semester_id]['type'] ?? null;

            if ($studentId === null) {
                $this->flag('warn', 'attendance_student_no_match', 'kehadirans', $attendance->id);

                continue;
            }
            $classGroupId = $type ? $this->getMap('rombels@' . $type, $attendance->rombel_id) : null;
            if ($classGroupId === null) {
                $this->flag('warn', 'attendance_no_target', 'kehadirans', $attendance->id, 'rombel/semester has no class group');

                continue;
            }
            if (! isset($this->cgMembers[$classGroupId][$studentId])) {
                $level = isset($this->acknowledged["class_change@{$type}"][$attendance->pelajar_id]) ? 'info' : 'warn';
                $this->flag($level, 'attendance_student_not_member', 'kehadirans', $attendance->id, "student={$attendance->pelajar_id} rombel={$attendance->rombel_id} {$type}");
            }
        }
    }

    // ========================================================================
    // 9. Homeroom notes + cocurriculars (legacy source has no rombel_id)
    // ========================================================================

    /** @return array{0:?string,1:string} [class_group_id, outcome] */
    private function deriveClass(string $oldStudentId, string $pivotId, ?string $teacherUserId): array
    {
        $studentId = $this->getMap('pelajars', $oldStudentId);
        $semesterId = $this->pivot[$pivotId]['sem'] ?? null;
        if ($studentId === null || $semesterId === null) {
            return [null, 'no_target'];
        }

        $candidates = array_values(array_unique($this->membership[$studentId][$semesterId] ?? []));
        if (count($candidates) === 0) {
            return [null, 'no_class'];
        }
        if (count($candidates) === 1) {
            return [$candidates[0], 'single_class'];
        }

        $staffId = $this->getMap('users', $teacherUserId);
        $hits = array_values(array_filter(
            $candidates,
            fn($id) => $staffId !== null && ($this->cgInfo[$id]->homeroom_teacher_id ?? null) === $staffId
        ));
        if (count($hits) === 1) {
            return [$hits[0], 'picked_via_homeroom'];
        }

        return [null, 'ambiguous'];
    }

    private function checkNotesAndCocurriculars(): void
    {
        foreach (['catatan_wali_kelas' => 'homeroom_note', 'kokurikulers' => 'cocurricular'] as $table => $label) {
            foreach ($this->old($table)->orderBy('id')->cursor() as $row) {
                $this->bump($table, 'checked');
                $this->ref($row->guru_id, $table . '.guru_id');

                [$classGroupId, $outcome] = $this->deriveClass($row->pelajar_id, $row->tahun_ajaran_semester_id, $row->guru_id);
                $this->bump($table, $outcome);
                if ($classGroupId === null) {
                    $this->flag('warn', "{$label}_class_{$outcome}", $table, $row->id);
                }
            }

            // Duplicates per student + semester (cocurriculars have no unique key; notes not unique yet).
            $duplicates = $this->old($table)->groupBy('pelajar_id', 'tahun_ajaran_semester_id')
                ->havingRaw('COUNT(*) > 1')->select('pelajar_id', DB::raw('COUNT(*) c'))->get();
            foreach ($duplicates as $duplicate) {
                $this->flag('warn', "{$label}_duplicate_student_semester", $table, $duplicate->pelajar_id, "count={$duplicate->c}");
            }
        }
    }

    // ========================================================================
    // 10. Extracurriculars
    // ========================================================================

    private function checkExtracurriculars(): void
    {
        $extracurriculars = $this->old('ekstrakurikulers')->get();
        $names = [];
        foreach ($extracurriculars as $extracurricular) {
            $this->ref($extracurricular->pembina_id, 'ekstrakurikulers.pembina_id');
            if ($this->getMap('users', $extracurricular->pembina_id) === null) {
                $this->flag('warn', 'extracurricular_no_matching_supervisor', 'ekstrakurikulers', $extracurricular->id, (string) $extracurricular->nama);
            }
            $key = mb_strtolower(trim((string) $extracurricular->nama));
            if (isset($names[$key])) {
                $this->flag('warn', 'extracurricular_duplicate_name', 'ekstrakurikulers', $extracurricular->id, (string) $extracurricular->nama);
            }
            $names[$key] = true;
        }
        $ids = $extracurriculars->pluck('id')->flip()->all();

        $seen = [];
        foreach ($this->old('ekskul_pelajars')->orderBy('id')->cursor() as $member) {
            $this->bump('ekskul_pelajars', 'checked');
            $this->ref($member->created_by, 'ekskul_pelajars.created_by');
            $this->ref($member->updated_by, 'ekskul_pelajars.updated_by');

            if (! isset($ids[$member->ekstrakurikuler_id])) {
                $this->flag('warn', 'extracurricular_member_extracurricular_missing', 'ekskul_pelajars', $member->id);

                continue;
            }
            if ($this->getMap('pelajars', $member->pelajar_id) === null) {
                $this->flag('warn', 'extracurricular_member_student_no_match', 'ekskul_pelajars', $member->id);

                continue;
            }
            if ($this->getMap('tahun_ajaran_semesters', $member->tahun_ajaran_semester_id) === null) {
                $this->flag('warn', 'extracurricular_member_semester_no_target', 'ekskul_pelajars', $member->id);

                continue;
            }

            $key = $member->ekstrakurikuler_id . '|' . $member->tahun_ajaran_semester_id . '|' . $member->pelajar_id;
            if (isset($seen[$key])) {
                $this->flag('warn', 'extracurricular_member_duplicate', 'ekskul_pelajars', $member->id);
            }
            $seen[$key] = true;
        }

        // Extracurriculars are global in rapor_*; the supervisor is copied to every mapped semester.
        $this->bump('extracurriculars', 'rows', count($ids));
        $this->bump('extracurriculars', 'supervisor_rows', count($ids) * count(array_unique(array_column($this->pivot, 'sem'))));
        $this->bump('extracurriculars', 'members_to_copy', count($seen));
    }

    // ========================================================================
    // 11. Templates + settings
    // ========================================================================

    private function checkTemplatesAndSettings(): void
    {
        $extracurricularIds = $this->old('ekstrakurikulers')->pluck('id')->flip()->all();

        $templateTables = [
            'template_nilai_capaians' => 'score',
            'template_kokurikuler_capaians' => 'cocurricular',
            'template_ekstrakurikuler_deskripsis' => 'extracurricular',
        ];
        foreach ($templateTables as $table => $label) {
            foreach ($this->old($table)->get() as $template) {
                $this->bump($table, 'checked');
                $this->ref($template->created_by, $table . '.created_by');
                $this->ref($template->updated_by, $table . '.updated_by');

                if ($this->getMap('tahun_ajaran_semesters', $template->tahun_ajaran_semester_id) === null) {
                    $this->flag('warn', "template_{$label}_semester_no_target", $table, $template->id);
                }
                if ($label === 'score' && $template->mata_pelajaran_id !== null && ! isset($this->subjects[$template->mata_pelajaran_id])) {
                    $this->flag('warn', 'template_score_subject_missing', $table, $template->id);
                }
                if ($label === 'extracurricular' && $template->ekstrakurikuler_id !== null && ! isset($extracurricularIds[$template->ekstrakurikuler_id])) {
                    $this->flag('warn', 'template_extracurricular_missing', $table, $template->id);
                }
            }
        }

        // NULLs in unique columns are treated as distinct, so "global" rows can be duplicated.
        $duplicateSets = [
            'template_nilai_capaians' => ['COALESCE(mata_pelajaran_id,\'\'), COALESCE(tingkat,\'\'), rentang_min', 'tahun_ajaran_semester_id'],
            'template_kokurikuler_capaians' => ['COALESCE(tingkat,\'\'), COALESCE(subdimensi,\'\'), predikat', 'tahun_ajaran_semester_id'],
            'template_ekstrakurikuler_deskripsis' => ['COALESCE(ekstrakurikuler_id,\'\'), predikat', 'tahun_ajaran_semester_id'],
            'jurusan_mata_pelajarans' => ['jurusan_id, mata_pelajaran_id, COALESCE(kurikulum_id,\'\')', 'jurusan_id'],
        ];
        foreach ($duplicateSets as $table => [$columns, $sample]) {
            $rows = DB::table($this->prefix . $table)
                ->selectRaw("MIN({$sample}) AS s, COUNT(*) AS c")
                ->groupByRaw(($table === 'jurusan_mata_pelajarans' ? '' : 'tahun_ajaran_semester_id, ') . $columns)
                ->havingRaw('COUNT(*) > 1')->get();
            foreach ($rows as $row) {
                $this->flag('warn', 'duplicate_due_to_null_unique_column', $table, $row->s, "count={$row->c}");
            }
        }

        foreach ($this->old('pengaturans')->get() as $setting) {
            $this->bump('pengaturans', 'checked');
            $this->ref($setting->kepala_sekolah_id, 'pengaturans.kepala_sekolah_id');
            if ($this->getMap('tahun_ajaran_semesters', $setting->tahun_ajaran_semester_id) === null) {
                $this->flag('warn', 'setting_semester_no_target', 'pengaturans', $setting->id);
            }
            if ($setting->tanggal_rapor === null) {
                $this->flag('warn', 'setting_without_report_date', 'pengaturans', $setting->id);
            }
        }
    }

    // ========================================================================
    // 12. Curricula
    // ========================================================================

    private function checkCurricula(): void
    {
        $duplicateCurricula = $this->old('kurikulums')->groupBy('kode')->havingRaw('COUNT(*) > 1')
            ->select('kode', DB::raw('COUNT(*) c'))->get();
        foreach ($duplicateCurricula as $row) {
            $this->flag('warn', 'curriculum_duplicate_code', 'kurikulums', null, "code={$row->kode} count={$row->c}");
        }

        $duplicateSubjects = $this->old('mata_pelajarans')->groupBy('kode')->havingRaw('COUNT(*) > 1')
            ->select('kode', DB::raw('COUNT(*) c'))->get();
        foreach ($duplicateSubjects as $row) {
            $this->flag('info', 'subject_duplicate_code', 'mata_pelajarans', null, "code={$row->kode} count={$row->c}");
        }

        foreach ($this->old('tahun_ajaran_kurikulums')->get() as $row) {
            if ($this->getMap('tahun_ajarans', $row->tahun_ajaran_id) === null) {
                $this->flag('warn', 'curriculum_academic_year_no_target', 'tahun_ajaran_kurikulums', $row->id);
            }
        }

        foreach ($this->old('jurusan_mata_pelajarans')->get() as $row) {
            if ($this->getMap('jurusans', $row->jurusan_id) === null) {
                $this->flag('warn', 'concentration_subject_concentration_no_target', 'jurusan_mata_pelajarans', $row->id);
            }
        }

        $withoutCurriculum = $this->old('rombels')->whereNull('tahun_ajaran_kurikulum_id')->count();
        if ($withoutCurriculum > 0) {
            $this->flag('info', 'class_group_without_curriculum', 'rombels', null, "count={$withoutCurriculum}");
        }
    }

    // ========================================================================
    // 13. School data
    // ========================================================================

    private function checkSchool(): void
    {
        $rows = $this->old('data_sekolahs')->get();
        $this->bump('data_sekolahs', 'rows', $rows->count());
        if ($rows->count() !== 1) {
            $this->flag('warn', 'school_not_single_row', 'data_sekolahs', null, 'count=' . $rows->count());
        }

        $required = ['npsn', 'status_sekolah', 'alamat', 'kelurahan', 'kecamatan', 'kota_kabupaten', 'provinsi', 'telepon', 'email'];
        foreach ($rows as $school) {
            $empty = array_values(array_filter($required, fn($column) => $school->{$column} === null || trim((string) $school->{$column}) === ''));
            if ($empty) {
                $this->flag('warn', 'school_required_core_fields_empty', 'data_sekolahs', $school->id, implode(',', $empty));
            }
            $core = DB::table('core_schools')->first();
            if ($core) {
                $this->flag('info', 'core_school_exists', 'data_sekolahs', $school->id, $core->npsn === $school->npsn ? 'same npsn (update)' : 'DIFFERENT NPSN');
            }
        }
    }

    // ========================================================================
    // User references (teachers, creators, editors, homeroom, supervisors, headmaster)
    // ========================================================================

    private function reportUserRefs(): void
    {
        $known = $this->old('users')->pluck('id')->flip()->all();
        foreach ($this->refUsers as $userId => $sources) {
            if (isset($this->skipped['users'][$userId])) {
                $this->bump('users', 'references_to_skipped_user', array_sum($sources));

                continue;
            }
            $detail = implode('; ', array_map(fn($source, $n) => "{$source}={$n}", array_keys($sources), $sources));
            if (! isset($known[$userId])) {
                $this->flag('warn', 'user_referenced_missing_in_users', 'users', $userId, $detail);
            } elseif ($this->getMap('users', $userId) === null) {
                $this->flag('warn', 'user_referenced_no_staff_match', 'users', $userId, $detail);
            }
        }
    }

    // ========================================================================
    // Persist mappings + summary
    // ========================================================================

    private function persist(): void
    {
        $now = now();
        $rows = [];
        foreach ($this->maps as $table => $items) {
            foreach ($items as $old => $new) {
                $rows[] = [
                    'table_name' => $table,
                    'old_id' => $old,
                    'new_id' => $this->manual[$table][$old] ?? $new,
                    'note' => isset($this->manual[$table][$old]) ? 'manual' : ($this->mapNotes[$table][$old] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        // Manual decisions without an automatic result are still stored.
        foreach ($this->manual as $table => $items) {
            foreach ($items as $old => $new) {
                if (! isset($this->maps[$table][$old])) {
                    $rows[] = ['table_name' => $table, 'old_id' => $old, 'new_id' => $new, 'note' => 'manual', 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }

        foreach ($this->skipped as $table => $items) {
            foreach (array_keys($items) as $old) {
                $rows[] = ['table_name' => $table, 'old_id' => $old, 'new_id' => null, 'note' => 'skipped', 'created_at' => $now, 'updated_at' => $now];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('rapor_migration_maps')->upsert($chunk, ['table_name', 'old_id'], ['new_id', 'note', 'updated_at']);
        }
        $this->info('Mappings written: ' . count($rows) . ' rows.');
    }

    private function printSummary(): void
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

        $this->newLine();
        $this->info('STATISTICS (includes the decryption audit: ok / failed / empty)');
        $rows = [];
        ksort($this->stats);
        foreach ($this->stats as $label => $items) {
            ksort($items);
            foreach ($items as $key => $n) {
                $rows[] = [$label, $key, $n];
            }
        }
        $this->table(['Group', 'Result', 'Count'], $rows);

        $warnings = array_sum(array_filter($this->counts, fn($n, $key) => str_starts_with($key, 'warn|'), ARRAY_FILTER_USE_BOTH));
        $this->newLine();
        $warnings > 0
            ? $this->warn("{$warnings} WARN findings must be decided before rapor:copy.")
            : $this->info('No WARN findings.');
    }
}
