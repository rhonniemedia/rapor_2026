<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreSemester extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'core_semesters';

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(CoreAcademicYear::class, 'academic_year_id');
    }

    public function next(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_id');
    }

    public function isOdd(): bool
    {
        return $this->type === 'odd';
    }

    /** Short label for the semester picker, e.g. "2025/2026 • Ganjil". */
    public function getLabelAttribute(): string
    {
        return ($this->academicYear?->name ?? '-') . ' • ' . ($this->isOdd() ? 'Ganjil' : 'Genap');
    }
}
