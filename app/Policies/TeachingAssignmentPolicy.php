<?php

namespace App\Policies;

use App\Models\Rapor\TeachingAssignment;
use App\Models\User;
use App\Services\Rapor\RaporAccess;

/** Rights on one subject in one class group: only the assigned teacher (or an admin) may enter its scores. */
class TeachingAssignmentPolicy
{
    public function __construct(private RaporAccess $access) {}

    public function before(User $user): ?bool
    {
        return $this->access->isAdmin($user) ? true : null;
    }

    public function view(User $user, TeachingAssignment $assignment): bool
    {
        return $user->staff_id !== null && $assignment->staff_id === $user->staff_id;
    }

    public function enterScores(User $user, TeachingAssignment $assignment): bool
    {
        return $user->staff_id !== null && $assignment->staff_id === $user->staff_id;
    }
}
