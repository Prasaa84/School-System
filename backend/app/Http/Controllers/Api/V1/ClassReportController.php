<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ClassReportController extends Controller
{
    use AppliesSchoolScope;

    public function __invoke(Request $request): JsonResponse
    {
        $year = $request->query('year');
        $year = is_numeric($year) ? (int) $year : null;

        try {
            if (!Schema::hasTable('school_grade_class_tbl') || !Schema::hasTable('grade_tbl') || !Schema::hasTable('class_tbl')) {
                return response()->json(['data' => []]);
            }

            $user = $this->authUser();
            $classColumns = Schema::getColumnListing('school_grade_class_tbl');
            $classSchoolColumn = $this->resolveSchoolColumn($classColumns);
            $classHasIsDeleted = in_array('is_deleted', $classColumns, true);
            $studentHasIsDeleted = Schema::hasColumn('student_grade_class_tbl', 'is_deleted');

            $query = DB::table('school_grade_class_tbl as sgct')
                ->join('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
                ->join('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
                ->leftJoin('student_grade_class_tbl as sgc', function ($join) use ($studentHasIsDeleted): void {
                    $join->on('sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id');

                    if ($studentHasIsDeleted) {
                        $join->where('sgc.is_deleted', 0);
                    }
                })
                ->selectRaw('sgct.grade_id, gt.grade, sgct.class_id, ct.class, sgct.year, COUNT(DISTINCT sgc.std_id) as student_count')
                ->groupBy('sgct.grade_id', 'gt.grade', 'sgct.class_id', 'ct.class', 'sgct.year')
                ->orderBy('sgct.year', 'desc')
                ->orderBy('sgct.grade_id')
                ->orderBy('sgct.class_id');

            if ($classHasIsDeleted) {
                $query->where('sgct.is_deleted', 0);
            }

            if ($year !== null) {
                $query->where('sgct.year', $year);
            }

            $this->applySchoolScope($query, $user, 'sgct', $classSchoolColumn);

            $rows = $query->get();

            return response()->json([
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to load class report data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
