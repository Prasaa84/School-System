<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StudentIndexRequest;
use App\Http\Requests\Api\V1\StudentStoreRequest;
use App\Models\Guardian;
use App\Models\SchoolDetail;
use App\Models\SdsUser;
use App\Services\FeatureAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StudentController extends Controller
{
    use AppliesSchoolScope;

    public function __construct(private readonly FeatureAccessService $featureAccess)
    {
    }

    public function index(StudentIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';

        $hasSchoolTable = Schema::hasTable('school_details_tbl');

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
            ->when($search !== '', function ($builder) use ($search, $hasSchoolTable): void {
                $builder->where(function ($inner) use ($search, $hasSchoolTable): void {
                    $inner
                        ->where('st.index_no', 'like', "%{$search}%")
                        ->orWhere('st.name_with_initials', 'like', "%{$search}%")
                        ->orWhere('st.fullname', 'like', "%{$search}%");

                    if ($hasSchoolTable) {
                        $inner
                            ->orWhere('st.census_id', 'like', "%{$search}%")
                            ->orWhere('sc.sch_name', 'like', "%{$search}%");
                    }
                });
            })
            ->orderBy('st.census_id')->orderBy('st.index_no');

        if ($hasSchoolTable) {
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

        $studentIds = $items
            ->pluck('std_id')
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $currentGradeClasses = collect();

        if (!empty($studentIds) && Schema::hasTable('student_grade_class_tbl') && Schema::hasTable('school_grade_class_tbl')) {
            $gradeClassRows = DB::table('student_grade_class_tbl as sgc')
                ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
                ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
                ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
                ->whereIn('sgc.std_id', $studentIds)
                ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), function ($query): void {
                    $query->where('sgc.is_deleted', 0);
                })
                ->when(Schema::hasColumn('school_grade_class_tbl', 'is_deleted'), function ($query): void {
                    $query->where('sgct.is_deleted', 0);
                })
                ->select([
                    'sgc.std_id',
                    'sgc.st_gr_cl_id',
                    'sgct.year',
                    'gt.grade',
                    'ct.class',
                ])
                ->orderByDesc('sgct.year')
                ->orderByDesc('sgc.st_gr_cl_id')
                ->get();

            $currentGradeClasses = $gradeClassRows
                ->unique(fn ($row): int => (int) ($row->std_id ?? 0))
                ->keyBy(fn ($row): int => (int) ($row->std_id ?? 0));
        }

        $data = $items->map(function ($student) use ($currentGradeClasses): array {
            $gradeClass = $currentGradeClasses->get((int) ($student->std_id ?? 0));

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
                'census_id' => isset($student->census_id) ? (string) $student->census_id : null,
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
        $requestedCensusId = isset($validated['census_id']) ? trim((string) $validated['census_id']) : '';
        $censusId = $isAdmin
            ? ($requestedCensusId !== '' ? $this->resolveCanonicalSchoolCensusId($requestedCensusId) : $adminScopedCensusId)
            : $this->resolveUserCensusId($user);

        if ($censusId === null) {
            return response()->json([
                'message' => __('messages.students.census_required'),
            ], 422);
        }

        if (!$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
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
        $year = is_numeric($validated['year'] ?? null) ? (int) $validated['year'] : null;

        if ($gradeId !== null && $classId !== null) {
            $gradeStreamId = DB::table('grade_tbl')->where('grade_id', $gradeId)->value('stream_id');
            $classStreamId = DB::table('class_tbl')->where('class_id', $classId)->value('stream_id');

            if ($gradeStreamId === null || $classStreamId === null || (int) $gradeStreamId !== (int) $classStreamId) {
                return response()->json([
                    'message' => __('messages.students.grade_class_mismatch'),
                ], 422);
            }
        }

        $hasAnyAssignment = $gradeId !== null || $classId !== null || $year !== null;
        $hasCompleteAssignment = $gradeId !== null && $classId !== null && $year !== null;
        if ($hasAnyAssignment && !$hasCompleteAssignment) {
            $errors = [];
            if ($gradeId === null || $classId === null) {
                $message = __('messages.students.validation.grade_class_required');
                $errors['grade_id'] = [$message];
                $errors['class_id'] = [$message];
            }
            if ($year === null) {
                $errors['year'] = [__('messages.students.validation.year_invalid')];
            }

            return $this->invalidStudentAssignmentResponse($errors);
        }

        $schoolGradeClassId = null;
        if ($hasCompleteAssignment) {
            $schoolGradeClassId = $this->resolveSchoolGradeClassId($gradeId, $classId, $year, (string) $censusId);
            if ($schoolGradeClassId === null) {
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

            DB::transaction(function () use ($validated, $indexNo, $censusId, $schoolGradeClassId): void {
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

                $studentId = (int) DB::table('student_tbl')->insertGetId($studentData);
                Log::info('Student store: student_tbl inserted.', [
                    'std_id' => $studentId,
                    'index_no' => $indexNo,
                    'census_id' => $censusId,
                ]);

                if ($schoolGradeClassId !== null && Schema::hasTable('student_grade_class_tbl')) {
                    DB::table('student_grade_class_tbl')->insert([
                        'std_id' => $studentId,
                        'sch_grd_cls_id' => $schoolGradeClassId,
                        'date_added' => $now,
                        'date_updated' => $now,
                        'is_deleted' => 0,
                    ]);

                    Log::info('Student store: student_grade_class_tbl inserted.', [
                        'std_id' => $studentId,
                        'sch_grd_cls_id' => $schoolGradeClassId,
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
                    Guardian::query()->insert([
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

        $censusId = $this->normalizeCensusId($student->census_id ?? null);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (!$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $gradeClass = null;
        if (Schema::hasTable('student_grade_class_tbl') && Schema::hasTable('school_grade_class_tbl')) {
            $gradeClassQuery = DB::table('student_grade_class_tbl as sgc')
                ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
                ->select(['sgct.grade_id', 'sgct.class_id', 'sgct.year', 'sgc.st_gr_cl_id'])
                ->where('sgc.std_id', $studentId)
                ->orderByDesc('sgct.year')
                ->orderByDesc('sgc.st_gr_cl_id');

            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $gradeClassQuery->where('sgc.is_deleted', 0);
            }
            if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                $gradeClassQuery->where('sgct.is_deleted', 0);
            }

            $gradeClass = $gradeClassQuery->first();
        }

        $guardian = null;
        if (Schema::hasTable('guardian_tbl')) {
            $guardianQuery = Guardian::query()->from('guardian_tbl as g')
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

        $validated = $request->validated();
        $originalCensusId = $this->normalizeCensusId($student->census_id ?? null);
        if ($originalCensusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (!$this->featureAccess->hasFeature($user, $originalCensusId, FeatureAccessService::STUDENT_UPDATE)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $isAdmin = $this->isAdministrator($user);
        $censusId = $originalCensusId;
        $requestedCensusId = isset($validated['census_id']) ? trim((string) $validated['census_id']) : '';
        if ($isAdmin && $requestedCensusId !== '') {
            $resolvedCensusId = $this->resolveCanonicalSchoolCensusId($requestedCensusId);
            if ($resolvedCensusId !== null) {
                $censusId = $resolvedCensusId;
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
        $year = is_numeric($validated['year'] ?? null) ? (int) $validated['year'] : null;

        if ($gradeId !== null && $classId !== null) {
            $gradeStreamId = DB::table('grade_tbl')->where('grade_id', $gradeId)->value('stream_id');
            $classStreamId = DB::table('class_tbl')->where('class_id', $classId)->value('stream_id');

            if ($gradeStreamId === null || $classStreamId === null || (int) $gradeStreamId !== (int) $classStreamId) {
                return response()->json([
                    'message' => __('messages.students.grade_class_mismatch'),
                ], 422);
            }
        }

        $hasAnyAssignment = $gradeId !== null || $classId !== null || $year !== null;
        $hasCompleteAssignment = $gradeId !== null && $classId !== null && $year !== null;
        if ($hasAnyAssignment && !$hasCompleteAssignment) {
            $errors = [];
            if ($gradeId === null || $classId === null) {
                $message = __('messages.students.validation.grade_class_required');
                $errors['grade_id'] = [$message];
                $errors['class_id'] = [$message];
            }
            if ($year === null) {
                $errors['year'] = [__('messages.students.validation.year_invalid')];
            }

            return $this->invalidStudentAssignmentResponse($errors);
        }

        $schoolGradeClassId = null;
        if ($hasCompleteAssignment) {
            $schoolGradeClassId = $this->resolveSchoolGradeClassId($gradeId, $classId, $year, (string) $censusId);
            if ($schoolGradeClassId === null) {
                return response()->json([
                    'message' => __('messages.students.grade_class_mismatch'),
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($studentId, $student, $validated, $indexNo, $originalCensusId, $censusId, $hasCompleteAssignment, $schoolGradeClassId): void {
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

                if (Schema::hasTable('student_grade_class_tbl') && $originalCensusId !== $censusId) {
                    $assignmentQuery = DB::table('student_grade_class_tbl')->where('std_id', $studentId);
                    if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                        $assignmentUpdates = ['is_deleted' => 1];
                        if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                            $assignmentUpdates['date_updated'] = $now;
                        }
                        $assignmentQuery->where('is_deleted', 0)->update($assignmentUpdates);
                    } else {
                        $assignmentQuery->delete();
                    }
                }

                if ($hasCompleteAssignment && $schoolGradeClassId !== null && Schema::hasTable('student_grade_class_tbl')) {
                    $assignmentQuery = DB::table('student_grade_class_tbl')->where('std_id', $studentId);
                    if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                        $assignmentUpdates = ['is_deleted' => 1];
                        if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                            $assignmentUpdates['date_updated'] = $now;
                        }
                        $assignmentQuery->where('is_deleted', 0)->update($assignmentUpdates);
                    } else {
                        $assignmentQuery->delete();
                    }

                    $insert = [
                        'std_id' => $studentId,
                        'sch_grd_cls_id' => $schoolGradeClassId,
                    ];
                    if (Schema::hasColumn('student_grade_class_tbl', 'date_added')) {
                        $insert['date_added'] = $now;
                    }
                    if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                        $insert['date_updated'] = $now;
                    }
                    if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                        $insert['is_deleted'] = 0;
                    }

                    DB::table('student_grade_class_tbl')->insert($insert);
                }

                if (Schema::hasTable('guardian_tbl')) {
                    if ($originalCensusId !== $censusId && Schema::hasColumn('guardian_tbl', 'census_id')) {
                        $updates = ['census_id' => $censusId];
                        if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                            $updates['date_updated'] = $now;
                        }

                        Guardian::query()
                            ->where('index_no', $oldIndexNo)
                            ->where('census_id', $originalCensusId)
                            ->update($updates);
                    }

                    if ($oldIndexNo !== '' && $oldIndexNo !== $indexNo) {
                        $query = Guardian::query()->where('index_no', $oldIndexNo);
                        if (Schema::hasColumn('guardian_tbl', 'census_id')) {
                            $query->where('census_id', $censusId);
                        }

                        $updates = ['index_no' => $indexNo];
                        if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                            $updates['date_updated'] = $now;
                        }

                        $query->update($updates);
                    }

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

                    $exists = Guardian::query()->where($guardianBase)->exists();
                    if ($exists) {
                        Guardian::query()->where($guardianBase)->update($guardianUpdates);
                    } else {
                        $hasData = collect($guardianUpdates)->except(['is_deleted', 'date_updated'])->contains(fn ($v): bool => trim((string) $v) !== '');
                        if ($hasData) {
                            $insert = array_merge($guardianBase, $guardianUpdates);
                            if (Schema::hasColumn('guardian_tbl', 'date_added')) {
                                $insert['date_added'] = $now;
                            }
                            Guardian::query()->insert($insert);
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

        $censusId = $this->normalizeCensusId($student->census_id ?? null);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (!$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_DELETE)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
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

                if (Schema::hasTable('student_grade_class_tbl')) {
                    $query = DB::table('student_grade_class_tbl')->where('std_id', $studentId);
                    if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                        $updates = ['is_deleted' => 1];
                        if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                            $updates['date_updated'] = $now;
                        }
                        $query->update($updates);
                    } else {
                        $query->delete();
                    }
                }

                if (Schema::hasTable('guardian_tbl')) {
                    $query = Guardian::query()->where('index_no', $indexNo);
                    if (Schema::hasColumn('guardian_tbl', 'census_id')) {
                        $query->where('census_id', $censusId);
                    }

                    if (Schema::hasColumn('guardian_tbl', 'is_deleted')) {
                        $updates = ['is_deleted' => 1];
                        if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
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

    private function resolveSchoolGradeClassId(int $gradeId, int $classId, int $year, string $censusId): ?int
    {
        if (!Schema::hasTable('school_grade_class_tbl')) {
            return null;
        }

        $query = DB::table('school_grade_class_tbl')
            ->where('grade_id', $gradeId)
            ->where('class_id', $classId)
            ->where('year', $year)
            ->whereIn('census_id', $this->censusCandidates($censusId));

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $schoolGradeClassId = $query
            ->orderByDesc('sch_grd_cls_id')
            ->value('sch_grd_cls_id');

        return is_numeric($schoolGradeClassId) ? (int) $schoolGradeClassId : null;
    }

    /**
     * @return array<int, string>
     */
    private function censusCandidates(string $censusId): array
    {
        $raw = trim($censusId);
        $candidates = collect([$raw])->filter(fn ($value): bool => $value !== '');

        if (is_numeric($raw)) {
            $asNumber = (string) ((int) $raw);
            $candidates
                ->push($asNumber)
                ->push(str_pad($asNumber, 5, '0', STR_PAD_LEFT))
                ->push(str_pad($asNumber, 7, '0', STR_PAD_LEFT));
        }

        return $candidates->uniqueStrict()->values()->all();
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    private function invalidStudentAssignmentResponse(array $errors): JsonResponse
    {
        return response()->json([
            'message' => __('messages.request.validation_failed'),
            'errors' => $errors,
        ], 422);
    }
}




