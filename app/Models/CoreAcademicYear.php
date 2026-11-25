<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use App\Models\Rapor\AcademicYearCurriculum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Tabel: core_academic_years
 * (id, name, start_date, end_date, status[active|inactive|archived], next_id)
 */
class CoreAcademicYear extends Model
{
    use HasUuids, ReadOnlyModel;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'core_academic_years';

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /* ------------------------------ Relasi ------------------------------ */

    public function semesters(): HasMany
    {
        return $this->hasMany(CoreSemester::class, 'academic_year_id');
    }

    /** Kurikulum yang diaktifkan di tahun ajaran ini (tabel rapor_academic_year_curricula). */
    public function academicYearCurricula(): HasMany
    {
        return $this->hasMany(AcademicYearCurriculum::class, 'academic_year_id');
    }

    /* ------------------------------ Scope ------------------------------- */

    /**
     * Data untuk tabel "Tahun Ajaran":
     *  - classes_count  = jumlah kelas unik (tingkat + jurusan + nomor rombel).
     *    acd_class_groups dibuat per semester, jadi tanpa DISTINCT kelas yang sama
     *    akan terhitung dua kali (ganjil + genap).
     *  - students_count = jumlah siswa unik yang pernah masuk rombel di tahun ajaran ini.
     *  - semester aktif dan kurikulum di-eager-load (3 query tambahan, bukan N+1).
     */
    public function scopeForList(Builder $query): Builder
    {
        $classes = DB::table('acd_class_groups as g')
            ->join('core_semesters as s', 's.id', '=', 'g.semester_id')
            ->whereColumn('s.academic_year_id', 'core_academic_years.id')
            ->selectRaw("COUNT(DISTINCT CONCAT_WS('|', g.grade_level, g.concentration_id, g.group_number))");

        $students = DB::table('acd_class_group_students as cgs')
            ->join('acd_class_groups as g', 'g.id', '=', 'cgs.class_group_id')
            ->join('core_semesters as s', 's.id', '=', 'g.semester_id')
            ->whereColumn('s.academic_year_id', 'core_academic_years.id')
            ->selectRaw('COUNT(DISTINCT cgs.student_id)');

        return $query
            ->select('core_academic_years.*')
            ->selectSub($classes, 'classes_count')
            ->selectSub($students, 'students_count')
            ->with([
                'semesters' => fn($q) => $q->where('status', CoreSemester::STATUS_ACTIVE),
                'academicYearCurricula.curriculum',
            ])
            ->orderByDesc('start_date');
    }

    /** Cari berdasarkan nama tahun ajaran (mis. 2026/2027). */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn($q) => $q->where('core_academic_years.name', 'like', "%{$term}%"));
    }

    /* ----------------------------- Accessor ----------------------------- */

    /**
     * Status untuk tampilan: active | planned | inactive | archived.
     * "planned" diturunkan (bukan kolom DB): status inactive dan start_date belum tiba.
     */
    protected function displayStatus(): Attribute
    {
        return Attribute::get(fn() => match (true) {
            $this->status === self::STATUS_ARCHIVED => 'archived',
            $this->status === self::STATUS_ACTIVE => 'active',
            (bool) $this->start_date?->isFuture() => 'planned',
            default => 'inactive',
        });
    }

    /** Semester aktif (kalau ada). */
    protected function activeSemester(): Attribute
    {
        return Attribute::get(fn() => $this->semesters->firstWhere('status', CoreSemester::STATUS_ACTIVE));
    }
}
