<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GradeReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $year = $request->query('year');
        $year = is_numeric($year) ? (int) $year : null;

        try {
            if (!Schema::hasTable('student_grade_class_tbl') || !Schema::hasTable('grade_tbl')) {
                return response()->json(['data' => []]);
            }

            $query = DB::table('student_grade_class_tbl as sgc')
                ->join('grade_tbl as gt', 'sgc.grade_id', '=', 'gt.grade_id')
                ->where('sgc.is_deleted', 0)
                ->selectRaw('sgc.grade_id, gt.grade, sgc.year, COUNT(DISTINCT sgc.index_no) as student_count')
                ->groupBy('sgc.grade_id', 'gt.grade', 'sgc.year')
                ->orderBy('sgc.year', 'desc')
                ->orderBy('sgc.grade_id');

            if ($year !== null) {
                $query->where('sgc.year', $year);
            }

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
