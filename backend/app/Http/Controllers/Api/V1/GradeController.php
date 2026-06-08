<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\GradeSpan;
use App\Models\SchoolDetail;
use App\Models\SchoolGrade;
use App\Models\SchoolGradeClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GradeController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function __invoke(Request $request): JsonResponse
    {
        $gradeTable = (new SchoolGrade())->getTable();

        if (!Schema::hasTable($gradeTable)) {
            return response()->json([
                'year' => null,
                'years' => [],
                'data' => [],
            ]);
        }

        $user = $this->authUser();
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'year' => null,
                'years' => [],
                'data' => [],
            ]);
        }
        $gradeColumns = Schema::getColumnListing($gradeTable);
        $hasIsDeleted = in_array('is_deleted', $gradeColumns, true);
        $schoolColumn = $this->resolveSchoolColumn($gradeColumns);

        $availableYearsQuery = SchoolGrade::query();
        if ($hasIsDeleted) {
            $availableYearsQuery->where('is_deleted', 0);
        }
        $this->applySchoolScope($availableYearsQuery->getQuery(), $user, null, $schoolColumn);
        $availableYears = $availableYearsQuery
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->filter(fn ($year): bool => $year >= 2000 && $year <= 2100)
            ->values()
            ->all();

        if ($classTeacherAssignment !== null) {
            $availableYears = array_values(array_filter(
                $availableYears,
                fn (int $year): bool => $year === (int) $classTeacherAssignment['year']
            ));
        }

        if (empty($availableYears)) {
            return response()->json([
                'year' => null,
                'years' => [],
                'data' => [],
            ]);
        }

        $requestedYear = $request->query('year');
        $requestedYear = is_numeric($requestedYear) ? (int) $requestedYear : null;
        $selectedYear = $classTeacherAssignment !== null
            ? (int) $classTeacherAssignment['year']
            : (($requestedYear !== null && in_array($requestedYear, $availableYears, true))
                ? $requestedYear
                : $availableYears[0]);

        $gradeLabelColumns = [
            'grade_en',
            'grade_si',
            'grade_ta',
        ];

        $query = DB::table("{$gradeTable} as sgt")
            ->leftJoin('grade_tbl as gt', 'sgt.grade_id', '=', 'gt.grade_id')
            ->select([
                'sgt.sch_grd_id',
                'sgt.grade_id',
                'sgt.year',
            ])
            ->where('sgt.year', $selectedYear)
            ->orderBy('sgt.grade_id');

        if ($this->resolveLookupLabelColumn('grade_tbl', $gradeLabelColumns) !== null) {
            $query->addSelect(DB::raw($this->buildLocalizedLabelSelect('gt', $gradeLabelColumns, 'grade')));
        }

        if (in_array('stf_id', $gradeColumns, true)) {
            $query->addSelect('sgt.stf_id');
        }
        if (in_array('required_subject_count', $gradeColumns, true)) {
            $query->addSelect('sgt.required_subject_count');
        }

        if (in_array('date_updated', $gradeColumns, true)) {
            $query->addSelect('sgt.date_updated');
        } elseif (in_array('updated_dt', $gradeColumns, true)) {
            $query->addSelect(DB::raw('sgt.updated_dt as date_updated'));
        }

        if ($schoolColumn !== null) {
            $query->addSelect(DB::raw("sgt.{$schoolColumn} as census_id"));
            $query->orderBy("sgt.{$schoolColumn}");
        }

        if ($hasIsDeleted) {
            $query->where('sgt.is_deleted', 0);
        }

        if ($classTeacherAssignment !== null) {
            $query->where('sgt.grade_id', $classTeacherAssignment['grade_id']);
        }

        $this->applySchoolScope($query, $user, 'sgt', $schoolColumn);

        if (Schema::hasTable('school_details_tbl') && $schoolColumn !== null) {
            $query->leftJoin('school_details_tbl as sc', "sgt.{$schoolColumn}", '=', 'sc.census_id')
                ->addSelect(DB::raw('sc.sch_name as school_name'));
        }

        if (Schema::hasTable('staff_tbl') && in_array('stf_id', $gradeColumns, true)) {
            $query->leftJoin('staff_tbl as st', 'sgt.stf_id', '=', 'st.stf_id')
                ->addSelect(DB::raw('st.name_with_ini as grade_head'));
        }

        $rows = $query->get()->map(function ($row): array {
            return [
                'sch_grd_id' => isset($row->sch_grd_id) ? (int) $row->sch_grd_id : null,
                'census_id' => isset($row->census_id) ? (int) $row->census_id : null,
                'school_name' => $row->school_name ?? null,
                'grade_id' => isset($row->grade_id) ? (int) $row->grade_id : null,
                'grade' => $row->grade,
                'year' => isset($row->year) ? (int) $row->year : null,
                'stf_id' => isset($row->stf_id) ? (int) $row->stf_id : null,
                'required_subject_count' => isset($row->required_subject_count) && $row->required_subject_count !== null
                    ? (int) $row->required_subject_count
                    : null,
                'grade_head' => $row->grade_head ?? null,
                'date_updated' => $row->date_updated ?? null,
            ];
        })->all();

        return response()->json([
            'year' => (int) $selectedYear,
            'years' => $availableYears,
            'data' => $rows,
        ]);
    }
    public function initializeYear(Request $request): JsonResponse
    {
        $gradeTable = (new SchoolGrade())->getTable();

        if (!Schema::hasTable($gradeTable)) {
            return response()->json([
                'message' => 'Required table is missing.',
            ], 422);
        }

        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $currentAcademicYear = (int) now()->year;
        $targetYear = (int) $request->input('year', $currentAcademicYear);
        if ($targetYear < 2000 || $targetYear > $currentAcademicYear) {
            return response()->json(['message' => 'Invalid target year.'], 422);
        }

        Log::info('Grade year initialization started.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'target_year' => $targetYear,
        ]);

        $gradeColumns = Schema::getColumnListing($gradeTable);
        $gradeHasIsDeleted = in_array('is_deleted', $gradeColumns, true);
        $gradeSchoolColumn = $this->resolveSchoolColumn($gradeColumns);

        if ($gradeSchoolColumn === null) {
            return response()->json(['message' => 'School column not found in grade table.'], 422);
        }

        $censusId = $this->isAdministrator($user)
            ? $this->resolveRequestedSchoolCensusId($user)
            : $this->resolveUserCensusId($user);

        if ($censusId === null) {
            Log::warning('Grade year initialization skipped: school not resolved.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'target_year' => $targetYear,
            ]);

            return response()->json(['message' => 'Select a school first.'], 422);
        }

        $gradeIds = $this->resolveGradeIdsForSchool($censusId);
        if ($gradeIds === []) {
            Log::warning('Grade year initialization skipped: school grade span not set.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'census_id' => $censusId,
                'target_year' => $targetYear,
            ]);

            return response()->json(['message' => 'School grade span is not set.'], 422);
        }

        try {
            $result = DB::transaction(function () use (
                $targetYear,
                $censusId,
                $gradeIds,
                $gradeColumns,
                $gradeHasIsDeleted,
                $gradeSchoolColumn,
                $gradeTable,
            ): array {
                $existingGradeQuery = DB::table($gradeTable)
                    ->where($gradeSchoolColumn, $censusId)
                    ->where('year', $targetYear);
                if ($gradeHasIsDeleted) {
                    $existingGradeQuery->where('is_deleted', 0);
                }
                $existingGradeIds = $existingGradeQuery->pluck('grade_id')
                    ->map(fn ($value): int => (int) $value)
                    ->all();

                $gradeInserts = [];
                foreach ($gradeIds as $gradeId) {
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
                    if (in_array('required_subject_count', $gradeColumns, true)) {
                        $insert['required_subject_count'] = null;
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
                }

                $createdGrades = 0;
                if (!empty($gradeInserts)) {
                    DB::table($gradeTable)->insert($gradeInserts);
                    $createdGrades = count($gradeInserts);
                }

                return [
                    'grade_ids' => $gradeIds,
                    'total_grades' => count($gradeIds),
                    'created_grades' => $createdGrades,
                ];
            });

            $totalGrades = (int) ($result['total_grades'] ?? 0);
            $createdGrades = (int) ($result['created_grades'] ?? 0);

            if ($totalGrades > 0 && $createdGrades === 0) {
                Log::info('Grade year initialization skipped: grades already exist.', [
                    'user_id' => $user->user_id ?? null,
                    'role_id' => $user->role_id ?? null,
                    'census_id' => $censusId,
                    'target_year' => $targetYear,
                    'total_grades' => $totalGrades,
                ]);

                return response()->json([
                    'message' => 'Grades are already exists for the selected year.',
                    'target_year' => $targetYear,
                    'census_id' => $censusId,
                    'result' => $result,
                ], 422);
            }

            $message = $createdGrades === 1
                ? '1 grade created successfully.'
                : "{$createdGrades} grades created successfully.";

            Log::info('Grade year initialization completed.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'census_id' => $censusId,
                'target_year' => $targetYear,
                'total_grades' => $totalGrades,
                'created_grades' => $createdGrades,
            ]);

            return response()->json([
                'message' => $message,
                'target_year' => $targetYear,
                'census_id' => $censusId,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Grade year initialization failed.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'census_id' => $censusId,
                'target_year' => $targetYear,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to initialize year data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, int $gradeRowId): JsonResponse
    {
        $gradeTable = (new SchoolGrade())->getTable();

        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $stfId = $request->input('stf_id');
        $stfId = is_numeric($stfId) ? (int) $stfId : null;
        $requiredSubjectCountInput = $request->input('required_subject_count');
        $requiredSubjectCount = null;
        if ($requiredSubjectCountInput !== null && $requiredSubjectCountInput !== '') {
            if (!is_numeric($requiredSubjectCountInput)) {
                return response()->json(['message' => 'Required subject count must be a valid number.'], 422);
            }

            $requiredSubjectCount = (int) $requiredSubjectCountInput;
            if ($requiredSubjectCount < 0 || $requiredSubjectCount > 50) {
                return response()->json(['message' => 'Required subject count must be between 0 and 50.'], 422);
            }
        }

        $columns = Schema::getColumnListing($gradeTable);
        $schoolColumn = $this->resolveSchoolColumn($columns);

        $query = DB::table("{$gradeTable} as sgt")->where('sgt.sch_grd_id', $gradeRowId);
        if (in_array('is_deleted', $columns, true)) {
            $query->where('sgt.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgt', $schoolColumn);

        $row = $query->first();
        if ($row === null) {
            return response()->json(['message' => 'Grade row not found.'], 404);
        }

        if ($stfId !== null && in_array('stf_id', $columns, true) && $schoolColumn !== null) {
            $duplicateQuery = DB::table("{$gradeTable} as sgt")
                ->where('sgt.sch_grd_id', '<>', $gradeRowId)
                ->where("sgt.{$schoolColumn}", $row->{$schoolColumn})
                ->where('sgt.year', $row->year)
                ->where('sgt.stf_id', $stfId);

            if (in_array('is_deleted', $columns, true)) {
                $duplicateQuery->where('sgt.is_deleted', 0);
            }

            if ($duplicateQuery->exists()) {
                return response()->json(['message' => 'This staff member is already assigned to another grade for the selected year.'], 422);
            }
        }

        $updates = [];
        if (in_array('stf_id', $columns, true)) {
            $updates['stf_id'] = $stfId;
        }
        if (in_array('required_subject_count', $columns, true)) {
            $updates['required_subject_count'] = $requiredSubjectCount;
        }
        if (in_array('date_updated', $columns, true)) {
            $updates['date_updated'] = now();
        }
        if (in_array('updated_dt', $columns, true)) {
            $updates['updated_dt'] = now();
        }

        if (!empty($updates)) {
            DB::table($gradeTable)->where('sch_grd_id', $gradeRowId)->update($updates);
        }

        return response()->json(['message' => 'Grade row updated.']);
    }

    public function destroy(int $gradeRowId): JsonResponse
    {
        $gradeTable = (new SchoolGrade())->getTable();
        $gradeClassTable = (new SchoolGradeClass())->getTable();

        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $gradeColumns = Schema::getColumnListing($gradeTable);
        $classColumns = Schema::getColumnListing($gradeClassTable);
        $gradeSchoolColumn = $this->resolveSchoolColumn($gradeColumns);
        $classSchoolColumn = $this->resolveSchoolColumn($classColumns);

        $query = DB::table("{$gradeTable} as sgt")->where('sgt.sch_grd_id', $gradeRowId);
        if (in_array('is_deleted', $gradeColumns, true)) {
            $query->where('sgt.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgt', $gradeSchoolColumn);
        $row = $query->first();

        if ($row === null) {
            return response()->json(['message' => 'Grade row not found.'], 404);
        }

        DB::transaction(function () use ($gradeRowId, $row, $gradeColumns, $classColumns, $gradeSchoolColumn, $classSchoolColumn, $gradeTable, $gradeClassTable): void {
            if (in_array('is_deleted', $gradeColumns, true)) {
                DB::table($gradeTable)->where('sch_grd_id', $gradeRowId)->update(['is_deleted' => 1]);
            } else {
                DB::table($gradeTable)->where('sch_grd_id', $gradeRowId)->delete();
            }

            if ($classSchoolColumn !== null && $gradeSchoolColumn !== null) {
                $classQuery = DB::table($gradeClassTable)
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

    /**
     * @return array<int, int>
     */
    private function resolveGradeIdsForSchool(string $censusId): array
    {
        $range = $this->resolveGradeRangeForSchool($censusId);
        if ($range === null) {
            return [];
        }

        [$startGrade, $endGrade] = $range;

        $gradeTable = (new Grade())->getTable();
        if (!Schema::hasTable($gradeTable)) {
            return [];
        }

        return Grade::query()
            ->select(['grade_id'])
            ->when(
                $this->resolveLookupLabelColumn($gradeTable, ['grade_en', 'grade_si', 'grade_ta']) !== null,
                fn ($query) => $query->addSelect(DB::raw($this->buildLocalizedLabelSelect($gradeTable, ['grade_en', 'grade_si', 'grade_ta'], 'grade')))
            )
            ->orderBy('grade_id')
            ->get()
            ->filter(function (object $row) use ($startGrade, $endGrade): bool {
                $gradeName = (string) ($row->grade ?? '');
                $gradeNumber = $this->readGradeNumber($gradeName);
                if ($gradeNumber === null) {
                    return false;
                }

                return $gradeNumber >= $startGrade && $gradeNumber <= $endGrade;
            })
            ->map(fn (object $row): int => (int) $row->grade_id)
            ->values()
            ->all();
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private function resolveGradeRangeForSchool(string $censusId): ?array
    {
        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable) || !Schema::hasColumn($schoolTable, 'grd_span_id')) {
            return null;
        }

        $school = SchoolDetail::query()
            ->whereIn('census_id', $this->censusCandidates($censusId))
            ->first(['grd_span_id']);

        $gradeSpanId = is_numeric($school?->grd_span_id ?? null) ? (int) $school->grd_span_id : 0;
        $gradeSpanTable = (new GradeSpan())->getTable();
        if ($gradeSpanId <= 0 || !Schema::hasTable($gradeSpanTable)) {
            return null;
        }

        $spanText = GradeSpan::query()
            ->where('grd_span_id', $gradeSpanId)
            ->value('grd_span');

        $span = trim((string) $spanText);
        if (!preg_match('/^(\d+)\s*-\s*(\d+)$/', $span, $matches)) {
            return null;
        }

        $startGrade = (int) $matches[1];
        $endGrade = (int) $matches[2];

        if ($startGrade <= 0 || $endGrade <= 0 || $startGrade > $endGrade) {
            return null;
        }

        return [$startGrade, $endGrade];
    }

    private function readGradeNumber(string $gradeName): ?int
    {
        if (!preg_match('/(\d+)/', $gradeName, $matches)) {
            return null;
        }

        $gradeNumber = (int) $matches[1];

        return $gradeNumber > 0 ? $gradeNumber : null;
    }
}
