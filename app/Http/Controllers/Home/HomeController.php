<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Services\AcademicPeriod;
use App\Services\Rapor\RaporAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Home page. Content depends on the role: admin overview, or the classes/subjects of a teacher. */
class HomeController extends Controller
{
    public function index(Request $request, AcademicPeriod $period, RaporAccess $access)
    {
        $user = $request->user();
        $semester = $period->current();

        $data = [
            'semester' => $semester,
            'role' => $access->role($user),
            'isAdmin' => $access->isAdmin($user),
            'hasStaff' => $user->staff_id !== null,
            'teaching' => collect(),
            'homeroom' => collect(),
            'supervised' => collect(),
            'stats' => null,
        ];

        if (! $semester) {
            return view('pages.home.index', $data);
        }

        $data['teaching'] = $access->teachingAssignments($user, $semester->id)->groupBy('class_group_name');
        $data['homeroom'] = $access->homeroomClassGroups($user, $semester->id);
        $data['supervised'] = $access->supervisedExtracurriculars($user, $semester->id);

        if ($data['isAdmin']) {
            $data['stats'] = [
                'class_groups' => DB::table('acd_class_groups')->where('semester_id', $semester->id)->count(),
                'students' => DB::table('acd_class_group_students as m')->join('acd_class_groups as g', 'g.id', '=', 'm.class_group_id')
                    ->where('g.semester_id', $semester->id)->distinct()->count('m.student_id'),
                'assignments' => DB::table('rapor_teaching_assignments as t')->join('acd_class_groups as g', 'g.id', '=', 't.class_group_id')
                    ->where('g.semester_id', $semester->id)->count(),
                'assignments_without_teacher' => DB::table('rapor_teaching_assignments as t')->join('acd_class_groups as g', 'g.id', '=', 't.class_group_id')
                    ->where('g.semester_id', $semester->id)->whereNull('t.staff_id')->count(),
                'scores' => DB::table('rapor_scores')->where('semester_id', $semester->id)->whereNull('deleted_at')->count(),
            ];
        }

        return view('pages.home.index', $data);
    }
}
