<?php

namespace App\Http\Controllers\Academics\Curriculum;

use App\Http\Controllers\Controller;
use App\Models\CoreAcademicYear;
use App\Models\Rapor\Curriculum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CurriculumController extends Controller
{
    private const VIEW = 'pages.academics.relations';
    private const PER_PAGE = 10;

    /* ------------------------------------------------------------------ */
    /*  Halaman daftar                                                    */
    /* ------------------------------------------------------------------ */

    public function index(Request $request)
    {
        $filters = $this->filters($request->query());

        return view(self::VIEW . '.index', [
            'curricula' => $this->paginate($filters),
            'academicYears' => $request->headers->has('HX-Request') ? collect() : CoreAcademicYear::forList()->get(),
            'search' => $filters['search'],
            'filterStatus' => $filters['status'],
            'statusOptions' => Curriculum::statusOptions(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Modal tambah / edit / hapus (dimuat lewat hx-get ke #modal-container) */
    /* ------------------------------------------------------------------ */

    public function create()
    {
        return view(self::VIEW . '.curriculum._modal-form', [
            'curriculum' => new Curriculum(['status' => Curriculum::STATUS_ACTIVE]),
            'statusOptions' => Curriculum::statusOptions(),
        ]);
    }

    public function edit(Curriculum $curriculum)
    {
        return view(self::VIEW . '.curriculum._modal-form', [
            'curriculum' => $curriculum,
            'statusOptions' => Curriculum::statusOptions(),
        ]);
    }

    public function confirmDelete(Curriculum $curriculum)
    {
        $curriculum->loadCount(['academicYearCurricula', 'curriculumSubjects']);

        return view(self::VIEW . '.curriculum._modal-delete', compact('curriculum'));
    }

    /* ------------------------------------------------------------------ */
    /*  Aksi tulis. Respons = fragmen tabel (#curricula-container)        */
    /* ------------------------------------------------------------------ */

    public function store(Request $request)
    {
        Curriculum::create($request->validated());

        return $this->tableResponse($request, $this->notify('Kurikulum berhasil ditambahkan.'));
    }

    public function update(Request $request, Curriculum $curriculum)
    {
        $curriculum->update($request->validated());

        return $this->tableResponse($request, $this->notify('Kurikulum berhasil diperbarui.'));
    }

    public function toggleStatus(Request $request, Curriculum $curriculum)
    {
        $curriculum->update([
            'status' => $curriculum->isActive() ? Curriculum::STATUS_ARCHIVED : Curriculum::STATUS_ACTIVE,
        ]);

        $message = $curriculum->isActive() ? 'Kurikulum diaktifkan.' : 'Kurikulum diarsipkan.';

        return $this->tableResponse($request, $this->notify($message, 'success', false));
    }

    public function destroy(Request $request, Curriculum $curriculum)
    {
        // FK academic_year_curricula & curriculum_subjects = ON DELETE CASCADE,
        // jadi kurikulum yang sudah dipakai di tahun ajaran tidak boleh dihapus
        // (class_group_curricula ikut terhapus lewat cascade berantai).
        if ($curriculum->academicYearCurricula()->exists()) {
            return response('', 200)->withHeaders(
                ['HX-Reswap' => 'none'] + $this->notify('Kurikulum sudah dipakai di tahun ajaran. Arsipkan saja.', 'error')
            );
        }

        $curriculum->delete();

        return $this->tableResponse($request, $this->notify('Kurikulum berhasil dihapus.'));
    }

    /* ------------------------------------------------------------------ */
    /*  Helper                                                            */
    /* ------------------------------------------------------------------ */

    /** Normalisasi query string: search, filter_status, page. */
    private function filters(array $query): array
    {
        $status = $query['filter_status'] ?? '';

        return [
            'search' => trim((string) ($query['search'] ?? '')),
            'status' => in_array($status, [Curriculum::STATUS_ACTIVE, Curriculum::STATUS_ARCHIVED], true) ? $status : '',
            'page' => max(1, (int) ($query['page'] ?? 1)),
        ];
    }

    private function paginate(array $filters): LengthAwarePaginator
    {
        return Curriculum::query()
            ->withCount(['academicYearCurricula', 'curriculumSubjects'])
            ->search($filters['search'])
            ->ofStatus($filters['status'])
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'page', $filters['page'])
            ->withPath(route('rapor.curricula.index'))
            ->appends(array_filter([
                'search' => $filters['search'],
                'filter_status' => $filters['status'],
            ]));
    }

    /**
     * Render ulang tabel memakai filter/halaman yang sedang dilihat user
     * (dibaca dari header HX-Current-URL), supaya posisi list tidak reset.
     */
    private function tableResponse(Request $request, array $headers = [])
    {
        parse_str((string) parse_url((string) $request->header('HX-Current-URL'), PHP_URL_QUERY), $query);
        $filters = $this->filters($query);

        $list = $this->paginate($filters);

        // Setelah hapus, halaman terakhir bisa kosong → mundur ke halaman terakhir yang ada.
        if ($list->isEmpty() && $list->currentPage() > 1) {
            $filters['page'] = $list->lastPage();
            $list = $this->paginate($filters);
        }

        return response()
            ->view(self::VIEW . '.curriculum._table', [
                'curricula' => $list,
                'listUrl' => $list->url($list->currentPage()),
            ])
            ->withHeaders($headers);
    }

    /** Header HX-Trigger: event "notify" (toast) dan "close-modal". */
    private function notify(string $message, string $type = 'success', bool $closeModal = true): array
    {
        $events = ['notify' => ['type' => $type, 'message' => $message]];

        if ($closeModal) {
            $events['close-modal'] = true;
        }

        return ['HX-Trigger' => json_encode($events)];
    }
}
