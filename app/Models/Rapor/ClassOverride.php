<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClassOverride extends Model
{
    use HasUuids;

    protected $table = 'rapor_class_overrides';

    protected $guarded = ['id'];
}
