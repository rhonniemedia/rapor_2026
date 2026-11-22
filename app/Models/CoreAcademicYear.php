<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreAcademicYear extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'core_academic_years';

    public function semesters(): HasMany
    {
        return $this->hasMany(CoreSemester::class, 'academic_year_id');
    }
}
