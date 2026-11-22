<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasUuids;

    protected $table = 'rapor_subjects';

    protected $guarded = ['id'];
}
