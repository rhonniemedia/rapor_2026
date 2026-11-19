<?php
// Run from the project root:  php artisan tinker student-check.php
// Diagnoses the students flagged by rapor:map. Prints NO names and NO NISN, only NIS, class names and YES/NO.

use App\Support\Rapor\LegacyCrypt;
use App\Traits\HasBlindIndex;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

$ids = [
    'c67fe976-94f4-11f0-a641-bc2411077983', // class differs (X TEI)
    'c6ed5386-94f4-11f0-a641-bc2411077983', // class differs (X TITL3)
    'c75811e3-94f4-11f0-a641-bc2411077983', // class differs (X TPTL)
    'f9d1aa7c-a325-11ef-9f32-bc2411077983', // class differs (XI TKJ2)
    'f9d2294a-a325-11ef-9f32-bc2411077983', // class differs (XI TKR1)
    'f7fa30cc-a325-11ef-9f32-bc2411077983', // date of birth differs
    'f993f97f-a325-11ef-9f32-bc2411077983', // date of birth differs
    'f8a14290-a325-11ef-9f32-bc2411077983', // no PINTAR match
];

$legacy = new LegacyCrypt();
$hasher = new class {
    use HasBlindIndex;

    public function hash(string $value): string
    {
        return $this->blindIndexHash($value);
    }
};

$classesOf = fn(string $studentId) => DB::table('acd_class_group_students as m')
    ->join('acd_class_groups as g', 'g.id', '=', 'm.class_group_id')
    ->join('core_semesters as s', 's.id', '=', 'g.semester_id')
    ->where('m.student_id', $studentId)->whereIn('s.code', ['2025-1', '2025-2'])
    ->orderBy('s.code')->pluck('g.name', 's.code')->all();

$dobRelation = function (?string $a, ?string $b): string {
    if ($a === null || $b === null) {
        return 'unknown';
    }
    [$y1, $m1, $d1] = array_pad(explode('-', substr($a, 0, 10)), 3, '');
    [$y2, $m2, $d2] = array_pad(explode('-', substr($b, 0, 10)), 3, '');
    if ($y1 . $m1 . $d1 === $y2 . $m2 . $d2) {
        return 'same';
    }
    if ($y1 === $y2 && $m1 === $d2 && $d1 === $m2) {
        return 'day_month_swapped';
    }

    return $y1 === $y2 ? 'same_year_other_date' : 'different_year';
};

foreach ($ids as $id) {
    $p = DB::table('old_pelajars')->where('id', $id)->first();
    if (! $p) {
        echo substr($id, 0, 8) . " not found in old_pelajars\n";

        continue;
    }

    $rombels = DB::table('old_rombel_pelajars as rp')->join('old_rombels as r', 'r.id', '=', 'rp.rombel_id')
        ->where('rp.pelajar_id', $id)->pluck('r.nama')->implode(',');

    echo substr($id, 0, 8) . ' old_nis=' . $p->nomor_induk . ' old_rombel=' . ($rombels ?: '-');

    $vault = DB::table('acd_students_vault')->where('nisn_hash', $hasher->hash($legacy->plain($p->nisn)))->first();

    if ($vault) {
        $s = DB::table('acd_students')->where('id', $vault->student_id)->first();
        $sameName = mb_strtolower(trim($p->nama_lengkap)) === mb_strtolower(trim($s->name));

        try {
            $dob = $dobRelation(substr((string) $legacy->serialized($p->tanggal_lahir), 0, 10), Crypt::decryptString($vault->dob_encrypted));
        } catch (\Throwable $e) {
            $dob = 'unreadable';
        }

        echo ' | NISN -> pintar_nis=' . $s->nis . ' same_name=' . ($sameName ? 'YES' : 'NO')
            . ' pintar_classes=' . json_encode($classesOf($s->id)) . ' dob=' . $dob . "\n";
    } else {
        echo ' | NISN -> no PINTAR student';
        $hits = DB::table('acd_students')->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($p->nama_lengkap))])->get(['id', 'nis']);
        echo ' name_hits=' . $hits->count();
        foreach ($hits as $h) {
            $v = DB::table('acd_students_vault')->where('student_id', $h->id)->first();
            echo ' [pintar_nis=' . $h->nis . ' has_vault=' . ($v ? 'YES' : 'NO') . ' has_nisn_hash=' . (($v->nisn_hash ?? null) ? 'YES' : 'NO')
                . ' classes=' . json_encode($classesOf($h->id)) . ']';
        }
        echo "\n";
    }
}
