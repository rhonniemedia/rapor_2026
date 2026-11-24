<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabel: rapor_curricula (id char36, name, code, description, status, timestamps)
 */
class Curriculum extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'rapor_curricula';

    protected $fillable = ['name', 'code', 'description', 'status'];

    /** Format opsi untuk <x-ui.select>. */
    public static function statusOptions(): array
    {
        return [
            ['value' => self::STATUS_ACTIVE, 'label' => 'Aktif'],
            ['value' => self::STATUS_ARCHIVED, 'label' => 'Diarsipkan'],
        ];
    }

    public function academicYearCurricula(): HasMany
    {
        return $this->hasMany(AcademicYearCurriculum::class, 'curriculum_id');
    }

    public function curriculumSubjects(): HasMany
    {
        return $this->hasMany(CurriculumSubject::class, 'curriculum_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn($q) => $q->where(
            fn($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")
        ));
    }

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn($q) => $q->where('status', $status));
    }
}
