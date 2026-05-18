<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Models\SchoolDetail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    use AppliesSchoolScope;

    public function options(): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canAccessPayments($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
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
        $indexNo = trim((string) ($validated['index_no'] ?? ''));

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

    public function store(Request $request): JsonResponse
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
            'invoice_no' => ['required', 'string', 'max:10'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'include_member_fee' => ['sometimes', 'boolean'],
        ])->validate();

        $censusId = $this->resolveTargetSchoolCensusId($user);
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

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2, 4], true)) {
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
