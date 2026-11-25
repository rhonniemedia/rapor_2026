<?php

namespace App\Http\Controllers\Academics\Subjects;

use App\Http\Controllers\Controller;
use App\Models\Rapor\Subject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $curriculumId = $request->string('curriculum_id')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $perPage = $request->integer('per_page', 15);
        $perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;

        $subjects = Subject::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($curriculumId !== '', fn($query) => $query->where('curriculum_id', $curriculumId))
            ->when($status !== '', fn($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $viewName = $request->header('HX-Request') && ! $request->header('HX-Boosted')
            ? 'pages.academics.subjects._table'
            : 'pages.academics.subjects.index';

        return view($viewName, [
            'subjects' => $subjects,
            'filters' => [
                'search' => $search,
                'curriculum_id' => $curriculumId,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }
}
