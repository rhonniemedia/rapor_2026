<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Staff profile (table staff_data). */
class Data extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'staff_data';

    public function vault(): HasOne
    {
        return $this->hasOne(DataVault::class, 'staff_id');
    }

    /** Example: Budi Santoso, S.Pd., M.Kom. */
    public function getNameWithTitleAttribute(): string
    {
        $front = $this->front_title ? $this->front_title . ' ' : '';
        $back = $this->back_title ? ', ' . $this->back_title : '';

        return $front . $this->name . $back;
    }
}
