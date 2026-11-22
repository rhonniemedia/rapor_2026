<?php

namespace App\Policies;

use App\Models\Rapor\ExtracurricularSupervisor;
use App\Models\User;
use App\Services\Rapor\RaporAccess;

/** Rights on an extracurricular in one semester: the supervisor of that semester (or an admin). */
class ExtracurricularSupervisorPolicy
{
    public function __construct(private RaporAccess $access) {}

    public function before(User $user): ?bool
    {
        return $this->access->isAdmin($user) ? true : null;
    }

    public function view(User $user, ExtracurricularSupervisor $supervisor): bool
    {
        return $user->staff_id !== null && $supervisor->staff_id === $user->staff_id;
    }

    public function enterScores(User $user, ExtracurricularSupervisor $supervisor): bool
    {
        return $user->staff_id !== null && $supervisor->staff_id === $user->staff_id;
    }
}
