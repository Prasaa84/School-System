<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GradeReportController extends Controller
{
    use AppliesSchoolScope;

    public function __invoke(Request $request): JsonResponse
    {
        $year = $request->query('year');
        $year = is_numeric($year) ? (int) $year : null;

        try {
            if (!Schema::hasTable('school_grade_tbl') || !Schema::hasTable('grade_tbl')) {
                return response()->json(['data' => []]);
            }

            $user = $this->authUser();
            $gradeColumns = Schema::getColumnListing('school_grade_tbl');
            $gradeSchoolColumn = $this->resolveSchoolColumn($gradeColumns);
            $gradeHasIsDeleted = in_array('is_deleted', $gradeColumns, true);
            $gradeClassColumns = Schema::getColumnListing('school_grade_class_tbl');
            $classHasIsDeleted = in_array('is_deleted', $gradeClassColumns, true);
            $studentHasIsDeleted = Schema::hasColumn('student_grade_class_tbl', 'is_deleted');

            $query = DB::table('school_grade_tbl as sgt')
                ->join('grade_tbl as gt', 'sgt.grade_id', '=', 'gt.grade_id')
                ->leftJoin('school_grade_class_tbl as sgct', function ($join) use ($classHasIsDeleted): void {
                    $join->on('sgt.census_id', '=', 'sgct.census_id')
                        ->on('sgt.grade_id', '=', 'sgct.grade_id')
                        ->on('sgt.year', '=', 'sgct.year');

                    if ($classHasIsDeleted) {
                        $join->where('sgct.is_deleted', 0);
                    }
                })
                ->leftJoin('student_grade_class_tbl as sgc', function ($join) use ($studentHasIsDeleted): void {
                    $join->on('sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id');

                    if ($studentHasIsDeleted) {
                        $join->where('sgc.is_deleted', 0);
                    }
                })
                ->selectRaw('sgt.grade_id, gt.grade, sgt.year, COUNT(DISTINCT sgc.std_id) as student_count')
                ->groupBy('sgt.grade_id', 'gt.grade', 'sgt.year')
                ->orderBy('sgt.year', 'desc')
                ->orderBy('sgt.grade_id');

            if ($gradeHasIsDeleted) {
                $query->where('sgt.is_deleted', 0);
            }

            if ($year !== null) {
                $query->where('sgt.year', $year);
            }

            $this->applySchoolScope($query, $user, 'sgt', $gradeSchoolColumn);

            $rows = $query->get();

            return response()->json([
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to load grade report data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
