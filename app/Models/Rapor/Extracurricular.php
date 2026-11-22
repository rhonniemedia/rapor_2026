<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Extracurricular extends Model
{
    use HasUuids;

    protected $table = 'rapor_extracurriculars';

    protected $guarded = ['id'];
}
