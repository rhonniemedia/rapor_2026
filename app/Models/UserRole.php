<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'user_roles';
}
