<?php

namespace App\Http\Controllers\Academics\Curriculum;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rapor\CurriculumRequest;
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
        $query = $request->query();
        $filters = $this->filters($query);
        $ay = $this->academicYearFilters($query);

        // Kedua daftar selalu dimuat: pencarian HTMX memakai hx-select pada respons halaman penuh,
        // jadi #curricula-container dan #academic-years-container harus ada di setiap respons.
        return view(self::VIEW . '.index', [
            'curricula' => $this->paginate($filters, $ay),
            'academicYears' => $this->paginateAcademicYears($filters, $ay),
            'search' => $filters['search'],
            'filterStatus' => $filters['status'],
            'ayearSearch' => $ay['search'],
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
    /*  Gagal validasi ditangani CurriculumRequest::failedValidation().   */
    /* ------------------------------------------------------------------ */

    public function store(CurriculumRequest $request)
    {
        Curriculum::create($request->validated());

        return $this->tableResponse($request, $this->notify('Kurikulum berhasil ditambahkan.'));
    }

    public function update(CurriculumRequest $request, Curriculum $curriculum)
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

    /** Normalisasi query string kurikulum: search, filter_status, page. */
    private function filters(array $query): array
    {
        $status = $query['filter_status'] ?? '';

        return [
            'search' => trim((string) ($query['search'] ?? '')),
            'status' => in_array($status, [Curriculum::STATUS_ACTIVE, Curriculum::STATUS_ARCHIVED], true) ? $status : '',
            'page' => max(1, (int) ($query['page'] ?? 1)),
        ];
    }

    /** Normalisasi query string tahun ajaran: ay_search, ay_page. */
    private function academicYearFilters(array $query): array
    {
        return [
            'search' => trim((string) ($query['ay_search'] ?? '')),
            'page' => max(1, (int) ($query['ay_page'] ?? 1)),
        ];
    }

    /** Parameter yang dipertahankan di semua link paginasi. */
    private function listParams(array $filters, array $ay): array
    {
        return array_filter([
            'search' => $filters['search'],
            'filter_status' => $filters['status'],
            'ay_search' => $ay['search'],
        ]);
    }

    private function paginate(array $filters, array $ay = ['search' => '', 'page' => 1]): LengthAwarePaginator
    {
        return Curriculum::query()
            ->withCount(['academicYearCurricula', 'curriculumSubjects'])
            ->search($filters['search'])
            ->ofStatus($filters['status'])
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'page', $filters['page'])
            ->withPath(route('rapor.curricula.index'))
            ->appends($this->listParams($filters, $ay));
    }

    private function paginateAcademicYears(array $filters, array $ay): LengthAwarePaginator
    {
        return CoreAcademicYear::forList()
            ->search($ay['search'])
            ->paginate(self::PER_PAGE, ['*'], 'ay_page', $ay['page'])
            ->withPath(route('rapor.curricula.index'))
            ->appends($this->listParams($filters, $ay));
    }

    /**
     * Render ulang tabel memakai filter/halaman yang sedang dilihat user
     * (dibaca dari header HX-Current-URL), supaya posisi list tidak reset.
     */
    private function tableResponse(Request $request, array $headers = [])
    {
        parse_str((string) parse_url((string) $request->header('HX-Current-URL'), PHP_URL_QUERY), $query);
        $filters = $this->filters($query);
        $ay = $this->academicYearFilters($query);

        $list = $this->paginate($filters, $ay);

        // Setelah hapus, halaman terakhir bisa kosong → mundur ke halaman terakhir yang ada.
        if ($list->isEmpty() && $list->currentPage() > 1) {
            $filters['page'] = $list->lastPage();
            $list = $this->paginate($filters, $ay);
        }

        return response()
            ->view(self::VIEW . '.curriculum._table', [
                'curricula' => $list,
                'listUrl' => $list->url($list->currentPage()),
            ])
            ->withHeaders($headers);
    }

    /**
     * Header HX-Trigger: event "showAlert" (dijembatani layout app.blade.php ke window.ShowAlert)
     * dan "close-modal" (didengar partial modal).
     */
    private function notify(string $message, string $type = 'success', bool $closeModal = true): array
    {
        $events = [
            'showAlert' => [
                'type' => $type,
                'title' => $type === 'success' ? 'Berhasil' : 'Gagal',
                'message' => $message,
            ],
        ];

        if ($closeModal) {
            $events['close-modal'] = true;
        }

        return ['HX-Trigger' => json_encode($events)];
    }
}
