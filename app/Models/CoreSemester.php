<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tabel: core_semesters
 * (id, academic_year_id, type[odd|even], code, start_date, end_date, status, next_id)
 */
class CoreSemester extends Model
{
    use HasUuids, ReadOnlyModel;

    public const STATUS_ACTIVE = 'active';

    protected $table = 'core_semesters';

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /* ------------------------------ Relasi ------------------------------ */

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(CoreAcademicYear::class, 'academic_year_id');
    }

    public function next(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_id');
    }

    /* ------------------------------ Helper ------------------------------ */

    public function isOdd(): bool
    {
        return $this->type === 'odd';
    }

    /** Short label for the semester picker, e.g. "2025/2026 • Ganjil". */
    public function getLabelAttribute(): string
    {
        return ($this->academicYear?->name ?? '-') . ' • ' . ($this->isOdd() ? 'Ganjil' : 'Genap');
    }

    /* ----------------------------- Accessor ----------------------------- */

    /** odd → Ganjil, even → Genap */
    protected function label(): Attribute
    {
        return Attribute::get(fn() => $this->type === 'odd' ? 'Ganjil' : 'Genap');
    }
}
