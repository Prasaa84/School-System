<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\StudentDailyAttendance;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentAttendanceController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function index(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewAttendance($user)) {
            return response()->json(['message' => 'Only class teachers and principals can access daily attendance.'], 403);
        }

        if ($this->isClassTeacher($user) && $this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'date' => $this->resolveAttendanceDate($request),
                'class_info' => null,
                'summary' => $this->emptyAttendanceSummary(),
                'filters' => null,
                'data' => [],
                'message' => 'No class assignment found for this class teacher.',
            ]);
        }

        if (!Schema::hasTable('student_daily_attendance_tbl')) {
            return response()->json(['message' => 'Attendance table is not available.'], 422);
        }

        if ($this->isClassTeacher($user)) {
            $assignment = $this->resolveClassTeacherAssignment($user);
            if ($assignment === null) {
                return response()->json([
                    'date' => $this->resolveAttendanceDate($request),
                    'class_info' => null,
                    'summary' => $this->emptyAttendanceSummary(),
                    'filters' => null,
                    'data' => [],
                    'message' => 'No class assignment found for this class teacher.',
                ]);
            }

            $date = $this->resolveAttendanceDate($request);
            $this->ensureClassAttendanceRows($assignment, $date, $user);
            $students = $this->loadAttendanceStudentsForClass($assignment['sch_grd_cls_id'], $date);
            $classInfo = $this->loadAttendanceClassInfo($assignment['sch_grd_cls_id']);
            if ($classInfo !== null) {
                $classInfo['student_count'] = count($students);
            }

            return response()->json([
                'date' => $date,
                'class_info' => $classInfo,
                'summary' => $this->buildAttendanceSummary($students),
                'filters' => [
                    'date_from' => $date,
                    'date_to' => $date,
                    'year' => $assignment['year'],
                    'grade_id' => $assignment['grade_id'],
                    'class_id' => $assignment['class_id'],
                    'gender_id' => 0,
                    'search' => '',
                ],
                'pagination' => null,
                'data' => $students,
            ]);
        }

        $filters = $this->validatePrincipalFilters($request);
        ['data' => $students, 'summary' => $summary, 'pagination' => $pagination, 'date_headers' => $dateHeaders] = $this->loadAttendanceStudentsForPrincipal($user, $filters);

        return response()->json([
            'date' => $filters['date'],
            'class_info' => null,
            'summary' => $summary,
            'filters' => $filters,
            'pagination' => $pagination,
            'date_headers' => $dateHeaders,
            'data' => $students,
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->isClassTeacher($user)) {
            return response()->json(['message' => 'Only class teachers can mark daily attendance.'], 403);
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json(['message' => 'No class assignment found for this class teacher.'], 422);
        }

        if (!Schema::hasTable('student_daily_attendance_tbl')) {
            return response()->json(['message' => 'Attendance table is not available.'], 422);
        }

        $assignment = $this->resolveClassTeacherAssignment($user);
        if ($assignment === null) {
            return response()->json(['message' => 'No class assignment found for this class teacher.'], 422);
        }

        $validated = Validator::make($request->all(), [
            'student_id' => ['required', 'integer', 'exists:student_tbl,std_id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        $studentId = (int) $validated['student_id'];
        $date = isset($validated['date']) ? (string) $validated['date'] : $this->resolveAttendanceDate($request);

        if (!$this->studentBelongsToAssignedClass($studentId, $assignment['sch_grd_cls_id'])) {
            return response()->json(['message' => 'This student does not belong to the assigned class.'], 403);
        }

        /** @var StudentDailyAttendance|null $attendance */
        $attendance = StudentDailyAttendance::query()
            ->where('std_id', $studentId)
            ->where('attendance_date', $date)
            ->where('is_deleted', 0)
            ->first();

        $newStatus = $attendance === null || (int) ($attendance->status ?? 0) === 0 ? 1 : 0;
        $now = now();

        if ($attendance === null) {
            $attendance = new StudentDailyAttendance();
            $attendance->std_id = $studentId;
            $attendance->attendance_date = $date;
            $attendance->date_added = $now;
        }

        $attendance->status = $newStatus;
        $attendance->sch_grd_cls_id = $assignment['sch_grd_cls_id'];
        $attendance->census_id = $assignment['census_id'];
        $attendance->marked_by_user_id = is_numeric($user->user_id ?? null) ? (int) $user->user_id : null;
        $attendance->date_updated = $now;
        $attendance->is_deleted = 0;
        $attendance->save();

        return response()->json([
            'message' => $newStatus === 1 ? 'Attendance recorded.' : 'Attendance cleared.',
            'data' => [
                'student_id' => $studentId,
                'status' => $newStatus,
                'date' => $date,
            ],
        ]);
    }

    public function downloadReport(Request $request): StreamedResponse|JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->isPrincipal($user)) {
            return response()->json(['message' => 'Only principals can export attendance reports.'], 403);
        }

        if (!Schema::hasTable('student_daily_attendance_tbl')) {
            return response()->json(['message' => 'Attendance table is not available.'], 422);
        }

        $filters = $this->validatePrincipalFilters($request);
        $dateHeaders = $this->buildDateHeaders($filters['date_from'], $filters['date_to']);
        $rows = $this->buildPrincipalAttendanceExportRows($user, $filters, $dateHeaders);
        $periodSuffix = $filters['date_from'] === $filters['date_to']
            ? $filters['date_from']
            : ($filters['date_from'] . '_to_' . $filters['date_to']);
        $filename = 'student-attendance-report-' . $periodSuffix . '.xlsx';

        return response()->streamDownload(function () use ($rows, $dateHeaders): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Attendance Report');

            $sheet->setCellValue('A1', 'No.');
            $sheet->setCellValue('B1', 'Admission No');
            $sheet->setCellValue('C1', 'Name With Initials');

            $columnIndex = 4;
            foreach ($dateHeaders as $dateHeader) {
                $sheet->setCellValue($this->excelColumnName($columnIndex) . '1', $dateHeader);
                $columnIndex++;
            }

            foreach (array_values($rows) as $rowIndex => $row) {
                $excelRow = $rowIndex + 2;
                $sheet->setCellValue("A{$excelRow}", (string) ($rowIndex + 1));
                $sheet->setCellValue("B{$excelRow}", (string) ($row['admission_no'] ?? ''));
                $sheet->setCellValue("C{$excelRow}", (string) ($row['name_with_initials'] ?? ''));

                $dateColumnIndex = 4;
                foreach ($dateHeaders as $dateHeader) {
                    $value = $row['attendance_map'][$dateHeader] ?? null;
                    $sheet->setCellValue(
                        $this->excelColumnName($dateColumnIndex) . $excelRow,
                        $value === 0 || $value === 1 ? (string) $value : ''
                    );
                    $dateColumnIndex++;
                }
            }

            for ($index = 1; $index < $columnIndex; $index++) {
                $sheet->getColumnDimension($this->excelColumnName($index))->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function resolveAttendanceDate(Request $request): string
    {
        $validated = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        return isset($validated['date']) ? (string) $validated['date'] : now()->toDateString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadAttendanceStudentsForClass(int $schoolGradeClassId, string $date): array
    {
        $query = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->join('student_tbl as st', 'sgc.std_id', '=', 'st.std_id')
            ->leftJoin('student_daily_attendance_tbl as sda', function ($join) use ($date): void {
                $join->on('sda.std_id', '=', 'st.std_id')
                    ->where('sda.attendance_date', '=', $date)
                    ->where('sda.is_deleted', '=', 0);
            })
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId)
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                'st.gender_id',
                'sgct.year',
                'sgct.grade_id',
                'sgct.class_id',
                DB::raw('COALESCE(sda.status, 0) as status'),
            ])
            ->orderBy('st.index_no');

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }
        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        return $query->get()->map(fn ($row): array => $this->mapAttendanceStudentRow($row))->all();
    }

    /**
     * @param  array{date:string, date_from:string, date_to:string, year:int, grade_id:int, class_id:int, gender_id:int, page:int, per_page:int, search:string}  $filters
     * @return array{data:array<int, array<string, mixed>>, summary:array{total_students:int, present_students:int, absent_students:int}, pagination:array{page:int, per_page:int, total:int, last_page:int, from:int, to:int}, date_headers:array<int, string>}
     */
    private function loadAttendanceStudentsForPrincipal(User $user, array $filters): array
    {
        $rosterQuery = $this->buildPrincipalRosterQuery($user, $filters);
        $summaryQuery = $this->buildPrincipalAttendanceSummaryQuery($user, $filters);
        $total = (int) (clone $rosterQuery)->count();
        $presentStudents = (int) (clone $summaryQuery)->where('sda.status', 1)->count();
        $absentStudents = (int) (clone $summaryQuery)->where('sda.status', 0)->count();
        $page = max($filters['page'], 1);
        $perPage = max($filters['per_page'], 1);
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $dateHeaders = $this->buildDateHeaders($filters['date_from'], $filters['date_to']);

        $rows = (clone $rosterQuery)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $studentIds = $rows
            ->pluck('std_id')
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $attendanceRows = [];
        if ($studentIds !== []) {
            $attendanceRows = DB::table('student_daily_attendance_tbl as sda')
                ->whereIn('sda.std_id', $studentIds)
                ->whereBetween('sda.attendance_date', [$filters['date_from'], $filters['date_to']])
                ->where('sda.is_deleted', 0)
                ->select(['sda.std_id', 'sda.attendance_date', 'sda.status'])
                ->orderBy('sda.attendance_date')
                ->get();
        }

        $attendanceMap = [];
        foreach ($attendanceRows as $attendanceRow) {
            $studentId = is_numeric($attendanceRow->std_id ?? null) ? (int) $attendanceRow->std_id : 0;
            $date = (string) ($attendanceRow->attendance_date ?? '');
            if ($studentId <= 0 || $date === '') {
                continue;
            }

            $attendanceMap[$studentId] ??= [];
            $attendanceMap[$studentId][$date] = isset($attendanceRow->status) ? (int) $attendanceRow->status : null;
        }

        $from = $total > 0 ? $offset + 1 : 0;
        $to = $total > 0 ? min($offset + $perPage, $total) : 0;

        return [
            'data' => $rows->map(fn ($row): array => $this->mapPrincipalAttendanceStudentRow($row, $dateHeaders, $attendanceMap))->all(),
            'summary' => [
                'total_students' => $total,
                'present_students' => $presentStudents,
                'absent_students' => $absentStudents,
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
            'date_headers' => $dateHeaders,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadAttendanceClassInfo(int $schoolGradeClassId): ?array
    {
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

        $query = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId)
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.year',
                'sgct.grade_id',
                'sgct.class_id',
            ]);

        if ($gradeLabelColumn !== null) {
            $query->addSelect(DB::raw("gt.{$gradeLabelColumn} as grade"));
        }
        if ($classLabelColumn !== null) {
            $query->addSelect(DB::raw("ct.{$classLabelColumn} as class"));
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $grade = trim((string) ($row->grade ?? ''));
        $className = trim((string) ($row->class ?? ''));

        return [
            'sch_grd_cls_id' => (int) ($row->sch_grd_cls_id ?? 0),
            'year' => (int) ($row->year ?? 0),
            'grade_id' => (int) ($row->grade_id ?? 0),
            'class_id' => (int) ($row->class_id ?? 0),
            'grade' => $grade,
            'class' => $className,
            'grade_class' => trim("{$grade} {$className}"),
            'student_count' => 0,
        ];
    }

    private function studentBelongsToAssignedClass(int $studentId, int $schoolGradeClassId): bool
    {
        $query = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->where('sgc.std_id', $studentId)
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId);

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        return $query->exists();
    }

    private function canViewAttendance(?User $user): bool
    {
        return $this->isClassTeacher($user) || $this->isPrincipal($user);
    }

    private function isPrincipal(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) ($user->role_id ?? 0) === 2) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return $roleName === 'principal';
    }

    /**
     * @return array{date:string, date_from:string, date_to:string, year:int, grade_id:int, class_id:int, gender_id:int, page:int, per_page:int, search:string}
     */
    private function validatePrincipalFilters(Request $request): array
    {
        $validated = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'grade_id' => ['nullable', 'integer', 'min:0'],
            'class_id' => ['nullable', 'integer', 'min:0'],
            'gender_id' => ['nullable', 'integer', 'in:0,1,2'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'search' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $fallbackDate = isset($validated['date']) ? (string) $validated['date'] : now()->toDateString();
        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        $dateTo = trim((string) ($validated['date_to'] ?? ''));

        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = $fallbackDate;
            $dateTo = $fallbackDate;
        } elseif ($dateFrom === '') {
            $dateFrom = $dateTo;
        } elseif ($dateTo === '') {
            $dateTo = $dateFrom;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date' => $dateTo,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'year' => isset($validated['year']) ? (int) $validated['year'] : (int) now()->year,
            'grade_id' => isset($validated['grade_id']) ? (int) $validated['grade_id'] : 0,
            'class_id' => isset($validated['class_id']) ? (int) $validated['class_id'] : 0,
            'gender_id' => isset($validated['gender_id']) ? (int) $validated['gender_id'] : 0,
            'page' => isset($validated['page']) ? (int) $validated['page'] : 1,
            'per_page' => isset($validated['per_page']) ? (int) $validated['per_page'] : 100,
            'search' => trim((string) ($validated['search'] ?? '')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @return array{total_students:int, present_students:int, absent_students:int}
     */
    private function buildAttendanceSummary(array $students): array
    {
        $totalStudents = count($students);
        $presentStudents = count(array_filter($students, fn (array $row): bool => (int) ($row['status'] ?? 0) === 1));

        return [
            'total_students' => $totalStudents,
            'present_students' => $presentStudents,
            'absent_students' => max($totalStudents - $presentStudents, 0),
        ];
    }

    /**
     * @return array{total_students:int, present_students:int, absent_students:int}
     */
    private function emptyAttendanceSummary(): array
    {
        return [
            'total_students' => 0,
            'present_students' => 0,
            'absent_students' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAttendanceStudentRow(object $row): array
    {
        $grade = trim((string) ($row->grade ?? ''));
        $className = trim((string) ($row->class ?? ''));

        return [
            'std_id' => (int) ($row->std_id ?? 0),
            'index_no' => (string) ($row->index_no ?? ''),
            'admission_no' => (string) ($row->index_no ?? ''),
            'name_with_initials' => (string) ($row->name_with_initials ?? ''),
            'status' => (int) ($row->attendance_status ?? $row->status ?? 0),
            'attendance_date' => (string) ($row->attendance_date ?? ''),
            'gender_id' => (int) ($row->gender_id ?? 0),
            'gender_label' => $this->studentGenderLabel((int) ($row->gender_id ?? 0)),
            'year' => (int) ($row->year ?? 0),
            'grade_id' => (int) ($row->grade_id ?? 0),
            'class_id' => (int) ($row->class_id ?? 0),
            'grade' => $grade,
            'class' => $className,
            'grade_class' => trim("{$grade} {$className}"),
        ];
    }

    private function studentGenderLabel(int $genderId): string
    {
        return match ($genderId) {
            1 => 'Male',
            2 => 'Female',
            default => '',
        };
    }

    /**
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     */
    private function ensureClassAttendanceRows(array $assignment, string $date, User $user): void
    {
        $studentIds = DB::table('student_grade_class_tbl as sgc')
            ->where('sgc.sch_grd_cls_id', $assignment['sch_grd_cls_id'])
            ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), fn ($query) => $query->where('sgc.is_deleted', 0))
            ->pluck('sgc.std_id')
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): int => (int) $value)
            ->values();

        if ($studentIds->isEmpty()) {
            return;
        }

        $existingStudentIds = StudentDailyAttendance::query()
            ->where('attendance_date', $date)
            ->where('sch_grd_cls_id', $assignment['sch_grd_cls_id'])
            ->where('is_deleted', 0)
            ->whereIn('std_id', $studentIds->all())
            ->pluck('std_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $missingStudentIds = $studentIds->reject(fn (int $studentId): bool => in_array($studentId, $existingStudentIds, true))->values();
        if ($missingStudentIds->isEmpty()) {
            return;
        }

        $userId = is_numeric($user->user_id ?? null) ? (int) $user->user_id : null;
        $now = now();

        DB::table('student_daily_attendance_tbl')->insert(
            $missingStudentIds->map(fn (int $studentId): array => [
                'std_id' => $studentId,
                'attendance_date' => $date,
                'status' => 0,
                'sch_grd_cls_id' => $assignment['sch_grd_cls_id'],
                'census_id' => $assignment['census_id'],
                'marked_by_user_id' => $userId,
                'date_added' => $now,
                'date_updated' => $now,
                'is_deleted' => 0,
            ])->all()
        );
    }

    /**
     * @param  array{date:string, date_from:string, date_to:string, year:int, grade_id:int, class_id:int, gender_id:int, page:int, per_page:int, search:string}  $filters
     */
    private function buildPrincipalRosterQuery(User $user, array $filters): Builder
    {
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

        $gradeSelect = $gradeLabelColumn !== null ? "gt.{$gradeLabelColumn}" : "''";
        $classSelect = $classLabelColumn !== null ? "ct.{$classLabelColumn}" : "''";
        $search = $filters['search'];

        $query = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->join('student_tbl as st', 'sgc.std_id', '=', 'st.std_id')
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                'st.gender_id',
                'sgct.year',
                'sgct.grade_id',
                'sgct.class_id',
                DB::raw("{$gradeSelect} as grade"),
                DB::raw("{$classSelect} as class"),
            ])
            ->where('sgct.year', $filters['year'])
            ->when($filters['grade_id'] > 0, fn ($builder) => $builder->where('sgct.grade_id', $filters['grade_id']))
            ->when($filters['class_id'] > 0, fn ($builder) => $builder->where('sgct.class_id', $filters['class_id']))
            ->when($filters['gender_id'] > 0, fn ($builder) => $builder->where('st.gender_id', $filters['gender_id']))
            ->when($search !== '', function ($builder) use ($search, $gradeSelect, $classSelect): void {
                $builder->where(function ($inner) use ($search, $gradeSelect, $classSelect): void {
                    $inner->where('st.index_no', 'like', "%{$search}%")
                        ->orWhere('st.name_with_initials', 'like', "%{$search}%")
                        ->orWhereRaw("COALESCE({$gradeSelect}, '') like ?", ["%{$search}%"])
                        ->orWhereRaw("COALESCE({$classSelect}, '') like ?", ["%{$search}%"]);
                });
            })
            ->orderBy('sgct.grade_id')
            ->orderBy('sgct.class_id')
            ->orderBy('st.index_no');

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }
        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        $this->applySchoolScope($query, $user, 'sgct', 'census_id');

        return $query->groupBy([
            'st.std_id',
            'st.index_no',
            'st.name_with_initials',
            'st.gender_id',
            'sgct.year',
            'sgct.grade_id',
            'sgct.class_id',
            DB::raw($gradeSelect),
            DB::raw($classSelect),
        ]);
    }

    /**
     * @param  array{date:string, date_from:string, date_to:string, year:int, grade_id:int, class_id:int, gender_id:int, page:int, per_page:int, search:string}  $filters
     */
    private function buildPrincipalAttendanceSummaryQuery(User $user, array $filters): Builder
    {
        $search = $filters['search'];

        $query = DB::table('student_daily_attendance_tbl as sda')
            ->join('student_tbl as st', 'sda.std_id', '=', 'st.std_id')
            ->join('school_grade_class_tbl as sgct', 'sda.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->whereBetween('sda.attendance_date', [$filters['date_from'], $filters['date_to']])
            ->where('sgct.year', $filters['year'])
            ->where('sda.is_deleted', 0)
            ->when($filters['grade_id'] > 0, fn ($builder) => $builder->where('sgct.grade_id', $filters['grade_id']))
            ->when($filters['class_id'] > 0, fn ($builder) => $builder->where('sgct.class_id', $filters['class_id']))
            ->when($filters['gender_id'] > 0, fn ($builder) => $builder->where('st.gender_id', $filters['gender_id']))
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('st.index_no', 'like', "%{$search}%")
                        ->orWhere('st.name_with_initials', 'like', "%{$search}%");
                });
            });

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $this->applySchoolScope($query, $user, 'sgct', 'census_id');

        return $query;
    }

    /**
     * @param  array<int, string>  $dateHeaders
     * @param  array<int, array<string, int|null>>  $attendanceMap
     * @return array<string, mixed>
     */
    private function mapPrincipalAttendanceStudentRow(object $row, array $dateHeaders, array $attendanceMap): array
    {
        $studentId = is_numeric($row->std_id ?? null) ? (int) $row->std_id : 0;
        $statuses = [];
        foreach ($dateHeaders as $date) {
            $statuses[$date] = $attendanceMap[$studentId][$date] ?? null;
        }

        return [
            'std_id' => $studentId,
            'index_no' => (string) ($row->index_no ?? ''),
            'admission_no' => (string) ($row->index_no ?? ''),
            'name_with_initials' => (string) ($row->name_with_initials ?? ''),
            'attendance_map' => $statuses,
            'gender_id' => (int) ($row->gender_id ?? 0),
            'gender_label' => $this->studentGenderLabel((int) ($row->gender_id ?? 0)),
            'year' => (int) ($row->year ?? 0),
            'grade_id' => (int) ($row->grade_id ?? 0),
            'class_id' => (int) ($row->class_id ?? 0),
            'grade' => trim((string) ($row->grade ?? '')),
            'class' => trim((string) ($row->class ?? '')),
            'grade_class' => trim(sprintf('%s %s', (string) ($row->grade ?? ''), (string) ($row->class ?? ''))),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function buildDateHeaders(string $dateFrom, string $dateTo): array
    {
        $start = strtotime($dateFrom);
        $end = strtotime($dateTo);
        if ($start === false || $end === false || $start > $end) {
            return [];
        }

        $headers = [];
        for ($timestamp = $start; $timestamp <= $end; $timestamp = strtotime('+1 day', $timestamp)) {
            $headers[] = date('Y-m-d', $timestamp);
        }

        return $headers;
    }

    /**
     * @param  array{date:string, date_from:string, date_to:string, year:int, grade_id:int, class_id:int, gender_id:int, page:int, per_page:int, search:string}  $filters
     * @param  array<int, string>  $dateHeaders
     * @return array<int, array<string, mixed>>
     */
    private function buildPrincipalAttendanceExportRows(User $user, array $filters, array $dateHeaders): array
    {
        $rows = $this->buildPrincipalRosterQuery($user, $filters)->get();
        $studentIds = $rows
            ->pluck('std_id')
            ->filter(fn ($value): bool => is_numeric($value))
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $attendanceMap = [];
        if ($studentIds !== []) {
            $attendanceRows = DB::table('student_daily_attendance_tbl as sda')
                ->whereIn('sda.std_id', $studentIds)
                ->whereBetween('sda.attendance_date', [$filters['date_from'], $filters['date_to']])
                ->where('sda.is_deleted', 0)
                ->select(['sda.std_id', 'sda.attendance_date', 'sda.status'])
                ->orderBy('sda.attendance_date')
                ->get();

            foreach ($attendanceRows as $attendanceRow) {
                $studentId = is_numeric($attendanceRow->std_id ?? null) ? (int) $attendanceRow->std_id : 0;
                $date = (string) ($attendanceRow->attendance_date ?? '');
                if ($studentId <= 0 || $date === '') {
                    continue;
                }

                $attendanceMap[$studentId] ??= [];
                $attendanceMap[$studentId][$date] = isset($attendanceRow->status) ? (int) $attendanceRow->status : null;
            }
        }

        return $rows
            ->map(fn ($row): array => $this->mapPrincipalAttendanceStudentRow($row, $dateHeaders, $attendanceMap))
            ->all();
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
