<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ClassReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $year = $request->query('year');
        $year = is_numeric($year) ? (int) $year : null;

        try {
            if (!Schema::hasTable('student_grade_class_tbl') || !Schema::hasTable('school_grade_class_tbl') || !Schema::hasTable('grade_tbl') || !Schema::hasTable('class_tbl')) {
                return response()->json(['data' => []]);
            }

            $query = DB::table('student_grade_class_tbl as sgc')
                ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
                ->join('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
                ->join('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
                ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), function ($q): void {
                    $q->where('sgc.is_deleted', 0);
                })
                ->when(Schema::hasColumn('school_grade_class_tbl', 'is_deleted'), function ($q): void {
                    $q->where('sgct.is_deleted', 0);
                })
                ->selectRaw('sgct.grade_id, gt.grade, sgct.class_id, ct.class, sgct.year, COUNT(DISTINCT sgc.std_id) as student_count')
                ->groupBy('sgct.grade_id', 'gt.grade', 'sgct.class_id', 'ct.class', 'sgct.year')
                ->orderBy('sgct.year', 'desc')
                ->orderBy('sgct.grade_id')
                ->orderBy('sgct.class_id');

            if ($year !== null) {
                $query->where('sgct.year', $year);
            }

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
