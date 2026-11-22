<?php

namespace App\Services;

use App\Models\CoreSemester;
use Illuminate\Support\Facades\Session;

/**
 * Determines the semester that is currently being VIEWED (a per-session preference).
 *
 * - When the user picked a semester in the topbar, that one is used (kept in the session).
 * - Otherwise the semester with status 'active' in the shared master data is used.
 *
 * It never changes the active semester of the master data. Rights (who may enter what) are NOT decided by
 * this service: they are bound to the semester of the class group itself (see RaporAccess).
 */
class AcademicPeriod
{
    private const SESSION_KEY = 'academic_period.semester_id';

    private ?CoreSemester $resolved = null;
    private bool $isResolved = false;

    public function current(): ?CoreSemester
    {
        if ($this->isResolved) {
            return $this->resolved;
        }
        $this->isResolved = true;

        $selectedId = Session::get(self::SESSION_KEY);
        if ($selectedId) {
            $semester = CoreSemester::with('academicYear')->find($selectedId);
            if ($semester) {
                return $this->resolved = $semester;
            }
            Session::forget(self::SESSION_KEY);
        }

        return $this->resolved = $this->active();
    }

    public function currentId(): ?string
    {
        return $this->current()?->id;
    }

    /** The semester that is active in the master data. */
    public function active(): ?CoreSemester
    {
        return CoreSemester::with('academicYear')->where('status', 'active')->first();
    }

    /** True when the user is looking at a semester other than the active one. */
    public function isOverridden(): bool
    {
        $current = $this->current();
        $active = $this->active();

        return $current && $active && $current->id !== $active->id;
    }

    public function setSelected(string $semesterId): void
    {
        Session::put(self::SESSION_KEY, $semesterId);
        $this->resolved = null;
        $this->isResolved = false;
    }

    public function clearSelected(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->resolved = null;
        $this->isResolved = false;
    }

    /** Semesters for the topbar picker, newest first. */
    public function options()
    {
        return CoreSemester::with('academicYear')->orderByDesc('code')->get();
    }
}
