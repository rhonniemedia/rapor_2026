<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Tabel: rapor_academic_year_curricula (stub, dilengkapi di fitur berikutnya) */
class AcademicYearCurriculum extends Model
{
    use HasUuids;

    protected $table = 'rapor_academic_year_curricula';

    protected $fillable = ['curriculum_id', 'academic_year_id', 'status'];
}
