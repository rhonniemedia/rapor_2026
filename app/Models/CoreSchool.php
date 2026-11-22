<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreSchool extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'core_schools';

    public static function current(): self
    {
        return static::query()->first() ?? new static();
    }

    public function headmaster(): BelongsTo
    {
        return $this->belongsTo(Data::class, 'headmaster_staff_id');
    }
}
