<?php

namespace App\Services\Rapor;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for who may do what in the report card app.
 *
 * Only three roles are stored (shared user_roles): superadmin, admin, user. "Subject teacher" and
 * "homeroom teacher" are NOT stored; they are derived per semester from the data:
 *   - subject teacher  = a row in rapor_teaching_assignments for that class group and subject,
 *   - homeroom teacher = acd_class_groups.homeroom_teacher_id of that class group.
 * One person can be both. Admins and superadmins may enter and correct everything.
 *
 * Policies and Gates only call this class, so the rules live in one place. Results are memoised per request.
 */
class RaporAccess
{
    private array $roles = [];
    private array $teaches = [];
    private array $homeroom = [];
    private array $supervises = [];

    /** Role of the user for THIS app: 'superadmin' | 'admin' | 'user' | null (no access). */
    public function role(User $user): ?string
    {
        if (! array_key_exists($user->id, $this->roles)) {
            $order = config('rapor.roles', ['superadmin', 'admin', 'user']);
            $names = DB::table('user_app_roles as ur')
                ->join('user_roles as r', 'r.id', '=', 'ur.role_id')
                ->where('ur.user_id', $user->id)->where('ur.app_id', config('rapor.core_id'))
                ->pluck('r.name')->all();

            $this->roles[$user->id] = collect($order)->first(fn($role) => in_array($role, $names, true));
        }

        return $this->roles[$user->id];
    }

    public function hasAccess(User $user): bool
    {
        return $this->role($user) !== null;
    }

    public function isAdmin(User $user): bool
    {
        return in_array($this->role($user), ['superadmin', 'admin'], true);
    }

    /** Can the user use the working features at all? Needs a role, and a staff record unless admin. */
    public function canUse(User $user): bool
    {
        return $this->hasAccess($user) && ($this->isAdmin($user) || $user->staff_id !== null);
    }

    public function teaches(User $user, string $classGroupId, string $subjectId): bool
    {
        if ($user->staff_id === null) {
            return false;
        }

        $key = "{$user->staff_id}|{$classGroupId}|{$subjectId}";

        return $this->teaches[$key] ??= DB::table('rapor_teaching_assignments')
            ->where('staff_id', $user->staff_id)->where('class_group_id', $classGroupId)->where('subject_id', $subjectId)->exists();
    }

    public function isHomeroomOf(User $user, string $classGroupId): bool
    {
        if ($user->staff_id === null) {
            return false;
        }

        $key = "{$user->staff_id}|{$classGroupId}";

        return $this->homeroom[$key] ??= DB::table('acd_class_groups')
            ->where('id', $classGroupId)->where('homeroom_teacher_id', $user->staff_id)->exists();
    }

    public function supervises(User $user, string $extracurricularId, string $semesterId): bool
    {
        if ($user->staff_id === null) {
            return false;
        }

        $key = "{$user->staff_id}|{$extracurricularId}|{$semesterId}";

        return $this->supervises[$key] ??= DB::table('rapor_extracurricular_supervisors')
            ->where('staff_id', $user->staff_id)->where('extracurricular_id', $extracurricularId)->where('semester_id', $semesterId)->exists();
    }

    /** Subject assignments of the user in one semester (for the home page and menus). */
    public function teachingAssignments(User $user, string $semesterId)
    {
        if ($user->staff_id === null) {
            return collect();
        }

        return DB::table('rapor_teaching_assignments as t')
            ->join('acd_class_groups as g', 'g.id', '=', 't.class_group_id')
            ->join('rapor_subjects as s', 's.id', '=', 't.subject_id')
            ->where('t.staff_id', $user->staff_id)->where('g.semester_id', $semesterId)
            ->orderBy('g.name')->orderBy('s.name')
            ->get(['t.id', 't.class_group_id', 'g.name as class_group_name', 't.subject_id', 's.name as subject_name']);
    }

    /** Class groups the user is homeroom teacher of in one semester. */
    public function homeroomClassGroups(User $user, string $semesterId)
    {
        if ($user->staff_id === null) {
            return collect();
        }

        return DB::table('acd_class_groups')
            ->where('semester_id', $semesterId)->where('homeroom_teacher_id', $user->staff_id)
            ->orderBy('name')->get(['id', 'name']);
    }

    /** Extracurriculars the user supervises in one semester. */
    public function supervisedExtracurriculars(User $user, string $semesterId)
    {
        if ($user->staff_id === null) {
            return collect();
        }

        return DB::table('rapor_extracurricular_supervisors as es')
            ->join('rapor_extracurriculars as e', 'e.id', '=', 'es.extracurricular_id')
            ->where('es.staff_id', $user->staff_id)->where('es.semester_id', $semesterId)
            ->orderBy('e.name')->get(['e.id', 'e.name']);
    }
}
