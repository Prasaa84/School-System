<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GradeController extends Controller
{
    use AppliesSchoolScope;

    public function __invoke(): JsonResponse
    {
        if (!Schema::hasTable('school_grade_tbl')) {
            return response()->json(['data' => []]);
        }

        $user = $this->authUser();
        $gradeColumns = Schema::getColumnListing('school_grade_tbl');
        $hasIsDeleted = in_array('is_deleted', $gradeColumns, true);
        $schoolColumn = $this->resolveSchoolColumn($gradeColumns);

        $latestYearQuery = DB::table('school_grade_tbl as sgt');
        if ($hasIsDeleted) {
            $latestYearQuery->where('sgt.is_deleted', 0);
        }
        $this->applySchoolScope($latestYearQuery, $user, 'sgt', $schoolColumn);
        $latestYear = $latestYearQuery->max('year');

        if ($latestYear === null) {
            return response()->json(['data' => []]);
        }

        $query = DB::table('school_grade_tbl as sgt')
            ->leftJoin('grade_tbl as gt', 'sgt.grade_id', '=', 'gt.grade_id')
            ->select([
                'sgt.sch_grd_id',
                'sgt.grade_id',
                'sgt.year',
                DB::raw('gt.grade as grade'),
            ])
            ->where('sgt.year', $latestYear)
            ->orderBy('sgt.grade_id');

        if (in_array('stf_id', $gradeColumns, true)) {
            $query->addSelect('sgt.stf_id');
        }

        if ($schoolColumn !== null) {
            $query->addSelect(DB::raw("sgt.{$schoolColumn} as school_id"));
            $query->orderBy("sgt.{$schoolColumn}");
        }

        if ($hasIsDeleted) {
            $query->where('sgt.is_deleted', 0);
        }

        $this->applySchoolScope($query, $user, 'sgt', $schoolColumn);

        if (Schema::hasTable('school_tbl') && $schoolColumn !== null) {
            $query->leftJoin('school_tbl as sc', "sgt.{$schoolColumn}", '=', 'sc.census_id')
                ->addSelect(DB::raw('sc.sch_name as school_name'));
        }

        if (Schema::hasTable('staff_tbl') && in_array('stf_id', $gradeColumns, true)) {
            $query->leftJoin('staff_tbl as st', 'sgt.stf_id', '=', 'st.stf_id')
                ->addSelect(DB::raw('st.name_with_ini as grade_head'));
        }

        $rows = $query->get()->map(function ($row): array {
            return [
                'sch_grd_id' => isset($row->sch_grd_id) ? (int) $row->sch_grd_id : null,
                'school_id' => isset($row->school_id) ? (int) $row->school_id : null,
                'school_name' => $row->school_name ?? null,
                'grade_id' => isset($row->grade_id) ? (int) $row->grade_id : null,
                'grade' => $row->grade,
                'year' => isset($row->year) ? (int) $row->year : null,
                'stf_id' => isset($row->stf_id) ? (int) $row->stf_id : null,
                'grade_head' => $row->grade_head ?? null,
            ];
        })->all();

        return response()->json([
            'year' => (int) $latestYear,
            'data' => $rows,
        ]);
    }

    public function initializeYear(Request $request): JsonResponse
    {
        if (!Schema::hasTable('school_grade_tbl') || !Schema::hasTable('school_grade_class_tbl')) {
            return response()->json([
                'message' => 'Required tables are missing.',
            ], 422);
        }

        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }
        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $targetYear = (int) $request->input('year', now()->year);
        if ($targetYear < 2000 || $targetYear > 2100) {
            return response()->json(['message' => 'Invalid target year.'], 422);
        }

        $gradeColumns = Schema::getColumnListing('school_grade_tbl');
        $classColumns = Schema::getColumnListing('school_grade_class_tbl');
        $gradeHasIsDeleted = in_array('is_deleted', $gradeColumns, true);
        $classHasIsDeleted = in_array('is_deleted', $classColumns, true);
        $gradeSchoolColumn = $this->resolveSchoolColumn($gradeColumns);
        $classSchoolColumn = $this->resolveSchoolColumn($classColumns);

        if ($gradeSchoolColumn === null || $classSchoolColumn === null) {
            return response()->json(['message' => 'School column not found in grade/class tables.'], 422);
        }

        $censusId = $this->isAdministrator($user)
            ? (is_numeric($request->input('census_id')) ? (int) $request->input('census_id') : null)
            : $this->resolveUserCensusId($user);

        if ($censusId === null) {
            return response()->json(['message' => 'census_id is required for initialization.'], 422);
        }

        try {
            $result = DB::transaction(function () use (
                $targetYear,
                $censusId,
                $gradeColumns,
                $classColumns,
                $gradeHasIsDeleted,
                $classHasIsDeleted,
                $gradeSchoolColumn,
                $classSchoolColumn
            ): array {
                $gradeSourceYear = $this->resolveSourceYear('school_grade_tbl', $gradeSchoolColumn, $censusId, $targetYear);
                $classSourceYear = $this->resolveSourceYear('school_grade_class_tbl', $classSchoolColumn, $censusId, $targetYear);

                $createdGrades = 0;
                if ($gradeSourceYear !== null) {
                    $sourceGradeQuery = DB::table('school_grade_tbl')
                        ->where($gradeSchoolColumn, $censusId)
                        ->where('year', $gradeSourceYear);
                    if ($gradeHasIsDeleted) {
                        $sourceGradeQuery->where('is_deleted', 0);
                    }
                    $sourceGradeRows = $sourceGradeQuery->get();

                    $existingGradeQuery = DB::table('school_grade_tbl')
                        ->where($gradeSchoolColumn, $censusId)
                        ->where('year', $targetYear);
                    if ($gradeHasIsDeleted) {
                        $existingGradeQuery->where('is_deleted', 0);
                    }
                    $existingGradeIds = $existingGradeQuery->pluck('grade_id')
                        ->map(fn ($v): int => (int) $v)
                        ->all();

                    $gradeInserts = [];
                    foreach ($sourceGradeRows as $row) {
                        $gradeId = (int) $row->grade_id;
                        if (in_array($gradeId, $existingGradeIds, true)) {
                            continue;
                        }

                        $insert = [
                            $gradeSchoolColumn => $censusId,
                            'grade_id' => $gradeId,
                            'year' => $targetYear,
                        ];

                        if ($gradeHasIsDeleted) {
                            $insert['is_deleted'] = 0;
                        }
                        if (in_array('stf_id', $gradeColumns, true)) {
                            $insert['stf_id'] = null;
                        }
                        if (in_array('date_added', $gradeColumns, true)) {
                            $insert['date_added'] = now();
                        }
                        if (in_array('date_updated', $gradeColumns, true)) {
                            $insert['date_updated'] = now();
                        }
                        if (in_array('updated_dt', $gradeColumns, true)) {
                            $insert['updated_dt'] = now();
                        }

                        $gradeInserts[] = $insert;
                        $existingGradeIds[] = $gradeId;
                    }

                    if (!empty($gradeInserts)) {
                        DB::table('school_grade_tbl')->insert($gradeInserts);
                        $createdGrades = count($gradeInserts);
                    }
                }

                $createdClasses = 0;
                if ($classSourceYear !== null) {
                    $sourceClassQuery = DB::table('school_grade_class_tbl')
                        ->where($classSchoolColumn, $censusId)
                        ->where('year', $classSourceYear);
                    if ($classHasIsDeleted) {
                        $sourceClassQuery->where('is_deleted', 0);
                    }
                    $sourceClassRows = $sourceClassQuery->get();

                    $existingClassQuery = DB::table('school_grade_class_tbl')
                        ->where($classSchoolColumn, $censusId)
                        ->where('year', $targetYear);
                    if ($classHasIsDeleted) {
                        $existingClassQuery->where('is_deleted', 0);
                    }
                    $existingClassKeys = $existingClassQuery->get(['grade_id', 'class_id'])
                        ->map(fn ($row): string => (int) $row->grade_id . ':' . (int) $row->class_id)
                        ->all();

                    $classInserts = [];
                    foreach ($sourceClassRows as $row) {
                        $gradeId = (int) $row->grade_id;
                        $classId = (int) $row->class_id;
                        $key = $gradeId . ':' . $classId;

                        if (in_array($key, $existingClassKeys, true)) {
                            continue;
                        }

                        $insert = [
                            $classSchoolColumn => $censusId,
                            'grade_id' => $gradeId,
                            'class_id' => $classId,
                            'year' => $targetYear,
                        ];

                        if ($classHasIsDeleted) {
                            $insert['is_deleted'] = 0;
                        }
                        if (in_array('stf_id', $classColumns, true)) {
                            $insert['stf_id'] = null;
                        }
                        if (in_array('approved_std_count', $classColumns, true)) {
                            $insert['approved_std_count'] = $row->approved_std_count;
                        }
                        if (in_array('std_count', $classColumns, true)) {
                            $insert['std_count'] = $row->std_count;
                        }
                        if (in_array('date_added', $classColumns, true)) {
                            $insert['date_added'] = now();
                        }
                        if (in_array('date_updated', $classColumns, true)) {
                            $insert['date_updated'] = now();
                        }
                        if (in_array('updated_dt', $classColumns, true)) {
                            $insert['updated_dt'] = now();
                        }

                        $classInserts[] = $insert;
                        $existingClassKeys[] = $key;
                    }

                    if (!empty($classInserts)) {
                        DB::table('school_grade_class_tbl')->insert($classInserts);
                        $createdClasses = count($classInserts);
                    }
                }

                return [
                    'grade_source_year' => $gradeSourceYear,
                    'class_source_year' => $classSourceYear,
                    'created_grades' => $createdGrades,
                    'created_classes' => $createdClasses,
                ];
            });

            return response()->json([
                'message' => 'Year initialization completed.',
                'target_year' => $targetYear,
                'census_id' => $censusId,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to initialize year data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $gradeRowId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $stfId = $request->input('stf_id');
        $stfId = is_numeric($stfId) ? (int) $stfId : null;

        $columns = Schema::getColumnListing('school_grade_tbl');
        $schoolColumn = $this->resolveSchoolColumn($columns);

        $query = DB::table('school_grade_tbl as sgt')->where('sgt.sch_grd_id', $gradeRowId);
        if (in_array('is_deleted', $columns, true)) {
            $query->where('sgt.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgt', $schoolColumn);

        $row = $query->first();
        if ($row === null) {
            return response()->json(['message' => 'Grade row not found.'], 404);
        }

        $updates = [];
        if (in_array('stf_id', $columns, true)) {
            $updates['stf_id'] = $stfId;
        }
        if (in_array('date_updated', $columns, true)) {
            $updates['date_updated'] = now();
        }
        if (in_array('updated_dt', $columns, true)) {
            $updates['updated_dt'] = now();
        }

        if (!empty($updates)) {
            DB::table('school_grade_tbl')->where('sch_grd_id', $gradeRowId)->update($updates);
        }

        return response()->json(['message' => 'Grade row updated.']);
    }

    public function destroy(int $gradeRowId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $gradeColumns = Schema::getColumnListing('school_grade_tbl');
        $classColumns = Schema::getColumnListing('school_grade_class_tbl');
        $gradeSchoolColumn = $this->resolveSchoolColumn($gradeColumns);
        $classSchoolColumn = $this->resolveSchoolColumn($classColumns);

        $query = DB::table('school_grade_tbl as sgt')->where('sgt.sch_grd_id', $gradeRowId);
        if (in_array('is_deleted', $gradeColumns, true)) {
            $query->where('sgt.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgt', $gradeSchoolColumn);
        $row = $query->first();

        if ($row === null) {
            return response()->json(['message' => 'Grade row not found.'], 404);
        }

        DB::transaction(function () use ($gradeRowId, $row, $gradeColumns, $classColumns, $gradeSchoolColumn, $classSchoolColumn): void {
            if (in_array('is_deleted', $gradeColumns, true)) {
                DB::table('school_grade_tbl')->where('sch_grd_id', $gradeRowId)->update(['is_deleted' => 1]);
            } else {
                DB::table('school_grade_tbl')->where('sch_grd_id', $gradeRowId)->delete();
            }

            if ($classSchoolColumn !== null && $gradeSchoolColumn !== null) {
                $classQuery = DB::table('school_grade_class_tbl')
                    ->where($classSchoolColumn, $row->{$gradeSchoolColumn})
                    ->where('grade_id', $row->grade_id)
                    ->where('year', $row->year);

                if (in_array('is_deleted', $classColumns, true)) {
                    $classQuery->update(['is_deleted' => 1]);
                } else {
                    $classQuery->delete();
                }
            }
        });

        return response()->json(['message' => 'Grade row deleted.']);
    }

    private function resolveSourceYear(string $table, string $schoolColumn, int $censusId, int $targetYear): ?int
    {
        $columns = Schema::getColumnListing($table);
        $query = DB::table($table)
            ->where($schoolColumn, $censusId)
            ->where('year', '<>', $targetYear);

        if (in_array('is_deleted', $columns, true)) {
            $query->where('is_deleted', 0);
        }

        return $query->max('year');
    }
}
