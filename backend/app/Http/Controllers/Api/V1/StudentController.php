<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StudentIndexRequest;
use App\Http\Requests\Api\V1\StudentStoreRequest;
use App\Models\SchoolDetail;
use App\Models\SdsUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StudentController extends Controller
{
    use AppliesSchoolScope;

    public function index(StudentIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';

        $studentsQuery = DB::table('student_tbl as st')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                DB::raw('st.fullname as full_name'),
                'st.phone_no',
                'st.whatsapp_no',
                'st.dob',
                'st.d_o_admission',
                'st.census_id',
                DB::raw('st.date_updated as last_update'),
            ])
            ->where('st.is_deleted', 0)
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('st.index_no', 'like', "%{$search}%")
                        ->orWhere('st.name_with_initials', 'like', "%{$search}%")
                        ->orWhere('st.fullname', 'like', "%{$search}%");
                });
            })
            ->orderBy('st.index_no');

        if (Schema::hasTable('school_details_tbl')) {
            $schoolHasIsDeleted = Schema::hasColumn('school_details_tbl', 'is_deleted');
            $studentsQuery
                ->leftJoin('school_details_tbl as sc', function ($join) use ($schoolHasIsDeleted): void {
                    $join->on('st.census_id', '=', 'sc.census_id');
                    if ($schoolHasIsDeleted) {
                        $join->where('sc.is_deleted', 0);
                    }
                })
                ->addSelect(DB::raw('sc.sch_name as school_name'));
        }

        $this->applySchoolScope($studentsQuery, $this->authUser(), 'st', 'census_id');

        $students = $studentsQuery->paginate($perPage);
        $items = collect($students->items());

        $indexNumbers = $items
            ->pluck('index_no')
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->values()
            ->all();

        $currentGradeClasses = collect();

        if (!empty($indexNumbers)) {
            $latestYearSubquery = DB::table('student_grade_class_tbl as sgc_latest')
                ->selectRaw('sgc_latest.index_no, MAX(sgc_latest.year) as latest_year')
                ->where('sgc_latest.is_deleted', 0)
                ->groupBy('sgc_latest.index_no');

            $currentGradeClasses = DB::table('student_grade_class_tbl as sgc')
                ->joinSub($latestYearSubquery, 'latest', function ($join): void {
                    $join
                        ->on('sgc.index_no', '=', 'latest.index_no')
                        ->on('sgc.year', '=', 'latest.latest_year');
                })
                ->leftJoin('grade_tbl as gt', 'sgc.grade_id', '=', 'gt.grade_id')
                ->leftJoin('class_tbl as ct', 'sgc.class_id', '=', 'ct.class_id')
                ->whereIn('sgc.index_no', $indexNumbers)
                ->where('sgc.is_deleted', 0)
                ->select([
                    'sgc.index_no',
                    'sgc.year',
                    'gt.grade',
                    'ct.class',
                ])
                ->orderBy('sgc.index_no')
                ->get()
                ->keyBy(fn ($row) => (string) $row->index_no);
        }

        $data = $items->map(function ($student) use ($currentGradeClasses): array {
            $indexKey = (string) $student->index_no;
            $gradeClass = $currentGradeClasses->get($indexKey);

            $gradeClassLabel = 'N/A';
            $currentYear = null;

            if ($gradeClass !== null) {
                $grade = trim((string) ($gradeClass->grade ?? ''));
                $className = trim((string) ($gradeClass->class ?? ''));
                $gradeClassLabel = trim("{$grade} {$className}") !== '' ? trim("{$grade} {$className}") : 'N/A';
                $currentYear = isset($gradeClass->year) ? (int) $gradeClass->year : null;
            }

            return [
                'std_id' => (int) $student->std_id,
                'index_no' => (string) $student->index_no,
                'name_with_initials' => (string) ($student->name_with_initials ?? ''),
                'full_name' => (string) ($student->full_name ?? ''),
                'census_id' => isset($student->census_id) ? (int) $student->census_id : null,
                'school_name' => $student->school_name ?? null,
                'phone_no' => $student->phone_no,
                'whatsapp_no' => $student->whatsapp_no,
                'dob' => $student->dob,
                'd_o_admission' => $student->d_o_admission ?? null,
                'last_update' => $student->last_update,
                'grade_class' => $gradeClassLabel,
                'current_year' => $currentYear,
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        $user = $this->authUser();
        $isAdmin = $this->isAdministrator($user);

        $ethnicGroups = [];
        if (Schema::hasTable('ethnic_group_tbl')) {
            $query = DB::table('ethnic_group_tbl')->select(['ethnic_group_id', 'ethnic_group']);
            if (Schema::hasColumn('ethnic_group_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }
            $ethnicGroups = $query->orderBy('ethnic_group_id')->get()->map(fn ($row): array => [
                'id' => (int) $row->ethnic_group_id,
                'label' => (string) $row->ethnic_group,
            ])->all();
        }

        $religions = [];
        if (Schema::hasTable('religion_tbl')) {
            $query = DB::table('religion_tbl')->select(['religion_id', 'religion']);
            if (Schema::hasColumn('religion_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }
            $religions = $query->orderBy('religion_id')->get()->map(fn ($row): array => [
                'id' => (int) $row->religion_id,
                'label' => (string) $row->religion,
            ])->all();
        }

        $schools = [];
        $schoolTable = (new SchoolDetail())->getTable();
        if ($isAdmin && Schema::hasTable($schoolTable)) {
            $query = SchoolDetail::query()->select(['census_id', 'sch_name']);
            if (Schema::hasColumn($schoolTable, 'is_deleted')) {
                $query->where('is_deleted', 0);
            }
            $schools = $query->orderBy('sch_name')->get()->map(fn ($row): array => [
                'id' => (int) $row->census_id,
                'label' => (string) $row->sch_name,
            ])->all();
        }

        return response()->json([
            'ethnic_groups' => $ethnicGroups,
            'religions' => $religions,
            'schools' => $schools,
        ]);
    }

    public function store(StudentStoreRequest $request): JsonResponse
    {
        Log::info('Student store started.');

        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = $request->validated();

        $isAdmin = $this->isAdministrator($user);
        $adminScopedCensusId = $this->resolveRequestedSchoolCensusId($user);
        $censusId = $isAdmin
            ? (is_numeric($validated['census_id'] ?? null) ? (int) $validated['census_id'] : $adminScopedCensusId)
            : $this->resolveUserCensusId($user);

        if ($censusId === null) {
            return response()->json([
                'message' => __('messages.students.census_required'),
            ], 422);
        }

        $indexNo = trim((string) $validated['index_no']);

        $studentExists = DB::table('student_tbl')
            ->where('index_no', $indexNo)
            ->where('census_id', $censusId)
            ->where('is_deleted', 0)
            ->exists();

        if ($studentExists) {
            Log::warning('Student store rejected: duplicate admission.', [
                'index_no' => $indexNo,
                'census_id' => $censusId,
            ]);

            return response()->json([
                'message' => __('messages.students.already_exists', ['index_no' => $indexNo]),
            ], 422);
        }

        $gradeId = is_numeric($validated['grade_id'] ?? null) ? (int) $validated['grade_id'] : null;
        $classId = is_numeric($validated['class_id'] ?? null) ? (int) $validated['class_id'] : null;

        if ($gradeId !== null && $classId !== null) {
            $gradeStreamId = DB::table('grade_tbl')->where('grade_id', $gradeId)->value('stream_id');
            $classStreamId = DB::table('class_tbl')->where('class_id', $classId)->value('stream_id');

            if ($gradeStreamId === null || $classStreamId === null || (int) $gradeStreamId !== (int) $classStreamId) {
                return response()->json([
                    'message' => __('messages.students.grade_class_mismatch'),
                ], 422);
            }
        }

        try {
            Log::info('Student store transaction begin.', [
                'index_no' => $indexNo,
                'census_id' => $censusId,
            ]);

            DB::transaction(function () use ($validated, $indexNo, $censusId, $gradeId, $classId): void {
                $now = now();

                $studentData = [
                    'index_no' => $indexNo,
                    'fullname' => $validated['full_name'],
                    'name_with_initials' => $validated['name_with_initials'],
                    'address1' => $validated['address1'] ?? '',
                    'address2' => $validated['address2'] ?? '',
                    'phone_no' => $validated['phone_no'] ?? '',
                    'whatsapp_no' => $validated['whatsapp_no'] ?? '',
                    'phone_home' => $validated['phone_home'] ?? '',
                    'dob' => $validated['dob'] ?? null,
                    'email' => $validated['email'] ?? '',
                    'gender_id' => (int) $validated['gender_id'],
                    'ethnic_group_id' => is_numeric($validated['ethnic_group_id'] ?? null) ? (int) $validated['ethnic_group_id'] : 0,
                    'religion_id' => is_numeric($validated['religion_id'] ?? null) ? (int) $validated['religion_id'] : 0,
                    'd_o_admission' => $validated['d_o_admission'] ?? null,
                    'census_id' => $censusId,
                    'st_status_id' => 1,
                    'date_added' => $now,
                    'date_updated' => $now,
                    'is_deleted' => 0,
                ];

                DB::table('student_tbl')->insert($studentData);
                Log::info('Student store: student_tbl inserted.', [
                    'index_no' => $indexNo,
                    'census_id' => $censusId,
                ]);

                if ($gradeId !== null && $classId !== null) {
                    DB::table('student_grade_class_tbl')->insert([
                        'index_no' => $indexNo,
                        'grade_id' => $gradeId,
                        'class_id' => $classId,
                        'year' => (int) $validated['year'],
                        'census_id' => $censusId,
                        'date_added' => $now,
                        'date_updated' => $now,
                        'is_deleted' => 0,
                    ]);

                    Log::info('Student store: student_grade_class_tbl inserted.', [
                        'index_no' => $indexNo,
                        'census_id' => $censusId,
                        'grade_id' => $gradeId,
                        'class_id' => $classId,
                    ]);
                }

                $hasGuardianData = collect([
                    $validated['father_name'] ?? null,
                    $validated['father_job'] ?? null,
                    $validated['father_mobile'] ?? null,
                    $validated['mother_name'] ?? null,
                    $validated['mother_job'] ?? null,
                    $validated['mother_mobile'] ?? null,
                    $validated['guardian_name'] ?? null,
                    $validated['guardian_job'] ?? null,
                    $validated['guardian_mobile'] ?? null,
                ])->contains(fn ($value): bool => trim((string) $value) !== '');

                if ($hasGuardianData && Schema::hasTable('guardian_tbl')) {
                    DB::table('guardian_tbl')->insert([
                        'index_no' => $indexNo,
                        'census_id' => $censusId,
                        'f_name' => $validated['father_name'] ?? '',
                        'f_job' => $validated['father_job'] ?? '',
                        'f_mobile' => $validated['father_mobile'] ?? '',
                        'm_name' => $validated['mother_name'] ?? '',
                        'm_job' => $validated['mother_job'] ?? '',
                        'm_mobile' => $validated['mother_mobile'] ?? '',
                        'g_name' => $validated['guardian_name'] ?? '',
                        'g_job' => $validated['guardian_job'] ?? '',
                        'g_mobile' => $validated['guardian_mobile'] ?? '',
                        'date_added' => $now,
                        'date_updated' => $now,
                        'is_deleted' => 0,
                    ]);

                    Log::info('Student store: guardian_tbl inserted.', [
                        'index_no' => $indexNo,
                        'census_id' => $censusId,
                    ]);
                }
            });

            return response()->json([
                'message' => __('messages.students.create_success'),
                'data' => [
                    'index_no' => $indexNo,
                    'census_id' => $censusId,
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Student store failed.', [
                'index_no' => $indexNo ?? null,
                'census_id' => $censusId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('messages.students.create_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function show(int $studentId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $student = $this->loadStudentForWrite($studentId, $user);
        if ($student === null) {
            return response()->json(['message' => __('messages.students.not_found')], 404);
        }

        if (!$this->canManageStudentActions($user)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $censusId = is_numeric($student->census_id ?? null) ? (int) $student->census_id : null;
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        $gradeClass = null;
        if (Schema::hasTable('student_grade_class_tbl')) {
            $gradeClassQuery = DB::table('student_grade_class_tbl as sgc')
                ->select(['sgc.grade_id', 'sgc.class_id', 'sgc.year'])
                ->where('sgc.index_no', (string) $student->index_no)
                ->orderByDesc('sgc.year');

            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $gradeClassQuery->where('sgc.is_deleted', 0);
            }
            if (Schema::hasColumn('student_grade_class_tbl', 'census_id')) {
                $gradeClassQuery->where('sgc.census_id', $censusId);
            }

            $gradeClass = $gradeClassQuery->first();
        }

        $guardian = null;
        if (Schema::hasTable('guardian_tbl')) {
            $guardianQuery = DB::table('guardian_tbl as g')
                ->select([
                    DB::raw('g.f_name as father_name'),
                    DB::raw('g.f_job as father_job'),
                    DB::raw('g.f_mobile as father_mobile'),
                    DB::raw('g.m_name as mother_name'),
                    DB::raw('g.m_job as mother_job'),
                    DB::raw('g.m_mobile as mother_mobile'),
                    DB::raw('g.g_name as guardian_name'),
                    DB::raw('g.g_job as guardian_job'),
                    DB::raw('g.g_mobile as guardian_mobile'),
                ])
                ->where('g.index_no', (string) $student->index_no);

            if (Schema::hasColumn('guardian_tbl', 'is_deleted')) {
                $guardianQuery->where('g.is_deleted', 0);
            }
            if (Schema::hasColumn('guardian_tbl', 'census_id')) {
                $guardianQuery->where('g.census_id', $censusId);
            }

            $guardian = $guardianQuery->first();
        }

        return response()->json([
            'data' => [
                'std_id' => (int) $student->std_id,
                'census_id' => $censusId,
                'index_no' => (string) ($student->index_no ?? ''),
                'full_name' => (string) ($student->full_name ?? ''),
                'name_with_initials' => (string) ($student->name_with_initials ?? ''),
                'address1' => (string) ($student->address1 ?? ''),
                'address2' => (string) ($student->address2 ?? ''),
                'phone_no' => (string) ($student->phone_no ?? ''),
                'whatsapp_no' => (string) ($student->whatsapp_no ?? ''),
                'phone_home' => (string) ($student->phone_home ?? ''),
                'email' => (string) ($student->email ?? ''),
                'dob' => $student->dob,
                'd_o_admission' => $student->d_o_admission ?? null,
                'gender_id' => isset($student->gender_id) ? (int) $student->gender_id : 0,
                'ethnic_group_id' => isset($student->ethnic_group_id) ? (int) $student->ethnic_group_id : 0,
                'religion_id' => isset($student->religion_id) ? (int) $student->religion_id : 0,
                'grade_id' => isset($gradeClass?->grade_id) ? (int) $gradeClass->grade_id : 0,
                'class_id' => isset($gradeClass?->class_id) ? (int) $gradeClass->class_id : 0,
                'year' => isset($gradeClass?->year) ? (int) $gradeClass->year : null,
                'father_name' => (string) ($guardian->father_name ?? ''),
                'father_job' => (string) ($guardian->father_job ?? ''),
                'father_mobile' => (string) ($guardian->father_mobile ?? ''),
                'mother_name' => (string) ($guardian->mother_name ?? ''),
                'mother_job' => (string) ($guardian->mother_job ?? ''),
                'mother_mobile' => (string) ($guardian->mother_mobile ?? ''),
                'guardian_name' => (string) ($guardian->guardian_name ?? ''),
                'guardian_job' => (string) ($guardian->guardian_job ?? ''),
                'guardian_mobile' => (string) ($guardian->guardian_mobile ?? ''),
            ],
        ]);
    }
    public function update(StudentStoreRequest $request, int $studentId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $student = $this->loadStudentForWrite($studentId, $user);
        if ($student === null) {
            return response()->json(['message' => __('messages.students.not_found')], 404);
        }

        if (!$this->canManageStudentActions($user)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $validated = $request->validated();
        $originalCensusId = is_numeric($student->census_id ?? null) ? (int) $student->census_id : null;
        if ($originalCensusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        $isAdmin = $this->isAdministrator($user);
        $censusId = $originalCensusId;
        if ($isAdmin && is_numeric($validated['census_id'] ?? null)) {
            $requestedCensusId = (int) $validated['census_id'];
            if ($this->schoolExists($requestedCensusId)) {
                $censusId = $requestedCensusId;
            }
        }

        $indexNo = trim((string) $validated['index_no']);

        $duplicateExists = DB::table('student_tbl')
            ->where('index_no', $indexNo)
            ->where('census_id', $censusId)
            ->where('is_deleted', 0)
            ->where('std_id', '<>', $studentId)
            ->exists();

        if ($duplicateExists) {
            return response()->json([
                'message' => __('messages.students.already_exists', ['index_no' => $indexNo]),
            ], 422);
        }

        $gradeId = is_numeric($validated['grade_id'] ?? null) ? (int) $validated['grade_id'] : null;
        $classId = is_numeric($validated['class_id'] ?? null) ? (int) $validated['class_id'] : null;

        if ($gradeId !== null && $classId !== null) {
            $gradeStreamId = DB::table('grade_tbl')->where('grade_id', $gradeId)->value('stream_id');
            $classStreamId = DB::table('class_tbl')->where('class_id', $classId)->value('stream_id');

            if ($gradeStreamId === null || $classStreamId === null || (int) $gradeStreamId !== (int) $classStreamId) {
                return response()->json([
                    'message' => __('messages.students.grade_class_mismatch'),
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($studentId, $student, $validated, $indexNo, $originalCensusId, $censusId, $gradeId, $classId): void {
                $now = now();
                $oldIndexNo = (string) ($student->index_no ?? '');

                DB::table('student_tbl')->where('std_id', $studentId)->update([
                    'index_no' => $indexNo,
                    'fullname' => $validated['full_name'],
                    'name_with_initials' => $validated['name_with_initials'],
                    'address1' => $validated['address1'] ?? '',
                    'address2' => $validated['address2'] ?? '',
                    'phone_no' => $validated['phone_no'] ?? '',
                    'whatsapp_no' => $validated['whatsapp_no'] ?? '',
                    'phone_home' => $validated['phone_home'] ?? '',
                    'dob' => $validated['dob'] ?? null,
                    'email' => $validated['email'] ?? '',
                    'gender_id' => (int) $validated['gender_id'],
                    'ethnic_group_id' => is_numeric($validated['ethnic_group_id'] ?? null) ? (int) $validated['ethnic_group_id'] : 0,
                    'religion_id' => is_numeric($validated['religion_id'] ?? null) ? (int) $validated['religion_id'] : 0,
                    'd_o_admission' => $validated['d_o_admission'] ?? null,
                    'census_id' => $censusId,
                    'date_updated' => $now,
                ]);

                if ($originalCensusId !== $censusId) {
                    foreach (['student_grade_class_tbl', 'guardian_tbl'] as $table) {
                        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'index_no') || !Schema::hasColumn($table, 'census_id')) {
                            continue;
                        }

                        $updates = ['census_id' => $censusId];
                        if (Schema::hasColumn($table, 'date_updated')) {
                            $updates['date_updated'] = $now;
                        }

                        DB::table($table)
                            ->where('index_no', $oldIndexNo)
                            ->where('census_id', $originalCensusId)
                            ->update($updates);
                    }
                }

                if ($oldIndexNo !== '' && $oldIndexNo !== $indexNo) {
                    foreach (['student_grade_class_tbl', 'guardian_tbl'] as $table) {
                        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'index_no')) {
                            continue;
                        }

                        $query = DB::table($table)->where('index_no', $oldIndexNo);
                        if (Schema::hasColumn($table, 'census_id')) {
                            $query->where('census_id', $censusId);
                        }

                        $updates = ['index_no' => $indexNo];
                        if (Schema::hasColumn($table, 'date_updated')) {
                            $updates['date_updated'] = $now;
                        }

                        $query->update($updates);
                    }
                }

                if ($gradeId !== null && $classId !== null && Schema::hasTable('student_grade_class_tbl')) {
                    $year = (int) $validated['year'];
                    $base = ['index_no' => $indexNo, 'year' => $year];
                    if (Schema::hasColumn('student_grade_class_tbl', 'census_id')) {
                        $base['census_id'] = $censusId;
                    }

                    $updates = ['grade_id' => $gradeId, 'class_id' => $classId];
                    if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                        $updates['is_deleted'] = 0;
                    }
                    if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                        $updates['date_updated'] = $now;
                    }

                    $exists = DB::table('student_grade_class_tbl')->where($base)->exists();
                    if ($exists) {
                        DB::table('student_grade_class_tbl')->where($base)->update($updates);
                    } else {
                        $insert = array_merge($base, $updates);
                        if (Schema::hasColumn('student_grade_class_tbl', 'date_added')) {
                            $insert['date_added'] = $now;
                        }
                        DB::table('student_grade_class_tbl')->insert($insert);
                    }
                }

                if (Schema::hasTable('guardian_tbl')) {
                    $guardianBase = ['index_no' => $indexNo];
                    if (Schema::hasColumn('guardian_tbl', 'census_id')) {
                        $guardianBase['census_id'] = $censusId;
                    }

                    $guardianUpdates = [
                        'f_name' => trim((string) ($validated['father_name'] ?? '')),
                        'f_job' => trim((string) ($validated['father_job'] ?? '')),
                        'f_mobile' => trim((string) ($validated['father_mobile'] ?? '')),
                        'm_name' => trim((string) ($validated['mother_name'] ?? '')),
                        'm_job' => trim((string) ($validated['mother_job'] ?? '')),
                        'm_mobile' => trim((string) ($validated['mother_mobile'] ?? '')),
                        'g_name' => trim((string) ($validated['guardian_name'] ?? '')),
                        'g_job' => trim((string) ($validated['guardian_job'] ?? '')),
                        'g_mobile' => trim((string) ($validated['guardian_mobile'] ?? '')),
                    ];

                    if (Schema::hasColumn('guardian_tbl', 'is_deleted')) {
                        $guardianUpdates['is_deleted'] = 0;
                    }
                    if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                        $guardianUpdates['date_updated'] = $now;
                    }

                    $exists = DB::table('guardian_tbl')->where($guardianBase)->exists();
                    if ($exists) {
                        DB::table('guardian_tbl')->where($guardianBase)->update($guardianUpdates);
                    } else {
                        $hasData = collect($guardianUpdates)->except(['is_deleted', 'date_updated'])->contains(fn ($v): bool => trim((string) $v) !== '');
                        if ($hasData) {
                            $insert = array_merge($guardianBase, $guardianUpdates);
                            if (Schema::hasColumn('guardian_tbl', 'date_added')) {
                                $insert['date_added'] = $now;
                            }
                            DB::table('guardian_tbl')->insert($insert);
                        }
                    }
                }
            });

            return response()->json([
                'message' => __('messages.students.update_success'),
                'data' => [
                    'std_id' => $studentId,
                    'index_no' => $indexNo,
                    'census_id' => $censusId,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Student update failed.', [
                'std_id' => $studentId,
                'census_id' => $censusId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('messages.students.update_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function destroy(int $studentId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $student = $this->loadStudentForWrite($studentId, $user);
        if ($student === null) {
            return response()->json(['message' => __('messages.students.not_found')], 404);
        }

        if (!$this->canManageStudentActions($user)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $censusId = is_numeric($student->census_id ?? null) ? (int) $student->census_id : null;
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        $indexNo = (string) ($student->index_no ?? '');

        try {
            DB::transaction(function () use ($studentId, $indexNo, $censusId): void {
                $now = now();

                if (Schema::hasColumn('student_tbl', 'is_deleted')) {
                    $updates = ['is_deleted' => 1];
                    if (Schema::hasColumn('student_tbl', 'date_updated')) {
                        $updates['date_updated'] = $now;
                    }
                    DB::table('student_tbl')->where('std_id', $studentId)->update($updates);
                } else {
                    DB::table('student_tbl')->where('std_id', $studentId)->delete();
                }

                foreach (['student_grade_class_tbl', 'guardian_tbl'] as $table) {
                    if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'index_no')) {
                        continue;
                    }

                    $query = DB::table($table)->where('index_no', $indexNo);
                    if (Schema::hasColumn($table, 'census_id')) {
                        $query->where('census_id', $censusId);
                    }

                    if (Schema::hasColumn($table, 'is_deleted')) {
                        $updates = ['is_deleted' => 1];
                        if (Schema::hasColumn($table, 'date_updated')) {
                            $updates['date_updated'] = $now;
                        }
                        $query->update($updates);
                    } else {
                        $query->delete();
                    }
                }
            });

            return response()->json([
                'message' => __('messages.students.delete_success'),
            ]);
        } catch (Throwable $e) {
            Log::error('Student delete failed.', [
                'std_id' => $studentId,
                'index_no' => $indexNo,
                'census_id' => $censusId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('messages.students.delete_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    private function canManageStudentActions(?SdsUser $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);

        return in_array($roleId, [1, 2, 4], true);
    }

    private function loadStudentForWrite(int $studentId, ?SdsUser $user): ?object
    {
        $query = DB::table('student_tbl as st')
            ->select([
                'st.std_id',
                'st.index_no',
                DB::raw('st.fullname as full_name'),
                'st.name_with_initials',
                'st.address1',
                'st.address2',
                'st.phone_no',
                'st.whatsapp_no',
                'st.phone_home',
                'st.email',
                'st.dob',
                'st.d_o_admission',
                'st.gender_id',
                'st.ethnic_group_id',
                'st.religion_id',
                'st.census_id',
            ])
            ->where('st.std_id', $studentId)
            ->where('st.is_deleted', 0);

        $this->applySchoolScope($query, $user, 'st', 'census_id');

        return $query->first();
    }
}










