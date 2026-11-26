<?php

namespace App\Models\Rapor;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'rapor_subjects';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_religion_subject' => 'boolean',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            ['value' => self::STATUS_ACTIVE, 'label' => 'Aktif'],
            ['value' => self::STATUS_ARCHIVED, 'label' => 'Arsip'],
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $sub) use ($term) {
            $sub->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('religion', 'like', "%{$term}%");
        });
    }

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        if (! in_array($status, [self::STATUS_ACTIVE, self::STATUS_ARCHIVED], true)) {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function curriculumSubjects(): HasMany
    {
        return $this->hasMany(CurriculumSubject::class, 'subject_id');
    }

    public function concentrationSubjects(): HasMany
    {
        return $this->hasMany(ConcentrationSubject::class, 'subject_id');
    }

    public function classGroupSubjects(): HasMany
    {
        return $this->hasMany(ClassGroupSubject::class, 'subject_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'subject_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'subject_id');
    }

    public function isInUse(): bool
    {
        return $this->curriculumSubjects()->exists()
            || $this->concentrationSubjects()->exists()
            || $this->classGroupSubjects()->exists()
            || $this->teachingAssignments()->exists()
            || $this->scores()->exists();
    }
}
