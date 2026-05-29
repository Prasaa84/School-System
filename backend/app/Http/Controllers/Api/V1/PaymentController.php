<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\SchoolDetail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function options(): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canAccessPayments($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'schools' => $this->loadSchoolOptions($user),
                'years' => [],
            ]);
        }

        Log::info('Payments options requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'requested_school_census_id' => $this->resolveRequestedSchoolCensusId($user),
        ]);

        return response()->json([
            'schools' => $this->loadSchoolOptions($user),
            'years' => $this->loadFeeYears(),
        ]);
    }

    public function student(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canAccessPayments($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'index_no' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
        ])->validate();

        $censusId = $this->resolveTargetSchoolCensusId($user);
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        $indexNo = trim((string) ($validated['index_no'] ?? ''));

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'message' => 'Student not found for your assigned class.',
            ], 404);
        }

        Log::info('Payments student lookup requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'index_no' => $indexNo,
            'resolved_school_census_id' => $censusId,
        ]);

        if ($censusId === null) {
            Log::warning('Payments student lookup blocked: school context missing.', [
                'user_id' => $user->user_id ?? null,
                'role_id' => $user->role_id ?? null,
                'index_no' => $indexNo,
            ]);

            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? 'Select a school first.'
                    : 'School is not assigned for this user.',
            ], 422);
        }

        $student = $this->loadStudentByIndexNo($indexNo, $censusId);
        if ($student === null) {
            Log::warning('Payments student lookup failed: student not found for school.', [
                'user_id' => $user->user_id ?? null,
                'index_no' => $indexNo,
                'school_census_id' => $censusId,
            ]);

            return response()->json([
                'message' => 'Student not found for the selected school.',
            ], 404);
        }

        if ($classTeacherAssignment !== null && !$this->studentBelongsToTeacherClass($student['std_id'], $classTeacherAssignment)) {
            return response()->json([
                'message' => 'Student not found for your assigned class.',
            ], 404);
        }

        $payments = $this->loadStudentPayments($indexNo, $censusId);

        Log::info('Payments student lookup completed.', [
            'user_id' => $user->user_id ?? null,
            'index_no' => $indexNo,
            'school_census_id' => $censusId,
            'payment_count' => count($payments),
        ]);

        return response()->json([
            'student' => $student,
            'payments' => $payments,
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewPaymentReport($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'filters' => $this->validateReportFilters($request),
                'scope' => null,
                'summary' => $this->emptyPaymentReportSummary(),
                'data' => [],
                'message' => 'No class assignment found for this class teacher.',
            ]);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? 'Select a school first.'
                    : 'School is not assigned for this user.',
            ], 422);
        }

        $filters = $this->validateReportFilters($request);
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        if ($classTeacherAssignment !== null) {
            $filters['year'] = (int) $classTeacherAssignment['year'];
            $filters['grade_id'] = (int) $classTeacherAssignment['grade_id'];
            $filters['class_id'] = (int) $classTeacherAssignment['class_id'];
        }

        $rows = $this->loadPaymentReportRows($user, $censusId, $filters, $classTeacherAssignment);

        return response()->json([
            'filters' => $filters,
            'scope' => $classTeacherAssignment !== null
                ? $this->loadPaymentReportScope($classTeacherAssignment['sch_grd_cls_id'])
                : null,
            'summary' => $this->buildPaymentReportSummary($rows),
            'data' => $rows,
        ]);
    }

    public function downloadReport(Request $request): StreamedResponse|JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewPaymentReport($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json(['message' => 'No class assignment found for this class teacher.'], 422);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? 'Select a school first.'
                    : 'School is not assigned for this user.',
            ], 422);
        }

        $filters = $this->validateReportFilters($request);
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        if ($classTeacherAssignment !== null) {
            $filters['year'] = (int) $classTeacherAssignment['year'];
            $filters['grade_id'] = (int) $classTeacherAssignment['grade_id'];
            $filters['class_id'] = (int) $classTeacherAssignment['class_id'];
        }

        $rows = $this->loadPaymentReportRows($user, $censusId, $filters, $classTeacherAssignment);
        $scope = $classTeacherAssignment !== null
            ? $this->loadPaymentReportScope((int) $classTeacherAssignment['sch_grd_cls_id'])
            : null;
        $schoolName = $this->loadSchoolName($censusId);
        $mainHeading = trim($schoolName) !== '' ? ($schoolName . ' - SDS Payments') : 'SDS Payments';
        $reportHeading = $this->buildPaymentExportHeading($filters, $scope);
        $paidTotal = array_reduce(
            $rows,
            fn (float $total, array $row): float => $total + (($row['payment_status'] ?? 'not_paid') === 'paid' ? (float) ($row['total'] ?? 0) : 0.0),
            0.0
        );

        $yearSuffix = (int) ($filters['year'] ?? 0) > 0 ? (string) $filters['year'] : 'all-years';
        $classSuffix = trim((string) ($scope['grade_class'] ?? ''));
        $normalizedClassSuffix = $classSuffix !== '' ? ('-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $classSuffix)) : '';
        $filename = 'payments-report' . $normalizedClassSuffix . '-' . $yearSuffix . '.xlsx';

        return response()->streamDownload(function () use ($rows, $mainHeading, $reportHeading, $paidTotal): void {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Payments Report');

            $headers = [
                'No.',
                'Admission No',
                'Name With Initials',
                'Grade/Class',
                'Invoice No',
                'Year',
                'Status',
                'Annual Fee',
                'Member Fee',
                'Total',
                'Paid Date',
            ];

            $sheet->setCellValue('A1', $mainHeading);
            $sheet->mergeCells('A1:K1');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
            $sheet->setCellValue('A2', $reportHeading);
            $sheet->mergeCells('A2:K2');
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

            foreach ($headers as $index => $header) {
                $sheet->setCellValue($this->excelColumnName($index + 1) . '4', $header);
            }
            $sheet->getStyle('A4:K4')->getFont()->setBold(true);

            foreach (array_values($rows) as $rowIndex => $row) {
                $excelRow = $rowIndex + 5;
                $isPaid = ($row['payment_status'] ?? 'not_paid') === 'paid';
                $hasMemberFee = $isPaid && (bool) ($row['include_member_fee'] ?? false);
                $paidDate = ($row['payment_status'] ?? 'not_paid') === 'paid'
                    ? $this->normalizeExportDate((string) ($row['paid_date'] ?? ''))
                    : '-';

                $sheet->setCellValue("A{$excelRow}", (string) ($rowIndex + 1));
                $sheet->setCellValueExplicit("B{$excelRow}", (string) ($row['admission_no'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("C{$excelRow}", (string) ($row['name_with_initials'] ?? ''));
                $sheet->setCellValue("D{$excelRow}", (string) ($row['grade_class'] ?? ''));
                $sheet->setCellValueExplicit("E{$excelRow}", (string) ($row['invoice_no'] ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("F{$excelRow}", (string) ($row['year'] ?? ''));
                $sheet->setCellValue("G{$excelRow}", $isPaid ? 'Paid' : 'Not Paid');
                $sheet->setCellValue("H{$excelRow}", (float) ($row['annual_fee'] ?? 0));
                $sheet->getStyle("H{$excelRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

                if ($hasMemberFee) {
                    $sheet->setCellValue("I{$excelRow}", (float) ($row['member_fee'] ?? 0));
                    $sheet->getStyle("I{$excelRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                } else {
                    $sheet->setCellValue("I{$excelRow}", '-');
                    $sheet->getStyle("I{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                if ($isPaid) {
                    $sheet->setCellValue("J{$excelRow}", (float) ($row['total'] ?? 0));
                    $sheet->getStyle("J{$excelRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                } else {
                    $sheet->setCellValue("J{$excelRow}", '-');
                    $sheet->getStyle("J{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->setCellValue("K{$excelRow}", $paidDate);
            }

            $totalsRow = count($rows) + 5;
            $sheet->setCellValue("I{$totalsRow}", 'Total Fee');
            $sheet->setCellValue("J{$totalsRow}", $paidTotal);
            $sheet->getStyle("J{$totalsRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
            $sheet->getStyle("I{$totalsRow}:J{$totalsRow}")->getFont()->setBold(true);
            $sheet->getStyle("J{$totalsRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            for ($index = 1; $index <= count($headers); $index++) {
                $sheet->getColumnDimension($this->excelColumnName($index))->setAutoSize(true);
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function fee(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canAccessPayments($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])->validate();

        $year = (int) ($validated['year'] ?? 0);

        Log::info('Payments fee lookup requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'year' => $year,
        ]);

        $fee = $this->loadFeeForYear($year);
        if ($fee === null) {
            Log::warning('Payments fee lookup failed: no fee assigned for year.', [
                'user_id' => $user->user_id ?? null,
                'year' => $year,
            ]);

            return response()->json([
                'message' => 'No fees assigned to this year.',
            ], 404);
        }

        return response()->json($fee);
    }

    public function feeTypes(): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewFeeTypes($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Log::info('Payments fee types requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
        ]);

        return response()->json([
            'fee_types' => $this->loadFeeTypeRows(),
        ]);
    }

    public function storeFeeType(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageFeeTypes($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_fee' => ['required', 'numeric', 'min:0'],
            'member_fee' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $table = 'sds_annual_fee_type_tbl';
        if (!Schema::hasTable($table)) {
            Log::error('Payments fee type create failed: fee types table missing.', [
                'user_id' => $user->user_id ?? null,
                'table' => $table,
            ]);

            return response()->json([
                'message' => 'Annual fee types table is missing.',
            ], 500);
        }

        $year = (int) ($validated['year'] ?? 0);
        $annualFee = (float) ($validated['annual_fee'] ?? 0);
        $memberFee = (float) ($validated['member_fee'] ?? 0);

        Log::info('Payments fee type create requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'year' => $year,
            'annual_fee' => $annualFee,
            'member_fee' => $memberFee,
        ]);

        if ($this->feeTypeYearExists($year)) {
            Log::warning('Payments fee type create blocked: year already exists.', [
                'user_id' => $user->user_id ?? null,
                'year' => $year,
            ]);

            return response()->json([
                'message' => 'Annual fee type already exists for this year.',
            ], 422);
        }

        $feeTypeId = (int) DB::table($table)->insertGetId([
            'year' => $year,
            'sds_annual_fee' => $annualFee,
            'sds_member_fee' => $memberFee,
            'date_added' => now(),
        ], 'sds_annual_fee_id');

        Log::info('Payments fee type create completed.', [
            'user_id' => $user->user_id ?? null,
            'fee_type_id' => $feeTypeId,
            'year' => $year,
        ]);

        return response()->json([
            'message' => 'Annual fee type added successfully.',
            'fee_type' => $this->loadFeeTypeById($feeTypeId),
        ], 201);
    }

    public function updateFeeType(Request $request, int $feeTypeId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageFeeTypes($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annual_fee' => ['required', 'numeric', 'min:0'],
            'member_fee' => ['required', 'numeric', 'min:0'],
        ])->validate();

        $table = 'sds_annual_fee_type_tbl';
        if (!Schema::hasTable($table)) {
            Log::error('Payments fee type update failed: fee types table missing.', [
                'user_id' => $user->user_id ?? null,
                'table' => $table,
                'fee_type_id' => $feeTypeId,
            ]);

            return response()->json([
                'message' => 'Annual fee types table is missing.',
            ], 500);
        }

        $existing = $this->loadFeeTypeById($feeTypeId);
        if ($existing === null) {
            Log::warning('Payments fee type update failed: record not found.', [
                'user_id' => $user->user_id ?? null,
                'fee_type_id' => $feeTypeId,
            ]);

            return response()->json([
                'message' => 'Annual fee type not found.',
            ], 404);
        }

        $year = (int) ($validated['year'] ?? 0);
        $annualFee = (float) ($validated['annual_fee'] ?? 0);
        $memberFee = (float) ($validated['member_fee'] ?? 0);

        Log::info('Payments fee type update requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'fee_type_id' => $feeTypeId,
            'year' => $year,
            'annual_fee' => $annualFee,
            'member_fee' => $memberFee,
        ]);

        if ($this->feeTypeYearExists($year, $feeTypeId)) {
            Log::warning('Payments fee type update blocked: year already exists on another record.', [
                'user_id' => $user->user_id ?? null,
                'fee_type_id' => $feeTypeId,
                'year' => $year,
            ]);

            return response()->json([
                'message' => 'Annual fee type already exists for this year.',
            ], 422);
        }

        DB::table($table)
            ->where('sds_annual_fee_id', $feeTypeId)
            ->update([
                'year' => $year,
                'sds_annual_fee' => $annualFee,
                'sds_member_fee' => $memberFee,
            ]);

        Log::info('Payments fee type update completed.', [
            'user_id' => $user->user_id ?? null,
            'fee_type_id' => $feeTypeId,
            'year' => $year,
        ]);

        return response()->json([
            'message' => 'Annual fee type updated successfully.',
            'fee_type' => $this->loadFeeTypeById($feeTypeId),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManagePayments($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'index_no' => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'invoice_no' => ['required', 'string', 'max:10'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'include_member_fee' => ['sometimes', 'boolean'],
        ])->validate();

        $censusId = $this->resolveTargetSchoolCensusId($user);
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        $indexNo = trim((string) ($validated['index_no'] ?? ''));
        $invoiceNo = trim((string) ($validated['invoice_no'] ?? ''));
        $year = (int) ($validated['year'] ?? 0);
        $includeMemberFee = filter_var($validated['include_member_fee'] ?? false, FILTER_VALIDATE_BOOL);

        Log::info('Payments create requested.', [
            'user_id' => $user->user_id ?? null,
            'role_id' => $user->role_id ?? null,
            'index_no' => $indexNo,
            'invoice_no' => $invoiceNo,
            'year' => $year,
            'include_member_fee' => $includeMemberFee,
            'resolved_school_census_id' => $censusId,
        ]);

        if ($censusId === null) {
            Log::warning('Payments create blocked: school context missing.', [
                'user_id' => $user->user_id ?? null,
                'index_no' => $indexNo,
                'invoice_no' => $invoiceNo,
                'year' => $year,
            ]);

            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? 'Select a school first.'
                    : 'School is not assigned for this user.',
            ], 422);
        }

        $student = $this->loadStudentByIndexNo($indexNo, $censusId);
        if ($student === null) {
            Log::warning('Payments create failed: student not found for school.', [
                'user_id' => $user->user_id ?? null,
                'index_no' => $indexNo,
                'school_census_id' => $censusId,
            ]);

            return response()->json([
                'message' => 'Student not found for the selected school.',
            ], 404);
        }

        if ($classTeacherAssignment !== null && !$this->studentBelongsToTeacherClass($student['std_id'], $classTeacherAssignment)) {
            return response()->json([
                'message' => 'Student not found for your assigned class.',
            ], 404);
        }

        $fee = $this->loadFeeForYear($year);
        if ($fee === null) {
            Log::warning('Payments create failed: fee year not configured.', [
                'user_id' => $user->user_id ?? null,
                'index_no' => $indexNo,
                'year' => $year,
            ]);

            return response()->json([
                'message' => 'No fees assigned to this year.',
            ], 422);
        }

        if ($this->paymentExists($indexNo, $censusId, $year)) {
            Log::warning('Payments create blocked: duplicate payment for student and year.', [
                'user_id' => $user->user_id ?? null,
                'index_no' => $indexNo,
                'school_census_id' => $censusId,
                'year' => $year,
            ]);

            return response()->json([
                'message' => 'Payment record already exists for this student and year.',
            ], 422);
        }

        $paymentTable = 'sds_fee_payment_tbl';
        if (!Schema::hasTable($paymentTable)) {
            Log::error('Payments create failed: payments table missing.', [
                'user_id' => $user->user_id ?? null,
                'index_no' => $indexNo,
                'table' => $paymentTable,
            ]);

            return response()->json([
                'message' => 'Payments table is missing.',
            ], 500);
        }

        $total = (int) round((float) $fee['annual_fee'] + ($includeMemberFee ? (float) $fee['member_fee'] : 0));
        $now = now();

        $insert = [
            'adm_no' => $indexNo,
            'invoice_no' => $invoiceNo,
            'year' => $year,
            'is_sds_member_fee' => $includeMemberFee ? '1' : '0',
            'total' => $total,
            'paid_date' => $now,
            'date_updated' => $now,
            'is_deleted' => 0,
        ];

        $paymentId = (int) DB::table($paymentTable)->insertGetId($insert);

        Log::info('Payments create completed.', [
            'user_id' => $user->user_id ?? null,
            'payment_id' => $paymentId,
            'index_no' => $indexNo,
            'school_census_id' => $censusId,
            'year' => $year,
            'include_member_fee' => $includeMemberFee,
            'total' => $total,
        ]);

        return response()->json([
            'message' => 'Payment added successfully.',
            'payment' => $this->loadPaymentById($paymentId, $censusId),
            'student' => $student,
        ], 201);
    }

    private function canAccessPayments(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return true;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2, 4, 7], true) || $this->isClassTeacher($user)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal', 'student'], true);
    }

    private function canViewPaymentReport(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return true;
        }

        if ($this->isClassTeacher($user)) {
            return true;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2, 4], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal', 'sds user'], true);
    }

    private function canManagePayments(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2, 4], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal'], true);
    }

    /**
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     */
    private function studentBelongsToTeacherClass(int $studentId, array $assignment): bool
    {
        if ($studentId <= 0 || !Schema::hasTable('student_grade_class_tbl')) {
            return false;
        }

        $query = DB::table('student_grade_class_tbl as sgc')
            ->where('sgc.std_id', $studentId)
            ->where('sgc.sch_grd_cls_id', $assignment['sch_grd_cls_id']);

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }

        return $query->exists();
    }

    private function canViewFeeTypes(?User $user): bool
    {
        return $this->canManageFeeTypes($user) || ((int) ($user?->role_id ?? 0) === 4) || strtolower(trim((string) ($user?->role?->role_name ?? ''))) === 'sds user';
    }

    private function canManageFeeTypes(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal'], true);
    }

    private function resolveTargetSchoolCensusId(?User $user): ?string
    {
        if ($this->isAdministrator($user)) {
            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function loadSchoolOptions(?User $user): array
    {
        if (!$this->isAdministrator($user)) {
            return [];
        }

        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return [];
        }

        $query = SchoolDetail::query()->select(['census_id', 'sch_name']);
        if (Schema::hasColumn($schoolTable, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->orderBy('sch_name')
            ->get()
            ->map(fn (SchoolDetail $row): array => [
                'id' => (int) $row->census_id,
                'label' => trim((string) ($row->sch_name ?? '')) !== ''
                    ? (string) $row->sch_name
                    : (string) ($row->census_id ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<int>
     */
    private function loadFeeYears(): array
    {
        if (!Schema::hasTable('sds_annual_fee_type_tbl')) {
            return [];
        }

        return DB::table('sds_annual_fee_type_tbl')
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFeeForYear(int $year): ?array
    {
        if ($year <= 0 || !Schema::hasTable('sds_annual_fee_type_tbl')) {
            return null;
        }

        $row = DB::table('sds_annual_fee_type_tbl')
            ->where('year', $year)
            ->orderByDesc('sds_annual_fee_id')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'year' => (int) ($row->year ?? 0),
            'annual_fee' => (float) ($row->sds_annual_fee ?? 0),
            'member_fee' => (float) ($row->sds_member_fee ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadFeeTypeRows(): array
    {
        if (!Schema::hasTable('sds_annual_fee_type_tbl')) {
            return [];
        }

        return DB::table('sds_annual_fee_type_tbl')
            ->orderByDesc('year')
            ->orderByDesc('sds_annual_fee_id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) ($row->sds_annual_fee_id ?? 0),
                'year' => (int) ($row->year ?? 0),
                'annual_fee' => (float) ($row->sds_annual_fee ?? 0),
                'member_fee' => (float) ($row->sds_member_fee ?? 0),
                'date_added' => (string) ($row->date_added ?? ''),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadFeeTypeById(int $feeTypeId): ?array
    {
        if ($feeTypeId <= 0 || !Schema::hasTable('sds_annual_fee_type_tbl')) {
            return null;
        }

        $row = DB::table('sds_annual_fee_type_tbl')
            ->where('sds_annual_fee_id', $feeTypeId)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row->sds_annual_fee_id ?? 0),
            'year' => (int) ($row->year ?? 0),
            'annual_fee' => (float) ($row->sds_annual_fee ?? 0),
            'member_fee' => (float) ($row->sds_member_fee ?? 0),
            'date_added' => (string) ($row->date_added ?? ''),
        ];
    }

    private function feeTypeYearExists(int $year, ?int $exceptId = null): bool
    {
        if ($year <= 0 || !Schema::hasTable('sds_annual_fee_type_tbl')) {
            return false;
        }

        $query = DB::table('sds_annual_fee_type_tbl')->where('year', $year);
        if ($exceptId !== null && $exceptId > 0) {
            $query->where('sds_annual_fee_id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadStudentByIndexNo(string $indexNo, string $censusId): ?array
    {
        if (!Schema::hasTable('student_tbl')) {
            return null;
        }

        $query = DB::table('student_tbl as st')
            ->leftJoin('school_details_tbl as sc', 'sc.census_id', '=', 'st.census_id')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.fullname',
                'st.name_with_initials',
                'st.census_id',
                'sc.sch_name as school_name',
            ])
            ->where('st.index_no', $indexNo)
            ->whereIn('st.census_id', $this->censusCandidates($censusId))
            ->orderByDesc('st.std_id');

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        return [
            'std_id' => (int) ($row->std_id ?? 0),
            'index_no' => (string) ($row->index_no ?? ''),
            'fullname' => (string) ($row->fullname ?? ''),
            'name_with_initials' => (string) ($row->name_with_initials ?? ''),
            'census_id' => (string) ($row->census_id ?? ''),
            'school_name' => (string) ($row->school_name ?? ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadStudentPayments(string $indexNo, string $censusId): array
    {
        if (!Schema::hasTable('sds_fee_payment_tbl')) {
            return [];
        }

        $studentExists = DB::table('student_tbl')
            ->where('index_no', $indexNo)
            ->whereIn('census_id', $this->censusCandidates($censusId))
            ->when(
                Schema::hasColumn('student_tbl', 'is_deleted'),
                fn ($query) => $query->where('is_deleted', 0)
            )
            ->exists();

        if (!$studentExists) {
            return [];
        }

        $query = DB::table('sds_fee_payment_tbl as p')
            ->leftJoin('sds_annual_fee_type_tbl as f', 'f.year', '=', 'p.year')
            ->select([
                'p.id',
                'p.invoice_no',
                'p.year',
                'p.is_sds_member_fee',
                'p.total',
                'p.paid_date',
                'f.sds_annual_fee',
                'f.sds_member_fee',
            ])
            ->where('p.adm_no', $indexNo)
            ->orderByDesc('p.year')
            ->orderByDesc('p.id');

        if (Schema::hasColumn('sds_fee_payment_tbl', 'is_deleted')) {
            $query->where('p.is_deleted', 0);
        }

        return $query
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) ($row->id ?? 0),
                'invoice_no' => (string) ($row->invoice_no ?? ''),
                'year' => (int) ($row->year ?? 0),
                'include_member_fee' => (string) ($row->is_sds_member_fee ?? '0') === '1',
                'total' => (float) ($row->total ?? 0),
                'paid_date' => (string) ($row->paid_date ?? ''),
                'annual_fee' => (float) ($row->sds_annual_fee ?? 0),
                'member_fee' => (float) ($row->sds_member_fee ?? 0),
            ])
            ->all();
    }

    /**
     * @return array{year:int, grade_id:int, class_id:int, admission_no:string, invoice_no:string, payment_status:string}
     */
    private function validateReportFilters(Request $request): array
    {
        $validated = Validator::make($request->all(), [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'grade_id' => ['nullable', 'integer', 'min:1'],
            'class_id' => ['nullable', 'integer', 'min:1'],
            'admission_no' => ['nullable', 'string', 'max:20'],
            'invoice_no' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'in:all,paid,not_paid'],
        ])->validate();

        return [
            'year' => (int) ($validated['year'] ?? 0),
            'grade_id' => (int) ($validated['grade_id'] ?? 0),
            'class_id' => (int) ($validated['class_id'] ?? 0),
            'admission_no' => trim((string) ($validated['admission_no'] ?? '')),
            'invoice_no' => trim((string) ($validated['invoice_no'] ?? '')),
            'payment_status' => trim((string) ($validated['payment_status'] ?? 'all')) ?: 'all',
        ];
    }

    /**
     * @param  array{year:int, grade_id:int, class_id:int, admission_no:string, invoice_no:string, payment_status:string}  $filters
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}|null  $classTeacherAssignment
     * @return array<int, array<string, mixed>>
     */
    private function loadPaymentReportRows(?User $user, string $censusId, array $filters, ?array $classTeacherAssignment = null): array
    {
        if (
            !Schema::hasTable('sds_fee_payment_tbl')
            || !Schema::hasTable('student_tbl')
            || !Schema::hasTable('student_grade_class_tbl')
            || !Schema::hasTable('school_grade_class_tbl')
        ) {
            return [];
        }

        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
            'grade_en',
            'grade_si',
            'grade_ta',
            'grade',
        ]);
        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]);
        $gradeSelect = $gradeLabelColumn !== null ? "gt.{$gradeLabelColumn}" : "''";
        $classSelect = $classLabelColumn !== null ? "ct.{$classLabelColumn}" : "''";
        $assignmentKeyColumn = Schema::hasColumn('student_grade_class_tbl', 'st_gr_cl_id') ? 'st_gr_cl_id' : 'sch_grd_cls_id';
        $reportYear = (int) ($filters['year'] ?? 0);

        $latestAssignmentSubquery = DB::table('student_grade_class_tbl as sgc_latest')
            ->select([
                'sgc_latest.std_id',
                DB::raw("MAX(sgc_latest.{$assignmentKeyColumn}) as latest_assignment_key"),
            ])
            ->groupBy('sgc_latest.std_id');

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $latestAssignmentSubquery->where('sgc_latest.is_deleted', 0);
        }

        $query = DB::table('student_tbl as st')
            ->joinSub($latestAssignmentSubquery, 'latest_sgc', function ($join): void {
                $join->on('latest_sgc.std_id', '=', 'st.std_id');
            })
            ->join('student_grade_class_tbl as sgc', function ($join) use ($assignmentKeyColumn): void {
                $join->on('sgc.std_id', '=', 'st.std_id')
                    ->on("sgc.{$assignmentKeyColumn}", '=', 'latest_sgc.latest_assignment_key');
            })
            ->join('school_grade_class_tbl as sgct', function ($join): void {
                $join->on('sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id');
            })
            ->leftJoin('sds_fee_payment_tbl as p', function ($join) use ($filters): void {
                $join->on('p.adm_no', '=', 'st.index_no');
                if (($filters['year'] ?? 0) > 0) {
                    $join->where('p.year', '=', (int) $filters['year']);
                }
                if (Schema::hasColumn('sds_fee_payment_tbl', 'is_deleted')) {
                    $join->where('p.is_deleted', '=', 0);
                }
            })
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->leftJoin('sds_annual_fee_type_tbl as f', 'f.year', '=', DB::raw((string) $reportYear))
            ->select([
                'p.id',
                'st.index_no',
                'p.invoice_no',
                DB::raw(($reportYear > 0 ? (string) $reportYear : 'p.year') . ' as report_year'),
                'p.is_sds_member_fee',
                'p.total',
                'p.paid_date',
                'st.std_id',
                'st.fullname',
                'st.name_with_initials',
                'sgct.grade_id',
                'sgct.class_id',
                DB::raw("{$gradeSelect} as grade"),
                DB::raw("{$classSelect} as class"),
                'f.sds_annual_fee',
                'f.sds_member_fee',
                DB::raw("CASE WHEN p.id IS NULL THEN 'not_paid' ELSE 'paid' END as payment_status"),
            ])
            ->whereIn('st.census_id', $this->censusCandidates($censusId));

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }
        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        if ($classTeacherAssignment !== null) {
            $query->where('sgct.sch_grd_cls_id', (int) $classTeacherAssignment['sch_grd_cls_id']);
        } else {
            if (($filters['grade_id'] ?? 0) > 0) {
                $query->where('sgct.grade_id', (int) $filters['grade_id']);
            }
            if (($filters['class_id'] ?? 0) > 0) {
                $query->where('sgct.class_id', (int) $filters['class_id']);
            }
        }

        if (($filters['admission_no'] ?? '') !== '') {
            $query->where('st.index_no', 'like', '%' . $filters['admission_no'] . '%');
        }
        if (($filters['invoice_no'] ?? '') !== '') {
            $query->where('p.invoice_no', 'like', '%' . $filters['invoice_no'] . '%');
        }
        if (($filters['payment_status'] ?? 'all') === 'paid') {
            $query->whereNotNull('p.id');
        } elseif (($filters['payment_status'] ?? 'all') === 'not_paid') {
            $query->whereNull('p.id');
        }

        return $query
            ->orderBy('sgct.grade_id')
            ->orderBy('sgct.class_id')
            ->orderBy('st.index_no')
            ->orderByDesc('p.id')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) ($row->id ?? 0),
                'std_id' => (int) ($row->std_id ?? 0),
                'admission_no' => (string) ($row->index_no ?? ''),
                'invoice_no' => (string) ($row->invoice_no ?? ''),
                'year' => (int) ($row->report_year ?? 0),
                'include_member_fee' => (string) ($row->is_sds_member_fee ?? '0') === '1',
                'total' => (float) ($row->total ?? 0),
                'paid_date' => (string) ($row->paid_date ?? ''),
                'annual_fee' => (float) ($row->sds_annual_fee ?? 0),
                'member_fee' => (float) ($row->sds_member_fee ?? 0),
                'fullname' => (string) ($row->fullname ?? ''),
                'name_with_initials' => (string) ($row->name_with_initials ?? ''),
                'grade_id' => (int) ($row->grade_id ?? 0),
                'class_id' => (int) ($row->class_id ?? 0),
                'grade' => (string) ($row->grade ?? ''),
                'class' => (string) ($row->class ?? ''),
                'grade_class' => trim(sprintf('%s %s', (string) ($row->grade ?? ''), (string) ($row->class ?? ''))),
                'payment_status' => (string) ($row->payment_status ?? 'not_paid'),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{payment_count:int,total_amount:float}
     */
    private function buildPaymentReportSummary(array $rows): array
    {
        return [
            'payment_count' => count($rows),
            'total_amount' => array_reduce($rows, fn (float $total, array $row): float => $total + (float) ($row['total'] ?? 0), 0.0),
        ];
    }

    /**
     * @return array{payment_count:int,total_amount:float}
     */
    private function emptyPaymentReportSummary(): array
    {
        return [
            'payment_count' => 0,
            'total_amount' => 0.0,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPaymentReportScope(int $schoolGradeClassId): ?array
    {
        if ($schoolGradeClassId <= 0 || !Schema::hasTable('school_grade_class_tbl')) {
            return null;
        }

        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
            'grade_en',
            'grade_si',
            'grade_ta',
            'grade',
        ]);
        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]);
        $gradeSelect = $gradeLabelColumn !== null ? "gt.{$gradeLabelColumn}" : "''";
        $classSelect = $classLabelColumn !== null ? "ct.{$classLabelColumn}" : "''";

        $query = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.year',
                'sgct.grade_id',
                'sgct.class_id',
                DB::raw("{$gradeSelect} as grade"),
                DB::raw("{$classSelect} as class"),
            ])
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId);

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        return [
            'sch_grd_cls_id' => (int) ($row->sch_grd_cls_id ?? 0),
            'year' => (int) ($row->year ?? 0),
            'grade_id' => (int) ($row->grade_id ?? 0),
            'class_id' => (int) ($row->class_id ?? 0),
            'grade' => (string) ($row->grade ?? ''),
            'class' => (string) ($row->class ?? ''),
            'grade_class' => trim(sprintf('%s %s', (string) ($row->grade ?? ''), (string) ($row->class ?? ''))),
        ];
    }

    /**
     * @param  array{year:int, grade_id:int, class_id:int, admission_no:string, invoice_no:string, payment_status:string}  $filters
     * @param  array<string, mixed>|null  $scope
     */
    private function buildPaymentExportHeading(array $filters, ?array $scope): string
    {
        $yearLabel = (int) ($filters['year'] ?? 0) > 0 ? (string) $filters['year'] : 'All';
        $statusLabel = match ((string) ($filters['payment_status'] ?? 'all')) {
            'paid' => 'Paid',
            'not_paid' => 'Not Paid',
            default => 'All',
        };

        if ($scope !== null) {
            $gradeLabel = trim((string) ($scope['grade_class'] ?? ''));
        } else {
            $gradeLabel = '';
            $gradeId = (int) ($filters['grade_id'] ?? 0);
            $classId = (int) ($filters['class_id'] ?? 0);

            if ($gradeId > 0) {
                $gradeLabel = $this->loadGradeLabel($gradeId);
            }

            if ($classId > 0) {
                $classLabel = $this->loadClassLabel($classId);
                $gradeLabel = trim($gradeLabel . $classLabel);
            }

            if ($gradeLabel === '') {
                $gradeLabel = 'All';
            }
        }

        return sprintf('Year - %s, Grade - %s, Status - %s', $yearLabel, $gradeLabel, $statusLabel);
    }

    private function loadSchoolName(string $censusId): string
    {
        if ($censusId === '' || !Schema::hasTable((new SchoolDetail())->getTable())) {
            return 'School';
        }

        $schoolName = SchoolDetail::query()
            ->whereIn('census_id', $this->censusCandidates($censusId))
            ->orderByDesc('census_id')
            ->value('sch_name');

        return trim((string) ($schoolName ?? '')) !== ''
            ? trim((string) $schoolName)
            : 'School';
    }

    private function normalizeExportDate(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $trimmed) === 1
            ? substr($trimmed, 0, 10)
            : $trimmed;
    }

    private function loadGradeLabel(int $gradeId): string
    {
        if ($gradeId <= 0 || !Schema::hasTable('grade_tbl')) {
            return '';
        }

        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
            'grade_en',
            'grade_si',
            'grade_ta',
            'grade',
        ]);

        if ($gradeLabelColumn === null) {
            return '';
        }

        $row = DB::table('grade_tbl')
            ->select(DB::raw($gradeLabelColumn . ' as label'))
            ->where('grade_id', $gradeId)
            ->first();

        return trim((string) ($row->label ?? ''));
    }

    private function loadClassLabel(int $classId): string
    {
        if ($classId <= 0 || !Schema::hasTable('class_tbl')) {
            return '';
        }

        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]);

        if ($classLabelColumn === null) {
            return '';
        }

        $row = DB::table('class_tbl')
            ->select(DB::raw($classLabelColumn . ' as label'))
            ->where('class_id', $classId)
            ->first();

        return trim((string) ($row->label ?? ''));
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

    private function paymentExists(string $indexNo, string $censusId, int $year): bool
    {
        if (!Schema::hasTable('sds_fee_payment_tbl') || !Schema::hasTable('student_tbl')) {
            return false;
        }

        $query = DB::table('sds_fee_payment_tbl as p')
            ->join('student_tbl as st', 'st.index_no', '=', 'p.adm_no')
            ->where('p.adm_no', $indexNo)
            ->where('p.year', $year)
            ->whereIn('st.census_id', $this->censusCandidates($censusId));

        if (Schema::hasColumn('sds_fee_payment_tbl', 'is_deleted')) {
            $query->where('p.is_deleted', 0);
        }

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPaymentById(int $paymentId, string $censusId): ?array
    {
        if ($paymentId <= 0 || !Schema::hasTable('sds_fee_payment_tbl')) {
            return null;
        }

        $query = DB::table('sds_fee_payment_tbl as p')
            ->join('student_tbl as st', 'st.index_no', '=', 'p.adm_no')
            ->leftJoin('sds_annual_fee_type_tbl as f', 'f.year', '=', 'p.year')
            ->select([
                'p.id',
                'p.adm_no',
                'p.invoice_no',
                'p.year',
                'p.is_sds_member_fee',
                'p.total',
                'p.paid_date',
                'f.sds_annual_fee',
                'f.sds_member_fee',
            ])
            ->where('p.id', $paymentId)
            ->whereIn('st.census_id', $this->censusCandidates($censusId));

        if (Schema::hasColumn('sds_fee_payment_tbl', 'is_deleted')) {
            $query->where('p.is_deleted', 0);
        }

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row->id ?? 0),
            'index_no' => (string) ($row->adm_no ?? ''),
            'invoice_no' => (string) ($row->invoice_no ?? ''),
            'year' => (int) ($row->year ?? 0),
            'include_member_fee' => (string) ($row->is_sds_member_fee ?? '0') === '1',
            'total' => (float) ($row->total ?? 0),
            'paid_date' => (string) ($row->paid_date ?? ''),
            'annual_fee' => (float) ($row->sds_annual_fee ?? 0),
            'member_fee' => (float) ($row->sds_member_fee ?? 0),
        ];
    }
}
