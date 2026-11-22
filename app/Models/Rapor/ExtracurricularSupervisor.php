<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExtracurricularSupervisor extends Model
{
    use HasUuids;

    protected $table = 'rapor_extracurricular_supervisors';

    protected $guarded = ['id'];
}
