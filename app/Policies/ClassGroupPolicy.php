<?php

namespace App\Policies;

use App\Models\ClassGroup;
use App\Models\User;
use App\Services\Rapor\RaporAccess;

/**
 * Rights on a class group. They always apply to the semester of the class group itself,
 * never to the semester that happens to be selected in the topbar.
 *
 * Homeroom teacher (of THIS class group): sees all scores of the class (read-only for subjects of other
 * teachers), enters attendance, cocurricular and homeroom notes, previews and prints the report.
 */
class ClassGroupPolicy
{
    public function __construct(private RaporAccess $access) {}

    /** Admins and superadmins may do everything on every class group. */
    public function before(User $user): ?bool
    {
        return $this->access->isAdmin($user) ? true : null;
    }

    public function view(User $user, ClassGroup $classGroup): bool
    {
        return $this->access->isHomeroomOf($user, $classGroup->id);
    }

    public function viewScores(User $user, ClassGroup $classGroup): bool
    {
        return $this->access->isHomeroomOf($user, $classGroup->id);
    }

    public function manageHomeroom(User $user, ClassGroup $classGroup): bool
    {
        return $this->access->isHomeroomOf($user, $classGroup->id);
    }

    public function printReport(User $user, ClassGroup $classGroup): bool
    {
        return $this->access->isHomeroomOf($user, $classGroup->id);
    }
}
