<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\AcademicPeriod;
use Illuminate\Http\Request;

/** Stores the semester being viewed (session only). The active semester of the master data is never touched. */
class AcademicPeriodController extends Controller
{
    public function update(Request $request, AcademicPeriod $academicPeriod)
    {
        $request->validate(['semester_id' => ['required', 'exists:core_semesters,id']]);

        $academicPeriod->setSelected($request->input('semester_id'));

        return $this->back($request);
    }

    public function reset(Request $request, AcademicPeriod $academicPeriod)
    {
        $academicPeriod->clearSelected();

        return $this->back($request);
    }

    /** Only same-host redirects are accepted, so the parameter cannot be abused as an open redirect. */
    private function back(Request $request)
    {
        $target = (string) $request->input('redirect_to', '');
        $host = parse_url($target, PHP_URL_HOST);

        if ($target !== '' && ($host === null || $host === $request->getHost())) {
            return redirect($target);
        }

        return back();
    }
}
