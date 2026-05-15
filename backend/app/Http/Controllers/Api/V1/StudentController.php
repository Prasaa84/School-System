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
use App\Models\Student;
use App\Models\User;
use App\Models\StudentGradeClass;
use App\Services\FeatureAccessService;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    private const STUDENTS_IN_CLASSES_META_SHEET = '__students_in_classes_meta';

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

    public function report(Request $request): JsonResponse
    {
        $data = $this->buildStudentReportRows($this->validateStudentReportFilters($request));

        return response()->json([
            'data' => $data,
        ]);
    }

    public function downloadReport(Request $request): StreamedResponse
    {
        $rows = $this->buildStudentReportRows($this->validateStudentReportFilters($request));
        $includeSchool = $this->isAdministrator($this->authUser());
        $filename = 'student-report-' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($rows, $includeSchool): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Student Report');

            $headers = [
                'No.',
                'Admission No',
                'Full Name',
                'Name With Initials',
                'Class',
                'Address 1',
                'Address 2',
                'Phone No',
                'WhatsApp No',
                'Home Phone',
                'DOB',
                'Gender',
                'Ethnic Group',
                'Religion',
                'Admission Date',
            ];

            if ($includeSchool) {
                $headers[] = 'School';
            }

            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue("{$column}1", $header);
            }

            foreach (array_values($rows) as $index => $row) {
                $rowNumber = $index + 2;
                $columnIndex = 0;
                $write = function (string $value) use ($sheet, $rowNumber, &$columnIndex): void {
                    $sheet->setCellValue(chr(65 + $columnIndex) . $rowNumber, $value);
                    $columnIndex++;
                };

                $write((string) ($index + 1));
                $write((string) ($row['index_no'] ?? ''));
                $write((string) ($row['full_name'] ?? ''));
                $write((string) ($row['name_with_initials'] ?? ''));
                $write($this->studentReportExportGradeClass((string) ($row['grade_class'] ?? '')));
                $write((string) ($row['address1'] ?? ''));
                $write((string) ($row['address2'] ?? ''));
                $write((string) ($row['phone_no'] ?? ''));
                $write((string) ($row['whatsapp_no'] ?? ''));
                $write((string) ($row['phone_home'] ?? ''));
                $write((string) ($row['dob'] ?? ''));
                $write($this->studentGenderLabel((int) ($row['gender_id'] ?? 0)));
                $write((string) ($row['ethnic_group'] ?? ''));
                $write((string) ($row['religion'] ?? ''));
                $write((string) ($row['d_o_admission'] ?? ''));
                if ($includeSchool) {
                    $write((string) ($row['school_name'] ?? ''));
                }
            }

            foreach (range('A', chr(64 + count($headers))) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function options(): JsonResponse
    {
        $user = $this->authUser();
        $isAdmin = $this->isAdministrator($user);

        $ethnicGroups = [];
        if (Schema::hasTable('ethnic_group_tbl')) {
            $ethnicLabelColumns = [
                'ethnic_group_en',
                'ethnic_group_si',
                'ethnic_group_ta',
            ];

            $query = DB::table('ethnic_group_tbl')->select(['ethnic_group_id']);
            if ($this->resolveLookupLabelColumn('ethnic_group_tbl', $ethnicLabelColumns) !== null) {
                $query->addSelect(DB::raw($this->buildLocalizedLabelSelect('ethnic_group_tbl', $ethnicLabelColumns)));
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
            $religionLabelColumns = [
                'religion_en',
                'religion_si',
                'religion_ta',
            ];

            $query = DB::table('religion_tbl')->select(['religion_id']);
            if ($this->resolveLookupLabelColumn('religion_tbl', $religionLabelColumns) !== null) {
                $query->addSelect(DB::raw($this->buildLocalizedLabelSelect('religion_tbl', $religionLabelColumns)));
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

    public function studentsInClassesRoster(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = Validator::make($request->all(), [
            'source_year' => ['required', 'integer', 'between:2000,2100'],
            'source_grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
            'source_class_id' => ['required', 'integer', 'exists:class_tbl,class_id'],
            'target_year' => ['nullable', 'integer', 'between:2000,2100'],
        ], [
            'source_year.required' => 'Source year is required.',
            'source_grade_id.required' => 'Source grade is required.',
            'source_class_id.required' => 'Source class is required.',
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $sourceYear = (int) $validated['source_year'];
        $sourceGradeId = (int) $validated['source_grade_id'];
        $sourceClassId = (int) $validated['source_class_id'];
        $targetYear = isset($validated['target_year']) ? (int) $validated['target_year'] : null;

        $sourceSchoolGradeClassId = $this->studentService->resolveSchoolGradeClassIdForAssignment(
            $sourceGradeId,
            $sourceClassId,
            $sourceYear,
            $censusId,
        );

        if ($sourceSchoolGradeClassId === null) {
            return response()->json([
                'message' => __('messages.students.grade_class_mismatch'),
                'errors' => [
                    'source_grade_id' => [__('messages.students.grade_class_mismatch')],
                    'source_class_id' => [__('messages.students.grade_class_mismatch')],
                    'source_year' => [__('messages.students.grade_class_mismatch')],
                ],
            ], 422);
        }

        $studentsQuery = DB::table('student_grade_class_tbl as sgc')
            ->join('student_tbl as st', 'sgc.std_id', '=', 'st.std_id')
            ->where('sgc.sch_grd_cls_id', $sourceSchoolGradeClassId)
            ->where('st.census_id', $censusId)
            ->where('st.is_deleted', 0)
            ->orderBy('st.index_no')
            ->orderBy('st.name_with_initials')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                'st.census_id',
            ]);

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $studentsQuery->where('sgc.is_deleted', 0);
        }

        $rows = $studentsQuery->get();
        $studentIds = $rows->pluck('std_id')
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $targetAssignments = collect();
        if ($targetYear !== null && $studentIds !== [] && Schema::hasTable('school_grade_class_tbl')) {
            $targetAssignmentsQuery = DB::table('student_grade_class_tbl as target_sgc')
                ->join('school_grade_class_tbl as target_sgct', 'target_sgc.sch_grd_cls_id', '=', 'target_sgct.sch_grd_cls_id')
                ->whereIn('target_sgc.std_id', $studentIds)
                ->where('target_sgct.year', $targetYear)
                ->whereIn('target_sgct.census_id', $this->studentService->censusCandidatesForAssignment($censusId))
                ->select([
                    'target_sgc.std_id',
                    'target_sgct.grade_id as target_grade_id',
                    'target_sgct.class_id as target_class_id',
                ])
                ->orderByDesc('target_sgc.st_gr_cl_id');

            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $targetAssignmentsQuery->where('target_sgc.is_deleted', 0);
            }
            if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                $targetAssignmentsQuery->where('target_sgct.is_deleted', 0);
            }

            $targetAssignments = $targetAssignmentsQuery->get()
                ->unique(fn ($row): int => (int) ($row->std_id ?? 0))
                ->keyBy(fn ($row): int => (int) ($row->std_id ?? 0));
        }

        return response()->json([
            'data' => $rows->map(function ($row) use ($sourceYear, $sourceGradeId, $sourceClassId, $targetAssignments): array {
                $target = $targetAssignments->get((int) ($row->std_id ?? 0));

                return [
                    'std_id' => (int) $row->std_id,
                    'index_no' => (string) ($row->index_no ?? ''),
                    'name_with_initials' => (string) ($row->name_with_initials ?? ''),
                    'census_id' => isset($row->census_id) ? (string) $row->census_id : null,
                    'source_year' => $sourceYear,
                    'source_grade_id' => $sourceGradeId,
                    'source_class_id' => $sourceClassId,
                    'target_grade_id' => isset($target?->target_grade_id) ? (int) $target->target_grade_id : 0,
                    'target_class_id' => isset($target?->target_class_id) ? (int) $target->target_class_id : 0,
                ];
            })->all(),
        ]);
    }

    public function saveStudentsInClasses(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = Validator::make($request->all(), [
            'target_year' => ['required', 'integer', 'between:2000,2100'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.std_id' => ['required', 'integer'],
            'assignments.*.grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
            'assignments.*.class_id' => ['required', 'integer', 'exists:class_tbl,class_id'],
        ], [
            'target_year.required' => 'Target year is required.',
            'assignments.required' => 'At least one student assignment is required.',
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $targetYear = (int) $validated['target_year'];
        $assignmentRows = is_array($validated['assignments']) ? $validated['assignments'] : [];

        try {
            DB::transaction(function () use ($assignmentRows, $targetYear, $censusId): void {
                foreach ($assignmentRows as $row) {
                    $studentId = (int) ($row['std_id'] ?? 0);
                    $gradeId = (int) ($row['grade_id'] ?? 0);
                    $classId = (int) ($row['class_id'] ?? 0);

                    $student = Student::query()
                        ->where('std_id', $studentId)
                        ->where('census_id', $censusId)
                        ->where('is_deleted', 0)
                        ->first();

                    if ($student === null) {
                        throw ValidationException::withMessages([
                            'assignments' => ["Student {$studentId} was not found for the selected school."],
                        ]);
                    }

                    $schoolGradeClassId = $this->studentService->resolveSchoolGradeClassIdForAssignment(
                        $gradeId,
                        $classId,
                        $targetYear,
                        $censusId,
                    );

                    if ($schoolGradeClassId === null) {
                        throw ValidationException::withMessages([
                            'assignments' => [__('messages.students.grade_class_mismatch')],
                        ]);
                    }

                    $this->studentService->upsertStudentAssignmentForYear($studentId, $schoolGradeClassId, $targetYear);
                }
            });

            return response()->json([
                'message' => 'Student class assignments saved successfully.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => count($e->errors()) > 0 ? collect($e->errors())->flatten()->first() : __('messages.request.validation_failed'),
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Student bulk assignment failed.', [
                'target_year' => $targetYear,
                'census_id' => $censusId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to save student class assignments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function studentsInClassesOverview(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $year = (int) $validated['year'];
        $gradeId = (int) $validated['grade_id'];

        return response()->json([
            'class_boxes' => $this->loadStudentsInClassesBoxes($censusId, $year, $gradeId),
        ]);
    }

    public function uploadStudentsInClass(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
            'class_id' => ['required', 'integer', 'exists:class_tbl,class_id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ], [
            'file.required' => 'Please choose a class list file.',
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $year = (int) $validated['year'];
        $gradeId = (int) $validated['grade_id'];
        $classId = (int) $validated['class_id'];

        Log::info('Students in classes upload requested.', [
            'user_id' => $user->id ?? null,
            'census_id' => $censusId,
            'year' => $year,
            'grade_id' => $gradeId,
            'class_id' => $classId,
            'original_file_name' => $request->file('file')?->getClientOriginalName(),
        ]);

        $schoolGradeClassId = $this->studentService->resolveSchoolGradeClassIdForAssignment($gradeId, $classId, $year, $censusId);
        if ($schoolGradeClassId === null) {
            Log::warning('Students in classes upload failed: grade/class/year mismatch.', [
                'user_id' => $user->id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
            ]);

            return response()->json([
                'message' => __('messages.students.grade_class_mismatch'),
                'errors' => [
                    'grade_id' => [__('messages.students.grade_class_mismatch')],
                    'class_id' => [__('messages.students.grade_class_mismatch')],
                    'year' => [__('messages.students.grade_class_mismatch')],
                ],
            ], 422);
        }

        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]) ?? 'class';

        $classLabel = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId)
            ->value(DB::raw("ct.{$classLabelColumn} as class"));

        $safeClass = preg_replace('/[^A-Za-z0-9]+/', '', trim((string) $classLabel)) ?: 'Class';
        $expectedBaseName = sprintf('Grade_%d%s_%d', $gradeId, $safeClass, $year);
        $uploadedBaseName = pathinfo((string) $request->file('file')?->getClientOriginalName(), PATHINFO_FILENAME);

        if (strcasecmp($uploadedBaseName, $expectedBaseName) !== 0) {
            Log::warning('Students in classes upload failed: invalid file name.', [
                'user_id' => $user->id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'expected_file_name' => "{$expectedBaseName}.xlsx",
                'uploaded_file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            return response()->json([
                'message' => "Invalid file name. Expected {$expectedBaseName}.xlsx for the selected class.",
                'errors' => [
                    'file' => ["Invalid file name. Expected {$expectedBaseName}.xlsx for the selected class."],
                ],
            ], 422);
        }

        $workbookMetadata = $this->extractStudentsInClassesWorkbookMetadata($request->file('file'));
        if ($workbookMetadata !== null) {
            $metadataSchoolGradeClassId = isset($workbookMetadata['school_grade_class_id']) ? (int) $workbookMetadata['school_grade_class_id'] : 0;
            $metadataYear = isset($workbookMetadata['year']) ? (int) $workbookMetadata['year'] : 0;
            $metadataGradeId = isset($workbookMetadata['grade_id']) ? (int) $workbookMetadata['grade_id'] : 0;
            $metadataClassId = isset($workbookMetadata['class_id']) ? (int) $workbookMetadata['class_id'] : 0;

            if (
                $metadataSchoolGradeClassId !== $schoolGradeClassId
                || $metadataYear !== $year
                || $metadataGradeId !== $gradeId
                || $metadataClassId !== $classId
            ) {
                Log::warning('Students in classes upload failed: workbook metadata mismatch.', [
                    'user_id' => $user->id ?? null,
                    'census_id' => $censusId,
                    'year' => $year,
                    'grade_id' => $gradeId,
                    'class_id' => $classId,
                    'school_grade_class_id' => $schoolGradeClassId,
                    'metadata' => $workbookMetadata,
                    'uploaded_file_name' => $request->file('file')?->getClientOriginalName(),
                ]);

                return response()->json([
                    'message' => 'This file belongs to a different class. Please upload the original file for the selected class.',
                    'errors' => [
                        'file' => ['This file belongs to a different class. Please upload the original file for the selected class.'],
                    ],
                ], 422);
            }
        }

        try {
            $sheets = Excel::toArray([], $request->file('file'));
        } catch (Throwable $e) {
            Log::warning('Students in classes upload failed: invalid spreadsheet file.', [
                'user_id' => $user->id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'original_file_name' => $request->file('file')?->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('messages.students.import_file_invalid'),
            ], 422);
        }

        $sheet = $sheets[0] ?? [];
        if (count($sheet) === 0) {
            Log::warning('Students in classes upload failed: empty sheet.', [
                'user_id' => $user->id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'original_file_name' => $request->file('file')?->getClientOriginalName(),
            ]);

            return response()->json([
                'message' => __('messages.students.import_empty'),
            ], 422);
        }

        $indexRows = $this->extractStudentsInClassesUploadRows($sheet);
        if ($indexRows === []) {
            Log::warning('Students in classes upload failed: no valid index numbers found.', [
                'user_id' => $user->id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'original_file_name' => $request->file('file')?->getClientOriginalName(),
                'sheet_row_count' => count($sheet),
            ]);

            return response()->json([
                'message' => 'No valid index numbers were found in the uploaded file.',
            ], 422);
        }

        $uniqueIndexRows = [];
        $duplicateIndexes = [];
        foreach ($indexRows as $rowInfo) {
            $indexNo = $rowInfo['index_no'];
            if (isset($uniqueIndexRows[$indexNo])) {
                $duplicateIndexes[] = $rowInfo;
                continue;
            }
            $uniqueIndexRows[$indexNo] = $rowInfo;
        }

        Log::info('Students in classes upload parsed spreadsheet.', [
            'user_id' => $user->id ?? null,
            'census_id' => $censusId,
            'year' => $year,
            'grade_id' => $gradeId,
            'class_id' => $classId,
            'original_file_name' => $request->file('file')?->getClientOriginalName(),
            'sheet_row_count' => count($sheet),
            'parsed_index_row_count' => count($indexRows),
            'unique_index_count' => count($uniqueIndexRows),
            'duplicate_index_count' => count($duplicateIndexes),
        ]);

        $studentRows = Student::query()
            ->whereIn('index_no', array_keys($uniqueIndexRows))
            ->where('census_id', $censusId)
            ->where('is_deleted', 0)
            ->get(['std_id', 'index_no'])
            ->keyBy(fn (Student $student): string => (string) $student->index_no);

        $missingIndexes = [];
        foreach ($uniqueIndexRows as $indexNo => $rowInfo) {
            if (!$studentRows->has($indexNo)) {
                $missingIndexes[] = $rowInfo;
            }
        }

        $conflictingAssignments = [];
        if ($studentRows->isNotEmpty()) {
            $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
                'class_en',
                'class_si',
                'class_ta',
                'class',
            ]) ?? 'class';

            $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
                'grade_en',
                'grade_si',
                'grade_ta',
            ]);

            $currentAssignments = DB::table('student_grade_class_tbl as sgc')
                ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
                ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
                ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
                ->whereIn('sgc.std_id', $studentRows->pluck('std_id')->map(fn ($value): int => (int) $value)->all())
                ->where('sgct.year', $year)
                ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), fn ($query) => $query->where('sgc.is_deleted', 0))
                ->when(Schema::hasColumn('school_grade_class_tbl', 'is_deleted'), fn ($query) => $query->where('sgct.is_deleted', 0))
                ->select([
                    'sgc.std_id',
                    'sgct.sch_grd_cls_id',
                    'sgct.grade_id',
                    'sgct.class_id',
                ])
                ->when($gradeLabelColumn !== null, fn ($query) => $query->addSelect(DB::raw("gt.{$gradeLabelColumn} as grade_label")))
                ->addSelect(DB::raw("ct.{$classLabelColumn} as class_label"))
                ->orderByDesc('sgc.st_gr_cl_id')
                ->get()
                ->unique(fn ($row): int => (int) ($row->std_id ?? 0))
                ->keyBy(fn ($row): int => (int) ($row->std_id ?? 0));

            foreach ($studentRows as $indexNo => $student) {
                $currentAssignment = $currentAssignments->get((int) $student->std_id);
                if ($currentAssignment === null) {
                    continue;
                }

                $assignedSchoolGradeClassId = (int) ($currentAssignment->sch_grd_cls_id ?? 0);
                if ($assignedSchoolGradeClassId === $schoolGradeClassId) {
                    continue;
                }

                $conflictingAssignments[] = [
                    'row' => $uniqueIndexRows[(string) $indexNo]['row'] ?? 0,
                    'index_no' => (string) $indexNo,
                    'current_grade' => trim((string) ($currentAssignment->grade_label ?? '')),
                    'current_class' => trim((string) ($currentAssignment->class_label ?? '')),
                ];
            }
        }

        if ($duplicateIndexes !== [] || $missingIndexes !== [] || $conflictingAssignments !== []) {
            Log::warning('Students in classes upload rejected during validation.', [
                'user_id' => $user->id ?? null,
                'census_id' => $censusId,
                'year' => $year,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'duplicate_index_count' => count($duplicateIndexes),
                'missing_index_count' => count($missingIndexes),
                'conflicting_assignment_count' => count($conflictingAssignments),
            ]);

            $message = 'Fix the upload file before importing.';
            if ($conflictingAssignments !== []) {
                $firstConflict = $conflictingAssignments[0];
                $currentLocation = trim(($firstConflict['current_grade'] !== '' ? $firstConflict['current_grade'] . ' ' : '') . ($firstConflict['current_class'] ?? ''));
                $message = $currentLocation !== ''
                    ? "Student {$firstConflict['index_no']} is already assigned to {$currentLocation} for {$year}. Remove the student from that class first."
                    : "Student {$firstConflict['index_no']} is already assigned to another class for {$year}. Remove the student from that class first.";
            } elseif ($missingIndexes !== []) {
                $message = "Student {$missingIndexes[0]['index_no']} was not found for the selected school.";
            } elseif ($duplicateIndexes !== []) {
                $message = "Duplicate index number found in upload: {$duplicateIndexes[0]['index_no']}.";
            }

            return response()->json([
                'message' => $message,
                'data' => [
                    'cleared_count' => 0,
                    'successful_count' => 0,
                    'failed_count' => count($missingIndexes) + count($duplicateIndexes) + count($conflictingAssignments),
                    'missing_indexes' => array_values($missingIndexes),
                    'duplicate_indexes' => array_values($duplicateIndexes),
                    'conflicting_assignments' => array_values($conflictingAssignments),
                ],
            ], 422);
        }

        $successfulCount = 0;
        $clearedCount = 0;

        DB::transaction(function () use (
            $uniqueIndexRows,
            $studentRows,
            $year,
            $schoolGradeClassId,
            &$successfulCount,
            &$clearedCount
        ): void {
            $clearedCount = $this->studentService->hardDeleteAssignmentsForSchoolGradeClass($schoolGradeClassId);

            foreach ($uniqueIndexRows as $indexNo => $rowInfo) {
                $student = $studentRows->get($indexNo);
                if (!$student instanceof Student) {
                    continue;
                }

                $this->studentService->replaceStudentAssignmentForYearHardDelete((int) $student->std_id, $schoolGradeClassId, $year);
                $successfulCount++;
            }
        });

        Log::info('Students in classes upload completed.', [
            'user_id' => $user->id ?? null,
            'census_id' => $censusId,
            'year' => $year,
            'grade_id' => $gradeId,
            'class_id' => $classId,
            'school_grade_class_id' => $schoolGradeClassId,
            'cleared_count' => $clearedCount,
            'successful_count' => $successfulCount,
            'missing_index_count' => 0,
            'duplicate_index_count' => 0,
            'conflicting_assignment_count' => 0,
        ]);

        return response()->json([
            'message' => 'Class list uploaded successfully.',
            'data' => [
                'cleared_count' => $clearedCount,
                'successful_count' => $successfulCount,
                'failed_count' => 0,
                'missing_indexes' => [],
                'duplicate_indexes' => [],
                'conflicting_assignments' => [],
            ],
        ]);
    }

    public function clearStudentsInClass(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
            'class_id' => ['required', 'integer', 'exists:class_tbl,class_id'],
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $schoolGradeClassId = $this->studentService->resolveSchoolGradeClassIdForAssignment(
            (int) $validated['grade_id'],
            (int) $validated['class_id'],
            (int) $validated['year'],
            $censusId,
        );
        if ($schoolGradeClassId === null) {
            return response()->json([
                'message' => __('messages.students.grade_class_mismatch'),
            ], 422);
        }

        $deletedCount = $this->studentService->hardDeleteAssignmentsForSchoolGradeClass($schoolGradeClassId);

        return response()->json([
            'message' => 'Class list cleared successfully.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ]);
    }

    public function removeStudentFromClass(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        if (!$this->isPrincipal($user)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
            'class_id' => ['required', 'integer', 'exists:class_tbl,class_id'],
            'student_id' => ['required', 'integer'],
        ], [
            'student_id.required' => 'Student is required.',
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $year = (int) $validated['year'];
        $gradeId = (int) $validated['grade_id'];
        $classId = (int) $validated['class_id'];
        $studentId = (int) $validated['student_id'];

        $schoolGradeClassId = $this->studentService->resolveSchoolGradeClassIdForAssignment(
            $gradeId,
            $classId,
            $year,
            $censusId,
        );

        if ($schoolGradeClassId === null) {
            return response()->json([
                'message' => __('messages.students.grade_class_mismatch'),
            ], 422);
        }

        $studentExists = Student::query()
            ->where('std_id', $studentId)
            ->where('census_id', $censusId)
            ->where('is_deleted', 0)
            ->exists();

        if (!$studentExists) {
            return response()->json([
                'message' => __('messages.students.not_found'),
            ], 404);
        }

        $deletedCount = $this->studentService->hardDeleteStudentAssignmentForSchoolGradeClass($studentId, $schoolGradeClassId);

        if ($deletedCount === 0) {
            return response()->json([
                'message' => 'Student is not assigned to the selected class.',
            ], 422);
        }

        return response()->json([
            'message' => 'Student removed from class successfully.',
            'data' => [
                'deleted_count' => $deletedCount,
            ],
        ]);
    }

    public function downloadStudentsInClass(Request $request): JsonResponse|StreamedResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'exists:grade_tbl,grade_id'],
            'class_id' => ['required', 'integer', 'exists:class_tbl,class_id'],
        ])->validate();

        $censusId = $this->resolveStudentWriteCensusId($user);
        if ($censusId === null) {
            return response()->json(['message' => __('messages.students.census_required')], 422);
        }

        if (
            !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_UPDATE)
            && !$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)
        ) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $year = (int) $validated['year'];
        $gradeId = (int) $validated['grade_id'];
        $classId = (int) $validated['class_id'];

        $schoolGradeClassId = $this->studentService->resolveSchoolGradeClassIdForAssignment(
            $gradeId,
            $classId,
            $year,
            $censusId,
        );

        if ($schoolGradeClassId === null) {
            return response()->json([
                'message' => __('messages.students.grade_class_mismatch'),
            ], 422);
        }

        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]) ?? 'class';

        $classLabel = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId)
            ->value(DB::raw("ct.{$classLabelColumn}"));

        $students = DB::table('student_grade_class_tbl as sgc')
            ->join('student_tbl as st', 'sgc.std_id', '=', 'st.std_id')
            ->where('sgc.sch_grd_cls_id', $schoolGradeClassId)
            ->where('st.is_deleted', 0)
            ->when(
                Schema::hasColumn('student_grade_class_tbl', 'is_deleted'),
                fn ($query) => $query->where('sgc.is_deleted', 0)
            )
            ->orderBy('st.index_no')
            ->orderBy('st.name_with_initials')
            ->get([
                'st.index_no',
                'st.name_with_initials',
            ]);

        $templatePath = storage_path('app/templates/students-class-template.xlsx');
        if (!is_file($templatePath)) {
            Log::warning('Student class download failed: template missing.', [
                'path' => $templatePath,
            ]);

            return response()->json([
                'message' => __('messages.students.template_missing'),
            ], 404);
        }

        $safeClass = preg_replace('/[^A-Za-z0-9]+/', '', trim((string) $classLabel)) ?: 'Class';
        $filename = sprintf('Grade_%d%s_%d.xlsx', $gradeId, $safeClass, $year);

        return response()->streamDownload(function () use ($students, $templatePath, $year, $gradeId, $classId, $schoolGradeClassId, $classLabel): void {
            $spreadsheet = IOFactory::load($templatePath);
            $sheet = $spreadsheet->getActiveSheet();
            $highestDataRow = max(1, (int) $sheet->getHighestDataRow());

            if ($highestDataRow > 1) {
                $sheet->removeRow(2, $highestDataRow - 1);
            }

            foreach ($students->values() as $index => $student) {
                $rowNumber = $index + 2;
                $sheet->setCellValue("A{$rowNumber}", $index + 1);
                $sheet->setCellValue("B{$rowNumber}", (string) ($student->index_no ?? ''));
                $sheet->setCellValue("C{$rowNumber}", (string) ($student->name_with_initials ?? ''));
            }

            $this->stampStudentsInClassesWorkbookMetadata($spreadsheet, [
                'year' => (string) $year,
                'grade_id' => (string) $gradeId,
                'class_id' => (string) $classId,
                'school_grade_class_id' => (string) $schoolGradeClassId,
                'class_name' => trim((string) $classLabel),
            ]);

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
                'message' => $isAdmin
                    ? __('messages.students.school_required')
                    : __('messages.students.census_required'),
            ], 422);
        }

        if (!$this->featureAccess->hasFeature($user, $censusId, FeatureAccessService::STUDENT_CREATE)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        try {
            $saved = $this->studentService->createStudent($validated, $censusId);
            $this->storeStudentPhoto($request->file('profile_photo'), (int) $saved['std_id']);

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
            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? __('messages.students.school_required')
                    : __('messages.students.census_required'),
            ], 422);
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

    public function downloadStudentsInClassesTemplate(): JsonResponse|BinaryFileResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => __('messages.auth.unauthorized')], 401);
        }

        $path = storage_path('app/templates/students-class-template.xlsx');
        if (!is_file($path)) {
            Log::warning('Student class template download failed: file missing.', [
                'path' => $path,
            ]);

            return response()->json([
                'message' => __('messages.students.template_missing'),
            ], 404);
        }

        Log::info('Student class template download requested.', [
            'path' => $path,
        ]);

        return response()->download($path, 'students-class-template.xlsx');
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
                'full_name' => (string) ($student->fullname ?? $student->full_name ?? ''),
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
                'photo_url' => $this->resolveStudentPhotoUrl((int) $student->std_id),
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
            $this->storeStudentPhoto($request->file('profile_photo'), $studentId);

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
    private function resolveImportCensusId(User $user, array $validated): ?string
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

    /**
     * @return array<int, array{sch_grd_cls_id:int,class_id:int,class:string,student_count:int,students:array<int, array{std_id:int,index_no:string,name_with_initials:string}>}>
     */
    private function loadStudentsInClassesBoxes(string $censusId, int $year, int $gradeId): array
    {
        if (!Schema::hasTable('school_grade_class_tbl') || !Schema::hasTable('class_tbl')) {
            return [];
        }

        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]) ?? 'class';

        $classQuery = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.class_id',
                DB::raw("ct.{$classLabelColumn} as class"),
            ])
            ->where('sgct.year', $year)
            ->where('sgct.grade_id', $gradeId)
            ->whereIn('sgct.census_id', $this->studentService->censusCandidatesForAssignment($censusId))
            ->orderBy('sgct.class_id');

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $classQuery->where('sgct.is_deleted', 0);
        }

        $classes = $classQuery->get();
        $classIds = $classes->pluck('sch_grd_cls_id')
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        $studentsByClass = collect();
        if ($classIds !== [] && Schema::hasTable('student_grade_class_tbl')) {
            $studentQuery = DB::table('student_grade_class_tbl as sgc')
                ->join('student_tbl as st', 'sgc.std_id', '=', 'st.std_id')
                ->whereIn('sgc.sch_grd_cls_id', $classIds)
                ->where('st.is_deleted', 0)
                ->select([
                    'sgc.sch_grd_cls_id',
                    'st.std_id',
                    'st.index_no',
                    'st.name_with_initials',
                ])
                ->orderBy('st.index_no')
                ->orderBy('st.name_with_initials');

            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $studentQuery->where('sgc.is_deleted', 0);
            }

            $studentsByClass = $studentQuery->get()
                ->groupBy(fn ($row): int => (int) ($row->sch_grd_cls_id ?? 0));
        }

        return $classes->map(function ($row) use ($studentsByClass): array {
            $students = $studentsByClass->get((int) ($row->sch_grd_cls_id ?? 0), collect())
                ->map(fn ($student): array => [
                    'std_id' => (int) ($student->std_id ?? 0),
                    'index_no' => (string) ($student->index_no ?? ''),
                    'name_with_initials' => (string) ($student->name_with_initials ?? ''),
                ])
                ->values()
                ->all();

            return [
                'sch_grd_cls_id' => (int) ($row->sch_grd_cls_id ?? 0),
                'class_id' => (int) ($row->class_id ?? 0),
                'class' => (string) ($row->class ?? ''),
                'student_count' => count($students),
                'students' => $students,
            ];
        })->all();
    }

    /**
     * @param  array<int, array<int, mixed>>  $sheet
     * @return array<int, array{row:int,index_no:string}>
     */
    private function extractStudentsInClassesUploadRows(array $sheet): array
    {
        $rows = [];

        foreach ($sheet as $rowIndex => $row) {
            if (!is_array($row) || $this->isImportRowEmpty($row)) {
                continue;
            }

            $indexNo = $this->importText($row, 1);
            if ($indexNo === '') {
                $indexNo = $this->importText($row, 0);
            }

            if ($rowIndex === 0 && !preg_match('/^\d+$/', $indexNo)) {
                continue;
            }

            if ($indexNo === '' || !preg_match('/^\d+$/', $indexNo)) {
                continue;
            }

            $rows[] = [
                'row' => $rowIndex + 1,
                'index_no' => $indexNo,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function stampStudentsInClassesWorkbookMetadata(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, array $metadata): void
    {
        $metaSheet = $spreadsheet->getSheetByName(self::STUDENTS_IN_CLASSES_META_SHEET);
        if ($metaSheet === null) {
            $metaSheet = $spreadsheet->createSheet();
            $metaSheet->setTitle(self::STUDENTS_IN_CLASSES_META_SHEET);
        } else {
            $highestRow = max(1, (int) $metaSheet->getHighestRow());
            if ($highestRow > 0) {
                $metaSheet->removeRow(1, $highestRow);
            }
        }

        $row = 1;
        foreach ($metadata as $key => $value) {
            $metaSheet->setCellValue("A{$row}", $key);
            $metaSheet->setCellValue("B{$row}", $value);
            $row++;
        }

        $metaSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * @return array<string, string>|null
     */
    private function extractStudentsInClassesWorkbookMetadata(?\Illuminate\Http\UploadedFile $file): ?array
    {
        if ($file === null) {
            return null;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            return null;
        }

        try {
            $spreadsheet = IOFactory::load((string) $file->getRealPath());
            $metaSheet = $spreadsheet->getSheetByName(self::STUDENTS_IN_CLASSES_META_SHEET);
            if ($metaSheet === null) {
                $spreadsheet->disconnectWorksheets();
                return null;
            }

            $highestRow = max(1, (int) $metaSheet->getHighestRow());
            $metadata = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $key = trim((string) $metaSheet->getCell("A{$row}")->getValue());
                if ($key === '') {
                    continue;
                }

                $metadata[$key] = trim((string) $metaSheet->getCell("B{$row}")->getValue());
            }

            $spreadsheet->disconnectWorksheets();
            return $metadata === [] ? null : $metadata;
        } catch (Throwable $e) {
            Log::warning('Students in classes upload metadata read failed.', [
                'original_file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function loadStudentForWrite(int $studentId, ?User $user): ?Student
    {
        $query = DB::table('student_tbl as st')
            ->select('st.std_id')
            ->where('st.std_id', $studentId)
            ->where('st.is_deleted', 0);

        $this->applySchoolScope($query, $user, 'st', 'census_id');

        $resolvedStudentId = $query->value('st.std_id');
        if (!is_numeric($resolvedStudentId)) {
            return null;
        }

        return Student::query()
            ->where('std_id', (int) $resolvedStudentId)
            ->where('is_deleted', 0)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateStudentReportFilters(Request $request): array
    {
        return Validator::make($request->all(), [
            'q' => ['nullable', 'string', 'max:255'],
            'school_census_id' => ['nullable', 'string', 'regex:/^[0-9]{4,7}$/'],
            'gender_id' => ['nullable', 'integer', 'in:1,2'],
            'ethnic_group_id' => ['nullable', 'integer'],
            'religion_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'grade_id' => ['nullable', 'integer', 'exists:grade_tbl,grade_id'],
            'class_id' => ['nullable', 'integer', 'exists:class_tbl,class_id'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentReportRows(array $validated): array
    {
        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';
        $schoolCensusId = isset($validated['school_census_id']) ? trim((string) $validated['school_census_id']) : '';
        $genderId = isset($validated['gender_id']) ? (int) $validated['gender_id'] : 0;
        $ethnicGroupId = isset($validated['ethnic_group_id']) ? (int) $validated['ethnic_group_id'] : 0;
        $religionId = isset($validated['religion_id']) ? (int) $validated['religion_id'] : 0;
        $year = isset($validated['year']) ? (int) $validated['year'] : 0;
        $gradeId = isset($validated['grade_id']) ? (int) $validated['grade_id'] : 0;
        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : 0;
        $hasSchoolTable = Schema::hasTable('school_details_tbl');

        $studentsQuery = DB::table('student_tbl as st')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                DB::raw('st.fullname as full_name'),
                'st.address1',
                'st.address2',
                'st.phone_no',
                'st.whatsapp_no',
                'st.phone_home',
                'st.dob',
                'st.d_o_admission',
                'st.census_id',
                'st.gender_id',
                'st.ethnic_group_id',
                'st.religion_id',
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
            ->when($schoolCensusId !== '', function ($builder) use ($schoolCensusId): void {
                $builder->where('st.census_id', $schoolCensusId);
            })
            ->when($genderId > 0, fn ($builder) => $builder->where('st.gender_id', $genderId))
            ->when($ethnicGroupId > 0, fn ($builder) => $builder->where('st.ethnic_group_id', $ethnicGroupId))
            ->when($religionId > 0, fn ($builder) => $builder->where('st.religion_id', $religionId))
            ->orderBy('st.census_id')
            ->orderBy('st.index_no');

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

        if (Schema::hasTable('ethnic_group_tbl')) {
            $ethnicLabelColumn = $this->resolveLookupLabelColumn('ethnic_group_tbl', [
                'ethnic_group_en',
                'ethnic_group_si',
                'ethnic_group_ta',
            ]);

            $studentsQuery->leftJoin('ethnic_group_tbl as eg', 'st.ethnic_group_id', '=', 'eg.ethnic_group_id');
            if ($ethnicLabelColumn !== null) {
                $studentsQuery->addSelect(DB::raw("eg.{$ethnicLabelColumn} as ethnic_group"));
            }
        }

        if (Schema::hasTable('religion_tbl')) {
            $religionLabelColumn = $this->resolveLookupLabelColumn('religion_tbl', [
                'religion_en',
                'religion_si',
                'religion_ta',
            ]);

            $studentsQuery->leftJoin('religion_tbl as rt', 'st.religion_id', '=', 'rt.religion_id');
            if ($religionLabelColumn !== null) {
                $studentsQuery->addSelect(DB::raw("rt.{$religionLabelColumn} as religion"));
            }
        }

        $this->applySchoolScope($studentsQuery, $this->authUser(), 'st', 'census_id');

        $students = $studentsQuery->get();
        $studentIds = $students->pluck('std_id')
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
                    'sgct.grade_id',
                    'sgct.class_id',
                ])
                ->orderByDesc('sgct.year')
                ->orderByDesc('sgc.st_gr_cl_id');

            if ($gradeLabelColumn !== null) {
                $gradeClassQuery->addSelect(DB::raw("gt.{$gradeLabelColumn} as grade"));
            }

            if ($classLabelColumn !== null) {
                $gradeClassQuery->addSelect(DB::raw("ct.{$classLabelColumn} as class"));
            }

            $currentGradeClasses = $gradeClassQuery->get()
                ->unique(fn ($row): int => (int) ($row->std_id ?? 0))
                ->keyBy(fn ($row): int => (int) ($row->std_id ?? 0));
        }

        return $students
            ->map(function ($student) use ($currentGradeClasses): array {
                $gradeClass = $currentGradeClasses->get((int) ($student->std_id ?? 0));
                $grade = trim((string) ($gradeClass->grade ?? ''));
                $className = trim((string) ($gradeClass->class ?? ''));
                $gradeClassLabel = trim("{$grade} {$className}") !== '' ? trim("{$grade} {$className}") : 'N/A';
                $currentYear = isset($gradeClass->year) ? (int) $gradeClass->year : null;

                return [
                    'std_id' => (int) ($student->std_id ?? 0),
                    'index_no' => (string) ($student->index_no ?? ''),
                    'name_with_initials' => (string) ($student->name_with_initials ?? ''),
                    'full_name' => (string) ($student->full_name ?? ''),
                    'address1' => (string) ($student->address1 ?? ''),
                    'address2' => (string) ($student->address2 ?? ''),
                    'census_id' => isset($student->census_id) ? (string) $student->census_id : null,
                    'school_name' => $student->school_name ?? null,
                    'phone_no' => $student->phone_no,
                    'whatsapp_no' => $student->whatsapp_no,
                    'phone_home' => $student->phone_home,
                    'dob' => $student->dob,
                    'd_o_admission' => $student->d_o_admission,
                    'gender_id' => isset($student->gender_id) ? (int) $student->gender_id : 0,
                    'ethnic_group' => (string) ($student->ethnic_group ?? ''),
                    'religion' => (string) ($student->religion ?? ''),
                    'grade_class' => $gradeClassLabel,
                    'class_label' => $className,
                    'current_year' => $currentYear,
                    'grade_id' => isset($gradeClass->grade_id) ? (int) $gradeClass->grade_id : 0,
                    'class_id' => isset($gradeClass->class_id) ? (int) $gradeClass->class_id : 0,
                ];
            })
            ->filter(function (array $student) use ($year, $gradeId, $classId): bool {
                if ($year > 0 && (int) ($student['current_year'] ?? 0) !== $year) {
                    return false;
                }

                if ($gradeId > 0 && (int) ($student['grade_id'] ?? 0) !== $gradeId) {
                    return false;
                }

                if ($classId > 0 && (int) ($student['class_id'] ?? 0) !== $classId) {
                    return false;
                }

                return true;
            })
            ->sort(function (array $left, array $right): int {
                $schoolCompare = strcmp((string) ($left['census_id'] ?? ''), (string) ($right['census_id'] ?? ''));
                if ($schoolCompare !== 0) {
                    return $schoolCompare;
                }

                $gradeCompare = ((int) ($left['grade_id'] ?? 0)) <=> ((int) ($right['grade_id'] ?? 0));
                if ($gradeCompare !== 0) {
                    return $gradeCompare;
                }

                $classCompare = strnatcasecmp(
                    (string) ($left['class_label'] ?? ''),
                    (string) ($right['class_label'] ?? ''),
                );
                if ($classCompare !== 0) {
                    return $classCompare;
                }

                $leftAdmission = trim((string) ($left['index_no'] ?? ''));
                $rightAdmission = trim((string) ($right['index_no'] ?? ''));

                if (ctype_digit($leftAdmission) && ctype_digit($rightAdmission)) {
                    return ((int) $leftAdmission) <=> ((int) $rightAdmission);
                }

                return strnatcasecmp($leftAdmission, $rightAdmission);
            })
            ->map(function (array $student): array {
                unset($student['grade_id'], $student['class_id'], $student['class_label']);
                return $student;
            })
            ->values()
            ->all();
    }

    private function studentGenderLabel(int $genderId): string
    {
        return match ($genderId) {
            1 => 'Male',
            2 => 'Female',
            default => '',
        };
    }

    private function storeStudentPhoto(mixed $photo, int $studentId): void
    {
        if ($studentId <= 0 || !$photo instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $targetDir = public_path('uploads/students');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $extension = strtolower((string) $photo->getClientOriginalExtension());
        if ($extension === '') {
            $extension = 'jpg';
        }

        foreach (glob($targetDir . DIRECTORY_SEPARATOR . $studentId . '.*') ?: [] as $existingFile) {
            if (is_file($existingFile)) {
                @unlink($existingFile);
            }
        }

        $photo->move($targetDir, $studentId . '.' . $extension);
    }

    private function resolveStudentPhotoUrl(int $studentId): string
    {
        if ($studentId <= 0) {
            return '';
        }

        $baseDir = public_path('uploads/students');
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $path = $baseDir . DIRECTORY_SEPARATOR . $studentId . '.' . $extension;
            if (is_file($path)) {
                return asset('uploads/students/' . $studentId . '.' . $extension);
            }
        }

        return '';
    }

    private function studentReportExportGradeClass(string $gradeClass): string
    {
        $value = preg_replace('/^Grade\s+/i', '', trim($gradeClass)) ?? trim($gradeClass);
        return preg_replace('/\s+/', '', $value) ?? $value;
    }

    private function resolveStudentWriteCensusId(User $user): ?string
    {
        if ($this->isAdministrator($user)) {
            $requested = $this->resolveRequestedSchoolCensusId($user);
            return $requested !== null ? $this->resolveCanonicalSchoolCensusId($requested) : null;
        }

        return $this->resolveUserCensusId($user);
    }

    private function isPrincipal(User $user): bool
    {
        if ((int) ($user->role_id ?? 0) === 2) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return $roleName === 'principal';
    }
}
