<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Tabel: rapor_curriculum_subjects (stub, dilengkapi di fitur berikutnya) */
class CurriculumSubject extends Model
{
    use HasUuids;

    protected $table = 'rapor_curriculum_subjects';

    protected $fillable = ['curriculum_id', 'subject_id', 'subject_group_id', 'grade_level', 'sort_order'];
}
