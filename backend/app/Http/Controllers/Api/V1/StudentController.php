<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StudentImportRequest;
use App\Http\Requests\Api\V1\StudentIndexRequest;
use App\Http\Requests\Api\V1\StudentStoreRequest;
use App\Models\Guardian;
use App\Models\SchoolDetail;
use App\Models\SdsUser;
use App\Models\StudentGradeClass;
use App\Services\FeatureAccessService;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class StudentController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    /**
     * Column positions in the shared student import template.
     *
     * @var array<string, int>
     */
    private const IMPORT_COLUMNS = [
        'index_no' => 0,
        'full_name' => 1,
        'name_with_initials' => 2,
        'address1' => 3,
        'address2' => 4,
        'phone_no' => 5,
        'whatsapp_no' => 6,
        'phone_home' => 7,
        'dob' => 8,
        'gender_id' => 9,
        'ethnic_group_id' => 10,
        'religion_id' => 11,
        'd_o_admission' => 12,
    ];

    public function __construct(
        private readonly FeatureAccessService $featureAccess,
        private readonly StudentService $studentService,
    )
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

        if (!empty($studentIds) && Schema::hasTable('student_grade_class_tbl') && Schema::hasTable('school_grade_class_tbl')) {
            $gradeClassQuery = DB::table('student_grade_class_tbl as sgc')
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
                ])
                ->orderByDesc('sgct.year')
                ->orderByDesc('sgc.st_gr_cl_id');

            if ($gradeLabelColumn !== null) {
                $gradeClassQuery->addSelect(DB::raw("gt.{$gradeLabelColumn} as grade"));
            }

            if ($classLabelColumn !== null) {
                $gradeClassQuery->addSelect(DB::raw("ct.{$classLabelColumn} as class"));
            }

            $gradeClassRows = $gradeClassQuery->get();

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
            $ethnicLabelColumn = $this->resolveLookupLabelColumn('ethnic_group_tbl', [
                'ethnic_group_en',
                'ethnic_group_si',
                'ethnic_group_ta',
            ]);

            $query = DB::table('ethnic_group_tbl')->select(['ethnic_group_id']);
            if ($ethnicLabelColumn !== null) {
                $query->addSelect(DB::raw("{$ethnicLabelColumn} as label"));
            }
            if (Schema::hasColumn('ethnic_group_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }
            $ethnicGroups = $query->orderBy('ethnic_group_id')->get()->map(fn ($row): array => [
                'id' => (int) $row->ethnic_group_id,
                'label' => (string) ($row->label ?? ''),
            ])->all();
        }

        $religions = [];
        if (Schema::hasTable('religion_tbl')) {
            $religionLabelColumn = $this->resolveLookupLabelColumn('religion_tbl', [
                'religion_en',
                'religion_si',
                'religion_ta',
            ]);

            $query = DB::table('religion_tbl')->select(['religion_id']);
            if ($religionLabelColumn !== null) {
                $query->addSelect(DB::raw("{$religionLabelColumn} as label"));
            }
            if (Schema::hasColumn('religion_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }
            $religions = $query->orderBy('religion_id')->get()->map(fn ($row): array => [
                'id' => (int) $row->religion_id,
                'label' => (string) ($row->label ?? ''),
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

        try {
            $saved = $this->studentService->createStudent($validated, $censusId);

            return response()->json([
                'message' => __('messages.students.create_success'),
                'data' => [
                    'std_id' => $saved['std_id'],
                    'index_no' => $saved['index_no'],
                    'census_id' => $saved['census_id'],
                ],
            ], 201);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return response()->json([
                'message' => count($errors) > 0 ? collect($errors)->flatten()->first() : __('messages.request.validation_failed'),
                'errors' => $errors,
            ], 422);
        } catch (Throwable $e) {
            Log::error('Student store failed.', [
                'index_no' => $validated['index_no'] ?? null,
                'census_id' => $censusId ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('messages.students.create_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function import(StudentImportRequest $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = $request->validated();
        $censusId = $this->resolveImportCensusId($user, $validated);

        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (!$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        try {
            $sheets = Excel::toArray([], $request->file('file'));
        } catch (Throwable $e) {
            Log::warning('Student import file could not be read.', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => __('messages.students.import_file_invalid'),
            ], 422);
        }

        $sheet = $sheets[0] ?? [];
        if (count($sheet) < 2) {
            return response()->json([
                'message' => __('messages.students.import_empty'),
            ], 422);
        }

        Log::info('Student import started.', [
            'census_id' => $censusId,
            'file_name' => $request->file('file')?->getClientOriginalName(),
            'sheet_row_count' => count($sheet),
        ]);

        $ethnicMap = $this->buildLookupMap('ethnic_group_tbl', 'ethnic_group_id', [
            'ethnic_group_en',
            'ethnic_group_si',
            'ethnic_group_ta',
        ]);
        $religionMap = $this->buildLookupMap('religion_tbl', 'religion_id', [
            'religion_en',
            'religion_si',
            'religion_ta',
        ]);
        $imported = [];
        $failed = [];
        $skipped = 0;

        foreach ($sheet as $rowIndex => $row) {
            if ($rowIndex === 0) {
                continue;
            }

            if ($this->isImportRowEmpty($row)) {
                $skipped++;
                continue;
            }

            $rowNumber = $rowIndex + 1;
            $rowData = $this->buildImportRow($row, $ethnicMap, $religionMap);

            try {
                $studentData = $this->validateImportRow($rowData);
                $imported[] = $this->studentService->createStudent($studentData, $censusId);
            } catch (ValidationException $e) {
                Log::warning('Student import row validation failed.', [
                    'row' => $rowNumber,
                    'census_id' => $censusId,
                    'index_no' => $rowData['index_no'] ?? null,
                    'errors' => $e->errors(),
                ]);

                $failed[] = [
                    'row' => $rowNumber,
                    'message' => $this->firstValidationMessage($e),
                    'errors' => $e->errors(),
                ];
            } catch (Throwable $e) {
                Log::error('Student import row failed.', [
                    'row' => $rowNumber,
                    'census_id' => $censusId,
                    'error' => $e->getMessage(),
                ]);

                $failed[] = [
                    'row' => $rowNumber,
                    'message' => __('messages.students.import_failed'),
                ];
            }
        }

        if (count($imported) === 0 && count($failed) === 0) {
            return response()->json([
                'message' => __('messages.students.import_empty'),
            ], 422);
        }

        Log::info('Student import completed.', [
            'census_id' => $censusId,
            'imported_count' => count($imported),
            'failed_count' => count($failed),
            'skipped_count' => $skipped,
        ]);

        return response()->json([
            'message' => __('messages.students.import_success'),
            'data' => [
                'imported_count' => count($imported),
                'failed_count' => count($failed),
                'skipped_count' => $skipped,
                'failed_rows' => $failed,
            ],
        ]);
    }

    public function downloadTemplate(): JsonResponse|BinaryFileResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $path = storage_path('app/templates/students-template.xlsx');
        if (!is_file($path)) {
            Log::warning('Student template download failed: file missing.', [
                'path' => $path,
            ]);

            return response()->json([
                'message' => __('messages.students.template_missing'),
            ], 404);
        }

        Log::info('Student template download requested.', [
            'path' => $path,
        ]);

        return response()->download($path, 'students-template.xlsx');
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
            $gradeClass = StudentGradeClass::query()
                ->with('schoolGradeClass')
                ->where('std_id', $studentId)
                ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), function ($query): void {
                    $query->where('is_deleted', 0);
                })
                ->orderByDesc('st_gr_cl_id')
                ->first();
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
                'grade_id' => isset($gradeClass?->schoolGradeClass?->grade_id) ? (int) $gradeClass->schoolGradeClass->grade_id : 0,
                'class_id' => isset($gradeClass?->schoolGradeClass?->class_id) ? (int) $gradeClass->schoolGradeClass->class_id : 0,
                'year' => isset($gradeClass?->schoolGradeClass?->year) ? (int) $gradeClass->schoolGradeClass->year : null,
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

        try {
            $saved = $this->studentService->updateStudent($student, $validated, $censusId);

            return response()->json([
                'message' => __('messages.students.update_success'),
                'data' => [
                    'std_id' => $saved['std_id'],
                    'index_no' => $saved['index_no'],
                    'census_id' => $saved['census_id'],
                ],
            ]);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return response()->json([
                'message' => count($errors) > 0 ? collect($errors)->flatten()->first() : __('messages.request.validation_failed'),
                'errors' => $errors,
            ], 422);
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
            $this->studentService->deleteStudent($student);

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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveImportCensusId(SdsUser $user, array $validated): ?string
    {
        $isAdmin = $this->isAdministrator($user);
        $adminScopedCensusId = $this->resolveRequestedSchoolCensusId($user);
        $requestedCensusId = isset($validated['census_id']) ? trim((string) $validated['census_id']) : '';

        return $isAdmin
            ? ($requestedCensusId !== '' ? $this->resolveCanonicalSchoolCensusId($requestedCensusId) : $adminScopedCensusId)
            : $this->resolveUserCensusId($user);
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $ethnicMap
     * @param  array<string, int>  $religionMap
     * @return array<string, mixed>
     */
    private function buildImportRow(array $row, array $ethnicMap, array $religionMap): array
    {
        $data = [];

        foreach ([
            'index_no',
            'full_name',
            'name_with_initials',
            'address1',
            'address2',
            'phone_no',
            'whatsapp_no',
            'phone_home',
            'email',
            'year',
            'grade_id',
            'class_id',
            'father_name',
            'father_job',
            'father_mobile',
            'mother_name',
            'mother_job',
            'mother_mobile',
            'guardian_name',
            'guardian_job',
            'guardian_mobile',
        ] as $field) {
            $value = $this->importText($row, self::IMPORT_COLUMNS[$field] ?? null);
            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        $dob = $this->importDate($row, self::IMPORT_COLUMNS['dob'] ?? null);
        if ($dob !== null) {
            $data['dob'] = $dob;
        }

        $admissionDate = $this->importDate($row, self::IMPORT_COLUMNS['d_o_admission'] ?? null);
        if ($admissionDate !== null) {
            $data['d_o_admission'] = $admissionDate;
        }

        $genderText = $this->importText($row, self::IMPORT_COLUMNS['gender_id'] ?? null);
        if ($genderText !== '') {
            $data['gender_text'] = $genderText;
            $genderId = $this->mapGender($genderText);
            if ($genderId !== null) {
                $data['gender_id'] = $genderId;
            }
        }

        $ethnicText = $this->importText($row, self::IMPORT_COLUMNS['ethnic_group_id'] ?? null);
        if ($ethnicText !== '') {
            $data['ethnic_group_text'] = $ethnicText;
            $ethnicId = $this->resolveLookupId('ethnic_group_id', $ethnicText, $ethnicMap);
            if ($ethnicId !== null) {
                $data['ethnic_group_id'] = $ethnicId;
            }
        }

        $religionText = $this->importText($row, self::IMPORT_COLUMNS['religion_id'] ?? null);
        if ($religionText !== '') {
            $data['religion_text'] = $religionText;
            $religionId = $this->resolveLookupId('religion_id', $religionText, $religionMap);
            if ($religionId !== null) {
                $data['religion_id'] = $religionId;
            }
        }

        foreach (['year', 'grade_id', 'class_id'] as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    private function validateImportRow(array $rowData): array
    {
        $validator = Validator::make($rowData, StudentStoreRequest::ruleSet(), StudentStoreRequest::messageSet());
        StudentStoreRequest::addGradeClassCheck($validator, $rowData);
        $validator->after(function ($validator) use ($rowData): void {
            if (($rowData['gender_text'] ?? '') !== '' && !isset($rowData['gender_id'])) {
                $validator->errors()->add('gender_id', __('messages.students.validation.gender_required'));
            }

            if (($rowData['ethnic_group_text'] ?? '') !== '' && !isset($rowData['ethnic_group_id'])) {
                $validator->errors()->add('ethnic_group_id', __('messages.students.validation.ethnic_group_invalid'));
            }

            if (($rowData['religion_text'] ?? '') !== '' && !isset($rowData['religion_id'])) {
                $validator->errors()->add('religion_id', __('messages.students.validation.religion_invalid'));
            }
        });

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return $validator->validated();
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isImportRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function importText(array $row, ?int $index): string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return '';
        }

        $value = $row[$index];
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, int>  $lookupMap
     */
    private function resolveLookupId(string $field, string $value, array $lookupMap): ?int
    {
        $normalized = $this->normalizeLookupValue($value);
        if ($normalized !== '' && isset($lookupMap[$normalized])) {
            return $lookupMap[$normalized];
        }

        foreach ($this->lookupAliases($field, $normalized) as $alias) {
            if (isset($lookupMap[$alias])) {
                return $lookupMap[$alias];
            }
        }

        return null;
    }

    private function importDate(array $row, ?int $index): ?string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return null;
        }

        $value = $row[$index];
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 1000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return trim((string) $value);
            }
        }

        return trim((string) $value);
    }

    private function mapGender(string $value): ?int
    {
        $normalized = $this->normalizeLookupValue($value);
        if ($normalized === '1' || $normalized === '2') {
            return (int) $normalized;
        }

        $labels = config('student_lookup_labels.gender_id', []);
        foreach ($labels as $id => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalized === $this->normalizeLookupValue($alias)) {
                    return (int) $id;
                }
            }
        }

        return null;
    }

    private function normalizeHeaderName(mixed $value): string
    {
        return $this->normalizeLookupValue($value);
    }

    /**
     * @return array<int, string>
     */
    private function lookupAliases(string $field, string $normalized): array
    {
        $labels = config("student_lookup_labels.{$field}", []);
        if (!is_array($labels)) {
            return [];
        }

        foreach ($labels as $aliases) {
            if (!is_array($aliases)) {
                continue;
            }

            $normalizedAliases = array_map(fn ($alias): string => $this->normalizeLookupValue($alias), $aliases);
            if (in_array($normalized, $normalizedAliases, true)) {
                return array_values(array_filter(
                    $normalizedAliases,
                    fn ($alias): bool => $alias !== '' && $alias !== $normalized,
                ));
            }
        }

        return [];
    }

    private function firstValidationMessage(ValidationException $e): string
    {
        return collect($e->errors())->flatten()->first() ?? __('messages.request.validation_failed');
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
