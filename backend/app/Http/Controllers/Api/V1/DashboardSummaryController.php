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
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);

        if ($this->isUnassignedClassTeacher($user)) {
            $year = $this->resolveClassTeacherAcademicYear();

            return response()->json([
                'summary' => [
                    'students_total' => 0,
                    'staff_total' => 0,
                    'grades_total' => 0,
                    'classes_total' => 0,
                    'students_last_updated' => null,
                    'staff_last_updated' => null,
                    'students_latest_year' => $year,
                    'grades_latest_year' => $year,
                    'classes_latest_year' => $year,
                ],
            ]);
        }

        $studentsLatestYear = null;
        $gradesLatestYear = null;
        $classesLatestYear = null;

        $studentsTotal = 0;
        if (Schema::hasTable('student_grade_class_tbl') && Schema::hasTable('school_grade_class_tbl')) {
            $sgctColumns = Schema::getColumnListing('school_grade_class_tbl');
            $sgctSchoolColumn = $this->resolveSchoolColumn($sgctColumns);

            $studentYearQuery = DB::table('student_grade_class_tbl as sgc')
                ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id');

            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $studentYearQuery->where('sgc.is_deleted', 0);
            }
            if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                $studentYearQuery->where('sgct.is_deleted', 0);
            }

            $this->applySchoolScope($studentYearQuery, $user, 'sgct', $sgctSchoolColumn);
            $studentsLatestYear = $studentYearQuery->max('sgct.year');

            if ($studentsLatestYear !== null) {
                $studentCountQuery = DB::table('student_grade_class_tbl as sgc')
                    ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
                    ->where('sgct.year', $studentsLatestYear);

                if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                    $studentCountQuery->where('sgc.is_deleted', 0);
                }
                if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                    $studentCountQuery->where('sgct.is_deleted', 0);
                }

                $this->applySchoolScope($studentCountQuery, $user, 'sgct', $sgctSchoolColumn);
                $studentsTotal = (int) $studentCountQuery->distinct('sgc.std_id')->count('sgc.std_id');
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

        if ($classTeacherAssignment !== null) {
            $studentsTotal = $this->countStudentsForClassTeacherAssignment($classTeacherAssignment);
            $staffTotal = $this->countParallelClassTeachersForAssignment($classTeacherAssignment);
            $classesTotal = $this->countParallelClassesForAssignment($classTeacherAssignment);
            $studentsLatestYear = $classTeacherAssignment['year'];
            $gradesLatestYear = $classTeacherAssignment['year'];
            $classesLatestYear = $classTeacherAssignment['year'];
            $gradesTotal = 1;
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

    /**
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     */
    private function countStudentsForClassTeacherAssignment(array $assignment): int
    {
        if (!Schema::hasTable('student_grade_class_tbl')) {
            return 0;
        }

        $query = DB::table('student_grade_class_tbl as sgc')
            ->where('sgc.sch_grd_cls_id', $assignment['sch_grd_cls_id']);

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }

        return (int) $query->distinct('sgc.std_id')->count('sgc.std_id');
    }

    /**
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     */
    private function countParallelClassesForAssignment(array $assignment): int
    {
        if (!Schema::hasTable('school_grade_class_tbl')) {
            return 0;
        }

        $query = DB::table('school_grade_class_tbl as sgct')
            ->where('sgct.grade_id', $assignment['grade_id'])
            ->where('sgct.year', $assignment['year'])
            ->whereIn('sgct.census_id', $this->censusCandidates($assignment['census_id']));

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        return (int) $query->count();
    }

    /**
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     */
    private function countParallelClassTeachersForAssignment(array $assignment): int
    {
        if (!Schema::hasTable('school_grade_class_tbl')) {
            return 0;
        }

        $query = DB::table('school_grade_class_tbl as sgct')
            ->where('sgct.grade_id', $assignment['grade_id'])
            ->where('sgct.year', $assignment['year'])
            ->whereIn('sgct.census_id', $this->censusCandidates($assignment['census_id']))
            ->where('sgct.stf_id', '>', 0);

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        return (int) $query->distinct('sgct.stf_id')->count('sgct.stf_id');
    }
}
