<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Models\SchoolGradeClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassLookupController extends Controller
{
    use AppliesSchoolScope;

    public function byGrade(Request $request, int $gradeId): JsonResponse
    {
        $gradeClassTable = (new SchoolGradeClass())->getTable();
        if (!Schema::hasTable($gradeClassTable)) {
            return response()->json([
                'year' => null,
                'data' => [],
            ]);
        }

        $columns = Schema::getColumnListing($gradeClassTable);
        $schoolColumn = $this->resolveSchoolColumn($columns);
        $hasIsDeleted = in_array('is_deleted', $columns, true);
        $user = $this->authUser();
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'year' => $this->resolveClassTeacherAcademicYear(),
                'data' => [],
            ]);
        }

        if ($classTeacherAssignment !== null && $gradeId !== $classTeacherAssignment['grade_id']) {
            return response()->json([
                'year' => $classTeacherAssignment['year'],
                'data' => [],
            ]);
        }

        $requestedYear = $request->query('year');
        if ($requestedYear !== null && (!is_numeric($requestedYear) || (int) $requestedYear < 2000 || (int) $requestedYear > 2100)) {
            return response()->json([
                'message' => 'Invalid academic year.',
            ], 422);
        }

        $selectedYear = is_numeric($requestedYear) ? (int) $requestedYear : null;
        if ($classTeacherAssignment !== null) {
            $selectedYear = $classTeacherAssignment['year'];
        }
        if ($selectedYear === null) {
            $yearQuery = DB::table("{$gradeClassTable} as sgct")
                ->where('sgct.grade_id', $gradeId);

            if ($hasIsDeleted) {
                $yearQuery->where('sgct.is_deleted', 0);
            }

            $this->applySchoolScope($yearQuery, $user, 'sgct', $schoolColumn);
            $selectedYear = $yearQuery->max('sgct.year');
        }

        if ($selectedYear === null) {
            return response()->json([
                'year' => null,
                'data' => [],
            ]);
        }

        $query = DB::table("{$gradeClassTable} as sgct")
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select([
                'ct.class_id',
                DB::raw('ct.class as class'),
                'ct.stream_id',
            ])
            ->where('sgct.grade_id', $gradeId)
            ->where('sgct.year', $selectedYear);

        if ($hasIsDeleted) {
            $query->where('sgct.is_deleted', 0);
        }

        $this->applySchoolScope($query, $user, 'sgct', $schoolColumn);

        $classes = $query
            ->distinct()
            ->orderBy('ct.class_id')
            ->get();

        return response()->json([
            'year' => (int) $selectedYear,
            'data' => $classes,
        ]);
    }
}
