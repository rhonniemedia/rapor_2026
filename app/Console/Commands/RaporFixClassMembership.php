<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * rapor:fix-class-membership - corrects the class group of a student for ONE semester in PINTAR.
 *
 * Input CSV (header required): nis,semester_code,class_name
 *
 * Rules:
 * - Only moves an EXISTING acd_class_group_students row to the target class group of the same semester
 *   (changes class_group_id only; entry/exit dates and reasons are left untouched).
 * - Never creates or deletes membership rows. A student with no row, or more than one row, in that semester
 *   is reported and nothing is changed.
 * - All-or-nothing: if any CSV row cannot be applied, no row is changed.
 * - Idempotent: rows that are already correct are reported and skipped.
 * - --dry-run only reports. A real run writes an audit CSV (old and new class_group_id) for rollback.
 */
class RaporFixClassMembership extends Command
{
    protected $signature = 'rapor:fix-class-membership
        {file : CSV with columns nis,semester_code,class_name}
        {--dry-run : Report only, change nothing}
        {--force : Skip the backup confirmation}';

    protected $description = 'Move a student to the correct class group for one semester in PINTAR (idempotent, all-or-nothing, supports --dry-run).';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error("File is not readable: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);
        if ($rows === []) {
            $this->error('The CSV has no data rows.');

            return self::FAILURE;
        }

        $plans = array_map(fn($row) => $this->plan($row), $rows);

        $this->table(
            ['NIS', 'Semester', 'From class', 'To class', 'Status'],
            array_map(fn($plan) => [$plan['nis'], $plan['semester'], $plan['from'] ?? '-', $plan['to'] ?? '-', $plan['status']], $plans)
        );

        $blocked = array_filter($plans, fn($plan) => ! in_array($plan['status'], ['update', 'already_correct'], true));
        if ($blocked) {
            $this->error(count($blocked) . ' row(s) cannot be applied. Nothing was changed.');
            foreach ($blocked as $plan) {
                $this->line(" - {$plan['nis']} {$plan['semester']}: {$plan['message']}");
            }

            return self::FAILURE;
        }

        $updates = array_values(array_filter($plans, fn($plan) => $plan['status'] === 'update'));
        if ($updates === []) {
            $this->info('Nothing to change: every row is already correct.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('DRY-RUN: ' . count($updates) . ' row(s) would be updated. Nothing was written.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Is the database backed up and do you want to update ' . count($updates) . ' membership row(s)?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $update) {
                DB::table('acd_class_group_students')
                    ->where('id', $update['membership_id'])
                    ->update(['class_group_id' => $update['to_id'], 'updated_at' => now()]);
            }
        });

        $dir = storage_path('app/rapor-map');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $audit = $dir . '/class-membership-fix-' . date('Ymd-His') . '.csv';
        $handle = fopen($audit, 'w');
        fputcsv($handle, ['membership_id', 'student_id', 'nis', 'semester_code', 'from_class_group_id', 'to_class_group_id'], ',', '"', '\\');
        foreach ($updates as $update) {
            fputcsv($handle, [$update['membership_id'], $update['student_id'], $update['nis'], $update['semester'], $update['from_id'], $update['to_id']], ',', '"', '\\');
        }
        fclose($handle);

        $this->info(count($updates) . " row(s) updated. Audit file (for rollback): {$audit}");

        return self::SUCCESS;
    }

    /** @return array<int, array<string, string>> */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        $header = array_map(fn($name) => strtolower(trim((string) $name)), $header ?: []);

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($line === [null] || count(array_filter($line, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }
        fclose($handle);

        return $rows;
    }

    private function plan(array $row): array
    {
        $nis = trim($row['nis'] ?? '');
        $code = trim($row['semester_code'] ?? '');
        $className = trim($row['class_name'] ?? '');
        $plan = ['nis' => $nis, 'semester' => $code, 'status' => 'error', 'message' => ''];

        $students = DB::table('acd_students')->where('nis', $nis)->pluck('id');
        if ($students->count() !== 1) {
            $plan['message'] = 'expected exactly 1 student with this NIS, found ' . $students->count();

            return $plan;
        }
        $studentId = $students->first();

        $semester = DB::table('core_semesters')->where('code', $code)->first();
        if (! $semester) {
            $plan['message'] = 'semester code not found';

            return $plan;
        }

        $targets = DB::table('acd_class_groups')->where('semester_id', $semester->id)->where('name', $className)->get(['id', 'name']);
        if ($targets->count() !== 1) {
            $plan['message'] = "expected exactly 1 class group named '{$className}' in {$code}, found " . $targets->count();

            return $plan;
        }
        $target = $targets->first();

        $memberships = DB::table('acd_class_group_students as m')
            ->join('acd_class_groups as g', 'g.id', '=', 'm.class_group_id')
            ->where('m.student_id', $studentId)->where('g.semester_id', $semester->id)
            ->get(['m.id', 'm.class_group_id', 'g.name']);

        $plan['student_id'] = $studentId;
        $plan['to'] = $target->name;
        $plan['to_id'] = $target->id;

        if ($memberships->count() === 0) {
            $plan['status'] = 'no_membership_row';
            $plan['message'] = 'the student has no membership row in this semester (this command never creates rows)';

            return $plan;
        }
        if ($memberships->count() > 1) {
            $plan['status'] = 'ambiguous';
            $plan['message'] = 'the student has more than one membership row in this semester; fix manually';

            return $plan;
        }

        $current = $memberships->first();
        $plan['from'] = $current->name;
        $plan['from_id'] = $current->class_group_id;
        $plan['membership_id'] = $current->id;

        if ($current->class_group_id === $target->id) {
            $plan['status'] = 'already_correct';

            return $plan;
        }

        $plan['status'] = 'update';

        return $plan;
    }
}
