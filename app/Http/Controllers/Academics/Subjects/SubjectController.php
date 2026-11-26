<?php

namespace App\Http\Controllers\Academics\Subjects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rapor\SubjectRequest;
use App\Models\Rapor\Subject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    private const VIEW = 'pages.academics.subjects';
    private const PER_PAGE = 10;

    public function index(Request $request)
    {
        $filters = $this->filters($request->query());

        return view(self::VIEW . '.index', [
            'subjects' => $this->paginate($filters),
            'search' => $filters['search'],
            'filterStatus' => $filters['status'],
            'filterCategory' => $filters['category'],
            'statusOptions' => Subject::statusOptions(),
        ]);
    }

    public function create()
    {
        return view(self::VIEW . '.subject._modal-form', [
            'subject' => new Subject([
                'status' => Subject::STATUS_ACTIVE,
                'is_religion_subject' => false,
            ]),
            'statusOptions' => Subject::statusOptions(),
        ]);
    }

    public function edit(Subject $subject)
    {
        return view(self::VIEW . '.subject._modal-form', [
            'subject' => $subject,
            'statusOptions' => Subject::statusOptions(),
        ]);
    }

    public function confirmDelete(Subject $subject)
    {
        $subject->loadCount([
            'curriculumSubjects',
            'concentrationSubjects',
            'classGroupSubjects',
            'teachingAssignments',
            'scores',
        ]);

        return view(self::VIEW . '.subject._modal-delete', compact('subject'));
    }

    public function store(SubjectRequest $request)
    {
        Subject::create($request->validated());

        return $this->tableResponse($request, $this->notify('Mata pelajaran berhasil ditambahkan.'));
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $subject->update($request->validated());

        return $this->tableResponse($request, $this->notify('Mata pelajaran berhasil diperbarui.'));
    }

    public function toggleStatus(Request $request, Subject $subject)
    {
        $subject->update([
            'status' => $subject->isActive() ? Subject::STATUS_ARCHIVED : Subject::STATUS_ACTIVE,
        ]);

        $message = $subject->isActive()
            ? 'Mata pelajaran diaktifkan.'
            : 'Mata pelajaran diarsipkan.';

        return $this->tableResponse($request, $this->notify($message, 'success', false));
    }

    public function destroy(Request $request, Subject $subject)
    {
        if ($subject->isInUse()) {
            return response('', 200)->withHeaders(
                ['HX-Reswap' => 'none'] + $this->notify(
                    'Mata pelajaran sudah dipakai pada data akademik/penilaian. Arsipkan saja.',
                    'error'
                )
            );
        }

        $subject->delete();

        return $this->tableResponse($request, $this->notify('Mata pelajaran berhasil dihapus.'));
    }

    private function filters(array $query): array
    {
        $status = $query['filter_status'] ?? '';
        $category = $query['filter_category'] ?? '';

        return [
            'search' => trim((string) ($query['search'] ?? '')),
            'status' => in_array($status, [Subject::STATUS_ACTIVE, Subject::STATUS_ARCHIVED], true) ? $status : '',
            'category' => in_array($category, ['general', 'religion'], true) ? $category : '',
            'page' => max(1, (int) ($query['page'] ?? 1)),
        ];
    }

    private function listParams(array $filters): array
    {
        return array_filter([
            'search' => $filters['search'],
            'filter_status' => $filters['status'],
            'filter_category' => $filters['category'],
        ]);
    }

    private function paginate(array $filters): LengthAwarePaginator
    {
        return Subject::query()
            ->search($filters['search'])
            ->ofStatus($filters['status'])
            ->when($filters['category'] === 'general', fn($q) => $q->where('is_religion_subject', false))
            ->when($filters['category'] === 'religion', fn($q) => $q->where('is_religion_subject', true))
            ->orderByRaw("status = 'active' desc")
            ->orderBy('name')
            ->paginate(self::PER_PAGE, ['*'], 'page', $filters['page'])
            ->withPath(route('academics.subjects.index'))
            ->appends($this->listParams($filters));
    }

    private function tableResponse(Request $request, array $headers = [])
    {
        parse_str((string) parse_url((string) $request->header('HX-Current-URL'), PHP_URL_QUERY), $query);
        $filters = $this->filters($query);

        $list = $this->paginate($filters);

        if ($list->isEmpty() && $list->currentPage() > 1) {
            $filters['page'] = $list->lastPage();
            $list = $this->paginate($filters);
        }

        return response()
            ->view(self::VIEW . '.subject._table', [
                'subjects' => $list,
                'listUrl' => $list->url($list->currentPage()),
            ])
            ->withHeaders($headers);
    }

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
