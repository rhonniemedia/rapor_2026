<?php

namespace App\Models\Rapor;

use App\Models\CoreAcademicYear;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabel: rapor_academic_year_curricula */
class AcademicYearCurriculum extends Model
{
    use HasUuids;

    protected $table = 'rapor_academic_year_curricula';

    protected $fillable = ['curriculum_id', 'academic_year_id', 'status'];

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(CoreAcademicYear::class, 'academic_year_id');
    }
}
