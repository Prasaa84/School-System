<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardSummaryController extends Controller
{
    use AppliesSchoolScope;

    public function __invoke(): JsonResponse
    {
        $user = $this->authUser();

        $studentsLatestYear = null;
        $gradesLatestYear = null;
        $classesLatestYear = null;

        $studentsTotal = 0;
        if (Schema::hasTable('student_grade_class_tbl')) {
            $sgcColumns = Schema::getColumnListing('student_grade_class_tbl');
            $sgcSchoolColumn = $this->resolveSchoolColumn($sgcColumns);

            $studentYearQuery = DB::table('student_grade_class_tbl as sgc');
            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $studentYearQuery->where('sgc.is_deleted', 0);
            }
            $this->applySchoolScope($studentYearQuery, $user, 'sgc', $sgcSchoolColumn);
            $studentsLatestYear = $studentYearQuery->max('year');

            if ($studentsLatestYear !== null) {
                $studentCountQuery = DB::table('student_grade_class_tbl as sgc')->where('sgc.year', $studentsLatestYear);
                if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                    $studentCountQuery->where('sgc.is_deleted', 0);
                }
                $this->applySchoolScope($studentCountQuery, $user, 'sgc', $sgcSchoolColumn);
                $studentsTotal = (int) $studentCountQuery->distinct('sgc.index_no')->count('sgc.index_no');
            }
        }

        if ($studentsTotal === 0 && Schema::hasTable('student_tbl')) {
            $studentColumns = Schema::getColumnListing('student_tbl');
            $studentSchoolColumn = $this->resolveSchoolColumn($studentColumns);

            $studentsFallback = DB::table('student_tbl as st');
            if (Schema::hasColumn('student_tbl', 'is_deleted')) {
                $studentsFallback->where('st.is_deleted', 0);
            }
            $this->applySchoolScope($studentsFallback, $user, 'st', $studentSchoolColumn);
            $studentsTotal = (int) $studentsFallback->count();
        }

        $staffTotal = 0;
        if (Schema::hasTable('staff_tbl')) {
            $staffColumns = Schema::getColumnListing('staff_tbl');
            $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);

            $staffQuery = DB::table('staff_tbl as st');
            if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
                $staffQuery->where('st.is_deleted', 0);
            }
            $this->applySchoolScope($staffQuery, $user, 'st', $staffSchoolColumn);
            $staffTotal = (int) $staffQuery->count();
        }

        $gradesTotal = 0;
        if (Schema::hasTable('school_grade_tbl')) {
            $gradeColumns = Schema::getColumnListing('school_grade_tbl');
            $gradeSchoolColumn = $this->resolveSchoolColumn($gradeColumns);

            $gradeYearQuery = DB::table('school_grade_tbl as sgt');
            if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
                $gradeYearQuery->where('sgt.is_deleted', 0);
            }
            $this->applySchoolScope($gradeYearQuery, $user, 'sgt', $gradeSchoolColumn);
            $gradesLatestYear = $gradeYearQuery->max('year');

            if ($gradesLatestYear !== null) {
                $gradeCountQuery = DB::table('school_grade_tbl as sgt')->where('sgt.year', $gradesLatestYear);
                if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
                    $gradeCountQuery->where('sgt.is_deleted', 0);
                }
                $this->applySchoolScope($gradeCountQuery, $user, 'sgt', $gradeSchoolColumn);
                $gradesTotal = (int) $gradeCountQuery->count();
            }
        }

        $classesTotal = 0;
        if (Schema::hasTable('school_grade_class_tbl')) {
            $classColumns = Schema::getColumnListing('school_grade_class_tbl');
            $classSchoolColumn = $this->resolveSchoolColumn($classColumns);

            $classYearQuery = DB::table('school_grade_class_tbl as sgct');
            if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                $classYearQuery->where('sgct.is_deleted', 0);
            }
            $this->applySchoolScope($classYearQuery, $user, 'sgct', $classSchoolColumn);
            $classesLatestYear = $classYearQuery->max('year');

            if ($classesLatestYear !== null) {
                $classCountQuery = DB::table('school_grade_class_tbl as sgct')->where('sgct.year', $classesLatestYear);
                if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                    $classCountQuery->where('sgct.is_deleted', 0);
                }
                $this->applySchoolScope($classCountQuery, $user, 'sgct', $classSchoolColumn);
                $classesTotal = (int) $classCountQuery->count();
            }
        }

        $studentsLastUpdated = null;
        if (Schema::hasTable('student_tbl')) {
            $studentColumns = Schema::getColumnListing('student_tbl');
            $studentSchoolColumn = $this->resolveSchoolColumn($studentColumns);

            $studentsUpdatedQuery = DB::table('student_tbl as st');
            if (Schema::hasColumn('student_tbl', 'is_deleted')) {
                $studentsUpdatedQuery->where('st.is_deleted', 0);
            }
            $this->applySchoolScope($studentsUpdatedQuery, $user, 'st', $studentSchoolColumn);
            $studentsLastUpdated = $studentsUpdatedQuery->max('st.date_updated');
        }

        $staffLastUpdated = null;
        if (Schema::hasTable('staff_tbl')) {
            $staffColumns = Schema::getColumnListing('staff_tbl');
            $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);

            $staffUpdatedQuery = DB::table('staff_tbl as st');
            if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
                $staffUpdatedQuery->where('st.is_deleted', 0);
            }
            $this->applySchoolScope($staffUpdatedQuery, $user, 'st', $staffSchoolColumn);
            $staffLastUpdated = $staffUpdatedQuery->max('st.date_updated');
        }

        return response()->json([
            'summary' => [
                'students_total' => $studentsTotal,
                'staff_total' => $staffTotal,
                'grades_total' => $gradesTotal,
                'classes_total' => $classesTotal,
                'students_last_updated' => $studentsLastUpdated,
                'staff_last_updated' => $staffLastUpdated,
                'students_latest_year' => $studentsLatestYear !== null ? (int) $studentsLatestYear : null,
                'grades_latest_year' => $gradesLatestYear !== null ? (int) $gradesLatestYear : null,
                'classes_latest_year' => $classesLatestYear !== null ? (int) $classesLatestYear : null,
            ],
        ]);
    }
}
