<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClassLookupController extends Controller
{
    public function byGrade(int $gradeId): JsonResponse
    {
        $streamId = DB::table('grade_tbl')
            ->where('grade_id', $gradeId)
            ->value('stream_id');

        if ($streamId === null) {
            return response()->json([
                'message' => 'Grade not found.',
            ], 404);
        }

        $classes = DB::table('class_tbl')
            ->select(['class_id', 'class', 'stream_id'])
            ->where('stream_id', $streamId)
            ->orderBy('class_id')
            ->get();

        return response()->json([
            'data' => $classes,
        ]);
    }
}
