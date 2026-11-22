<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Class group per semester (table acd_class_groups). */
class ClassGroup extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'acd_class_groups';

    public function semester(): BelongsTo
    {
        return $this->belongsTo(CoreSemester::class, 'semester_id');
    }

    public function concentration(): BelongsTo
    {
        return $this->belongsTo(CoreConcentration::class, 'concentration_id');
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(Data::class, 'homeroom_teacher_id');
    }

    public function classGroupStudents(): HasMany
    {
        return $this->hasMany(ClassGroupStudent::class, 'class_group_id');
    }
}
