<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Student (table acd_students). Identity only; encrypted details live in the vault and are loaded when needed. */
class Student extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'acd_students';

    public function concentration(): BelongsTo
    {
        return $this->belongsTo(CoreConcentration::class, 'concentration_id');
    }

    public function classGroupStudents(): HasMany
    {
        return $this->hasMany(ClassGroupStudent::class, 'student_id');
    }
}
