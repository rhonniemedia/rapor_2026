<?php

namespace App\Providers;

use App\Models\ClassGroup;
use App\Models\Rapor\ExtracurricularSupervisor;
use App\Models\Rapor\TeachingAssignment;
use App\Models\User;
use App\Policies\ClassGroupPolicy;
use App\Policies\ExtracurricularSupervisorPolicy;
use App\Policies\TeachingAssignmentPolicy;
use App\Services\AcademicPeriod;
use App\Services\Rapor\ClassContext;
use App\Services\Rapor\RaporAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request: they memoise lookups.
        $this->app->scoped(AcademicPeriod::class);
        $this->app->scoped(RaporAccess::class);
        $this->app->scoped(ClassContext::class);
    }

    public function boot(): void
    {
        Gate::policy(ClassGroup::class, ClassGroupPolicy::class);
        Gate::policy(TeachingAssignment::class, TeachingAssignmentPolicy::class);
        Gate::policy(ExtracurricularSupervisor::class, ExtracurricularSupervisorPolicy::class);

        // Rights that are not tied to one object.
        Gate::define('use-rapor', fn(User $user) => app(RaporAccess::class)->canUse($user));
        Gate::define('manage-rapor', fn(User $user) => app(RaporAccess::class)->isAdmin($user));
    }
}
