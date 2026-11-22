<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassGroupSubject extends Model
{
    use HasUuids;

    protected $table = 'rapor_class_group_subjects';

    protected $guarded = ['id'];
}
