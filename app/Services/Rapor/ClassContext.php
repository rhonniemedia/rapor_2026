<?php

namespace App\Services\Rapor;

use App\Models\Rapor\ClassOverride;
use Illuminate\Support\Facades\DB;

/**
 * The EFFECTIVE class group of a student in a semester.
 *
 * 1. A row in rapor_class_overrides wins (used when the shared membership is known to be wrong for that
 *    semester; the shared membership itself is never changed).
 * 2. Otherwise the shared membership (acd_class_group_students) of that semester is used. When a student has
 *    several rows in one semester (moved class), the LAST class counts: the row without an exit date, or else
 *    the one with the latest exit date (then the latest entry date).
 *
 * Scores follow the student; the report only prints the subjects of the effective class.
 */
class ClassContext
{
    public function effectiveClassGroupId(string $studentId, string $semesterId): ?string
    {
        return $this->effectiveClassGroupIds([$studentId], $semesterId)[$studentId] ?? null;
    }

    /**
     * @param  array<int, string>  $studentIds
     * @return array<string, string> student id => class group id (students without any class are absent)
     */
    public function effectiveClassGroupIds(array $studentIds, string $semesterId): array
    {
        $studentIds = array_values(array_unique($studentIds));
        if ($studentIds === []) {
            return [];
        }

        $result = [];
        foreach (array_chunk($studentIds, 1000) as $chunk) {
            $result += ClassOverride::query()
                ->where('semester_id', $semesterId)->whereIn('student_id', $chunk)
                ->pluck('class_group_id', 'student_id')->all();
        }

        $remaining = array_values(array_diff($studentIds, array_keys($result)));
        foreach (array_chunk($remaining, 1000) as $chunk) {
            $rows = DB::table('acd_class_group_students as m')
                ->join('acd_class_groups as g', 'g.id', '=', 'm.class_group_id')
                ->where('g.semester_id', $semesterId)->whereIn('m.student_id', $chunk)
                ->get(['m.student_id', 'm.class_group_id', 'm.entry_date', 'm.exit_date'])
                ->groupBy('student_id');

            foreach ($rows as $studentId => $memberships) {
                $result[$studentId] = $this->lastClass($memberships)->class_group_id;
            }
        }

        return $result;
    }

    /** Students whose effective class is the given class group. */
    public function studentIdsOf(string $classGroupId, string $semesterId): array
    {
        $members = DB::table('acd_class_group_students')->where('class_group_id', $classGroupId)->pluck('student_id')->all();
        $overridden = ClassOverride::query()->where('semester_id', $semesterId)->where('class_group_id', $classGroupId)->pluck('student_id')->all();

        $candidates = array_values(array_unique(array_merge($members, $overridden)));

        return array_keys(array_filter(
            $this->effectiveClassGroupIds($candidates, $semesterId),
            fn(string $effective) => $effective === $classGroupId
        ));
    }

    /** The "last class" among the membership rows of one semester. */
    private function lastClass($memberships): object
    {
        return $memberships->sort(function ($a, $b) {
            // rows without an exit date first (they are the current class)
            $aOpen = $a->exit_date === null ? 1 : 0;
            $bOpen = $b->exit_date === null ? 1 : 0;

            return [$bOpen, (string) $b->exit_date, (string) $b->entry_date] <=> [$aOpen, (string) $a->exit_date, (string) $a->entry_date];
        })->first();
    }
}
