<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\SchoolGrade;
use App\Models\SchoolGradeClass;
use App\Models\StudentGradeClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClassController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function index(Request $request): JsonResponse
    {
        $gradeClassTable = (new SchoolGradeClass())->getTable();
        if (!Schema::hasTable($gradeClassTable)) {
            return response()->json(['data' => []]);
        }

        $user = $this->authUser();
        $gradeClassColumns = Schema::getColumnListing($gradeClassTable);
        $hasIsDeleted = in_array('is_deleted', $gradeClassColumns, true);
        $schoolColumn = $this->resolveSchoolColumn($gradeClassColumns);

        $availableYearsQuery = DB::table("{$gradeClassTable} as sgct");
        if ($hasIsDeleted) {
            $availableYearsQuery->where('sgct.is_deleted', 0);
        }
        $this->applySchoolScope($availableYearsQuery, $user, 'sgct', $schoolColumn);
        $availableYears = $availableYearsQuery
            ->select('sgct.year')
            ->distinct()
            ->orderByDesc('sgct.year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->filter(fn ($year): bool => $year >= 2000 && $year <= 2100)
            ->values()
            ->all();

        $requestedYear = $request->query('year');
        $requestedYear = is_numeric($requestedYear) ? (int) $requestedYear : null;
        $selectedYear = $requestedYear ?? ($availableYears[0] ?? null);

        if ($selectedYear === null) {
            return response()->json([
                'year' => null,
                'years' => [],
                'data' => [],
            ]);
        }

        $gradeId = $request->query('grade_id');
        $gradeId = is_numeric($gradeId) ? (int) $gradeId : null;
        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
            'grade_en',
            'grade_si',
            'grade_ta',
        ]);
        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]);

        $studentCountQuery = null;
        if (Schema::hasTable('student_grade_class_tbl')) {
            $studentCountQuery = StudentGradeClass::query()->from('student_grade_class_tbl as sgc')
                ->selectRaw('sgc.sch_grd_cls_id, COUNT(DISTINCT sgc.std_id) as current_student_count')
                ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), function ($query): void {
                    $query->where('sgc.is_deleted', 0);
                })
                ->groupBy('sgc.sch_grd_cls_id');
        }

        $query = DB::table("{$gradeClassTable} as sgct")
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.grade_id',
                'sgct.class_id',
                'sgct.year',
            ])
            ->where('sgct.year', $selectedYear)
            ->orderBy('sgct.grade_id')
            ->orderBy('sgct.class_id');

        if ($gradeLabelColumn !== null) {
            $query->addSelect(DB::raw("gt.{$gradeLabelColumn} as grade"));
        }

        if ($classLabelColumn !== null) {
            $query->addSelect(DB::raw("ct.{$classLabelColumn} as class"));
        }

        if ($studentCountQuery !== null) {
            $query->leftJoinSub($studentCountQuery, 'student_counts', function ($join): void {
                $join->on('student_counts.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id');
            })->addSelect(DB::raw('COALESCE(student_counts.current_student_count, 0) as std_count'));
        } elseif (in_array('std_count', $gradeClassColumns, true)) {
            $query->addSelect('sgct.std_count');
        } elseif (in_array('student_count', $gradeClassColumns, true)) {
            $query->addSelect(DB::raw('sgct.student_count as std_count'));
        } else {
            $query->addSelect(DB::raw('0 as std_count'));
        }

        if (in_array('stf_id', $gradeClassColumns, true)) {
            $query->addSelect('sgct.stf_id');
        }

        if ($schoolColumn !== null) {
            $query->addSelect(DB::raw("sgct.{$schoolColumn} as census_id"));
            $query->orderBy("sgct.{$schoolColumn}");
        }

        if ($hasIsDeleted) {
            $query->where('sgct.is_deleted', 0);
        }

        if ($gradeId !== null) {
            $query->where('sgct.grade_id', $gradeId);
        }

        $this->applySchoolScope($query, $user, 'sgct', $schoolColumn);

        if (in_array('approved_std_count', $gradeClassColumns, true)) {
            $query->addSelect('sgct.approved_std_count');
        }

        if (Schema::hasTable('school_details_tbl') && $schoolColumn !== null) {
            $query->leftJoin('school_details_tbl as sc', "sgct.{$schoolColumn}", '=', 'sc.census_id')
                ->addSelect(DB::raw('sc.sch_name as school_name'));
        }

        if (Schema::hasTable('staff_tbl') && in_array('stf_id', $gradeClassColumns, true)) {
            $query->leftJoin('staff_tbl as st', 'sgct.stf_id', '=', 'st.stf_id')
                ->addSelect(DB::raw('st.name_with_ini as class_teacher'));
        }

        $rows = $query->get()->map(function ($row): array {
            return [
                'sch_grd_cls_id' => isset($row->sch_grd_cls_id) ? (int) $row->sch_grd_cls_id : null,
                'census_id' => isset($row->census_id) ? (int) $row->census_id : null,
                'school_name' => $row->school_name ?? null,
                'grade_id' => isset($row->grade_id) ? (int) $row->grade_id : null,
                'grade' => $row->grade,
                'class_id' => isset($row->class_id) ? (int) $row->class_id : null,
                'class' => $row->class,
                'year' => isset($row->year) ? (int) $row->year : null,
                'stf_id' => isset($row->stf_id) ? (int) $row->stf_id : null,
                'approved_std_count' => isset($row->approved_std_count) ? (int) $row->approved_std_count : null,
                'std_count' => isset($row->std_count) ? (int) $row->std_count : null,
                'class_teacher' => $row->class_teacher ?? null,
            ];
        })->all();

        return response()->json([
            'year' => (int) $selectedYear,
            'years' => $availableYears,
            'data' => $rows,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $gradeClassTable = (new SchoolGradeClass())->getTable();
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $year = $request->query('year');
        $year = is_numeric($year) ? (int) $year : null;
        $gradeId = $request->query('grade_id');
        $gradeId = is_numeric($gradeId) ? (int) $gradeId : null;

        $censusId = $this->isAdministrator($user)
            ? $this->resolveRequestedSchoolCensusId($user)
            : $this->resolveUserCensusId($user);

        $grades = [];
        if ($year !== null && Schema::hasTable('school_grade_tbl')) {
            $gradeOptionLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
                'grade_en',
                'grade_si',
                'grade_ta',
            ]);

            $gradeQuery = DB::table('school_grade_tbl as sgt')
                ->join('grade_tbl as gt', 'sgt.grade_id', '=', 'gt.grade_id')
                ->select(['sgt.grade_id'])
                ->where('sgt.year', $year)
                ->orderBy('sgt.grade_id');

            if ($gradeOptionLabelColumn !== null) {
                $gradeQuery->addSelect(DB::raw("gt.{$gradeOptionLabelColumn} as grade"));
            }

            if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
                $gradeQuery->where('sgt.is_deleted', 0);
            }

            $this->applySchoolScope($gradeQuery, $user, 'sgt', 'census_id');

            $grades = $gradeQuery->get()->map(fn ($row): array => [
                'grade_id' => (int) $row->grade_id,
                'grade' => (string) ($row->grade ?? ''),
            ])->all();
        }

        $classes = [];
        if ($year !== null && $gradeId !== null && $censusId !== null && Schema::hasTable('class_tbl') && Schema::hasTable('grade_tbl')) {
            $streamId = Grade::query()->where('grade_id', $gradeId)->value('stream_id');
            $streamId = is_numeric($streamId) ? (int) $streamId : null;

            if ($streamId !== null) {
                $classOptionLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
                    'class_en',
                    'class_si',
                    'class_ta',
                    'class',
                ]);

                $classQuery = SchoolClass::query()
                    ->select(['class_id'])
                    ->where('stream_id', $streamId)
                    ->orderBy('class_id');

                if ($classOptionLabelColumn !== null) {
                    $classQuery->addSelect(DB::raw("{$classOptionLabelColumn} as class"));
                }

                $existingClassIds = [];
                if (Schema::hasTable($gradeClassTable)) {
                    $existingQuery = SchoolGradeClass::query()
                        ->where('census_id', $censusId)
                        ->where('year', $year)
                        ->where('grade_id', $gradeId);

                    if (Schema::hasColumn($gradeClassTable, 'is_deleted')) {
                        $existingQuery->where('is_deleted', 0);
                    }

                    $existingClassIds = $existingQuery->pluck('class_id')
                        ->map(fn ($value): int => (int) $value)
                        ->all();
                }

                if ($existingClassIds !== []) {
                    $classQuery->whereNotIn('class_id', $existingClassIds);
                }

                $classes = $classQuery->get()->map(fn ($row): array => [
                    'class_id' => (int) $row->class_id,
                    'class' => (string) ($row->class ?? ''),
                ])->all();
            }
        }

        return response()->json([
            'grades' => $grades,
            'classes' => $classes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $year = $request->input('year');
        $year = is_numeric($year) ? (int) $year : null;
        $gradeId = $request->input('grade_id');
        $gradeId = is_numeric($gradeId) ? (int) $gradeId : null;
        $classId = $request->input('class_id');
        $classId = is_numeric($classId) ? (int) $classId : null;
        $approved = $request->input('approved_std_count');
        $approved = is_numeric($approved) ? (int) $approved : 0;

        if ($year === null || $year < 2000 || $year > 2100) {
            return response()->json(['message' => 'Invalid year.'], 422);
        }

        if ($gradeId === null || $gradeId <= 0) {
            return response()->json(['message' => 'Select a grade first.'], 422);
        }

        if ($classId === null || $classId <= 0) {
            return response()->json(['message' => 'Select a class first.'], 422);
        }

        $censusId = $this->isAdministrator($user)
            ? $this->resolveRequestedSchoolCensusId($user)
            : $this->resolveUserCensusId($user);

        if ($censusId === null) {
            Log::warning('Class add blocked: school not resolved.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
            ]);

            return response()->json(['message' => 'Select a school first.'], 422);
        }

        Log::info('Class add started.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'census_id' => $censusId,
            'year' => $year,
            'grade_id' => $gradeId,
            'class_id' => $classId,
        ]);

        $gradeClassTable = (new SchoolGradeClass())->getTable();
        $columns = Schema::getColumnListing($gradeClassTable);

        $gradeExists = SchoolGrade::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('grade_id', $gradeId)
            ->when(Schema::hasColumn('school_grade_tbl', 'is_deleted'), function ($query): void {
                $query->where('is_deleted', 0);
            })
            ->exists();

        if (!$gradeExists) {
            Log::warning('Class add blocked: grade not initialized for year.', [
                'user_id' => $user->user_id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
            ]);

            return response()->json(['message' => 'Selected grade is not available for the selected year.'], 422);
        }

        $gradeStreamId = Grade::query()->where('grade_id', $gradeId)->value('stream_id');
        $classStreamId = SchoolClass::query()->where('class_id', $classId)->value('stream_id');
        if (!is_numeric($gradeStreamId) || !is_numeric($classStreamId) || (int) $gradeStreamId !== (int) $classStreamId) {
            Log::warning('Class add blocked: class stream mismatch.', [
                'user_id' => $user->user_id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'grade_stream_id' => $gradeStreamId,
                'class_stream_id' => $classStreamId,
            ]);

            return response()->json(['message' => 'Selected class is not valid for the selected grade.'], 422);
        }

        $existingQuery = SchoolGradeClass::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('grade_id', $gradeId)
            ->where('class_id', $classId);

        $existing = $existingQuery->first();
        if ($existing !== null && (!Schema::hasColumn($gradeClassTable, 'is_deleted') || (int) ($existing->is_deleted ?? 0) === 0)) {
            Log::warning('Class add blocked: class already exists.', [
                'user_id' => $user->user_id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
            ]);

            return response()->json(['message' => 'Selected class already exists for the selected grade and year.'], 422);
        }

        $now = now();

        if ($existing !== null && Schema::hasColumn($gradeClassTable, 'is_deleted')) {
            $updates = [
                'is_deleted' => 0,
                'stf_id' => 0,
            ];

            if (in_array('approved_std_count', $columns, true)) {
                $updates['approved_std_count'] = max(0, $approved);
            }
            if (in_array('student_count', $columns, true)) {
                $updates['student_count'] = 0;
            }
            if (in_array('std_count', $columns, true)) {
                $updates['std_count'] = 0;
            }
            if (in_array('date_updated', $columns, true)) {
                $updates['date_updated'] = $now;
            }
            if (in_array('updated_dt', $columns, true)) {
                $updates['updated_dt'] = $now;
            }

            SchoolGradeClass::query()
                ->where('sch_grd_cls_id', $existing->sch_grd_cls_id)
                ->update($updates);

            Log::info('Class restored from deleted row.', [
                'user_id' => $user->user_id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'sch_grd_cls_id' => $existing->sch_grd_cls_id ?? null,
            ]);

            return response()->json(['message' => 'Class added successfully.']);
        }

        $insert = [
            'census_id' => $censusId,
            'year' => $year,
            'grade_id' => $gradeId,
            'class_id' => $classId,
            'stf_id' => 0,
        ];

        if (in_array('approved_std_count', $columns, true)) {
            $insert['approved_std_count'] = max(0, $approved);
        }
        if (in_array('student_count', $columns, true)) {
            $insert['student_count'] = 0;
        }
        if (in_array('std_count', $columns, true)) {
            $insert['std_count'] = 0;
        }
        if (in_array('is_deleted', $columns, true)) {
            $insert['is_deleted'] = 0;
        }
        if (in_array('date_added', $columns, true)) {
            $insert['date_added'] = $now;
        }
        if (in_array('date_updated', $columns, true)) {
            $insert['date_updated'] = $now;
        }
        if (in_array('updated_dt', $columns, true)) {
            $insert['updated_dt'] = $now;
        }

        SchoolGradeClass::query()->create($insert);

        Log::info('Class added successfully.', [
            'user_id' => $user->user_id ?? null,
            'census_id' => $censusId,
            'year' => $year,
            'grade_id' => $gradeId,
            'class_id' => $classId,
        ]);

        return response()->json(['message' => 'Class added successfully.']);
    }

    public function update(Request $request, int $classRowId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $gradeClassTable = (new SchoolGradeClass())->getTable();
        $columns = Schema::getColumnListing($gradeClassTable);
        $schoolColumn = $this->resolveSchoolColumn($columns);

        $query = DB::table("{$gradeClassTable} as sgct")->where('sgct.sch_grd_cls_id', $classRowId);
        if (in_array('is_deleted', $columns, true)) {
            $query->where('sgct.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgct', $schoolColumn);

        $row = $query->first();
        if ($row === null) {
            Log::warning('Class update blocked: row not found.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'sch_grd_cls_id' => $classRowId,
            ]);

            return response()->json(['message' => 'Class row not found.'], 404);
        }

        $updates = [];

        $stfId = $request->input('stf_id');
        if (in_array('stf_id', $columns, true)) {
            $resolvedStfId = is_numeric($stfId) && (int) $stfId > 0 ? (int) $stfId : 0;

            if ($resolvedStfId > 0) {
                $duplicateTeacherQuery = SchoolGradeClass::query()->from("{$gradeClassTable} as sgct")
                    ->where('sgct.sch_grd_cls_id', '!=', $classRowId)
                    ->where('sgct.year', (int) ($row->year ?? 0))
                    ->where('sgct.stf_id', $resolvedStfId);

                if ($schoolColumn !== null && isset($row->{$schoolColumn})) {
                    $duplicateTeacherQuery->where("sgct.{$schoolColumn}", $row->{$schoolColumn});
                }

                if (in_array('is_deleted', $columns, true)) {
                    $duplicateTeacherQuery->where('sgct.is_deleted', 0);
                }

                if ($duplicateTeacherQuery->exists()) {
                    Log::warning('Class update blocked: class teacher already assigned for year.', [
                        'user_id' => $user->user_id ?? null,
                        'role_id' => $user->role_id ?? null,
                        'sch_grd_cls_id' => $classRowId,
                        'census_id' => $row->{$schoolColumn} ?? null,
                        'year' => $row->year ?? null,
                        'stf_id' => $resolvedStfId,
                    ]);

                    return response()->json(['message' => 'This staff member is already assigned to another class for the selected year.'], 422);
                }
            }

            $updates['stf_id'] = $resolvedStfId;
        }

        $approved = $request->input('approved_std_count');
        if (in_array('approved_std_count', $columns, true) && $approved !== null) {
            $updates['approved_std_count'] = is_numeric($approved) ? max(0, (int) $approved) : 0;
        }

        if (in_array('date_updated', $columns, true)) {
            $updates['date_updated'] = now();
        }
        if (in_array('updated_dt', $columns, true)) {
            $updates['updated_dt'] = now();
        }

        if (!empty($updates)) {
            SchoolGradeClass::query()->where('sch_grd_cls_id', $classRowId)->update($updates);
        }

        Log::info('Class row updated.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'sch_grd_cls_id' => $classRowId,
            'census_id' => $row->{$schoolColumn} ?? null,
        ]);

        return response()->json(['message' => 'Class row updated.']);
    }

    public function destroy(int $classRowId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $gradeClassTable = (new SchoolGradeClass())->getTable();
        $columns = Schema::getColumnListing($gradeClassTable);
        $schoolColumn = $this->resolveSchoolColumn($columns);

        $query = DB::table("{$gradeClassTable} as sgct")->where('sgct.sch_grd_cls_id', $classRowId);
        if (in_array('is_deleted', $columns, true)) {
            $query->where('sgct.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgct', $schoolColumn);

        $row = $query->first();
        if ($row === null) {
            Log::warning('Class delete blocked: row not found.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'sch_grd_cls_id' => $classRowId,
            ]);

            return response()->json(['message' => 'Class row not found.'], 404);
        }

        if (in_array('is_deleted', $columns, true)) {
            $updates = ['is_deleted' => 1];
            if (in_array('date_updated', $columns, true)) {
                $updates['date_updated'] = now();
            }
            if (in_array('updated_dt', $columns, true)) {
                $updates['updated_dt'] = now();
            }

            SchoolGradeClass::query()->where('sch_grd_cls_id', $classRowId)->update($updates);
        } else {
            SchoolGradeClass::query()->where('sch_grd_cls_id', $classRowId)->delete();
        }

        Log::info('Class row deleted.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'sch_grd_cls_id' => $classRowId,
            'census_id' => $row->{$schoolColumn} ?? null,
        ]);

        return response()->json(['message' => 'Class row deleted.']);
    }
}
