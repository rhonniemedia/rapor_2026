<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TeachingAssignment extends Model
{
    use HasUuids;

    protected $table = 'rapor_teaching_assignments';

    protected $guarded = ['id'];
}
