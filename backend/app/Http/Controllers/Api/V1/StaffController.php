<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StaffStoreRequest;
use App\Models\SchoolDetail;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StaffController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        try {
            if (!Schema::hasTable('staff_tbl')) {
                return response()->json([
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            $user = $this->authUser();
            $staffColumns = Schema::getColumnListing('staff_tbl');
            $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);
            $query = Staff::query()
                ->select([
                    'stf_id',
                    'census_id',
                    'name_with_ini',
                    'nic_no',
                    'gender_id',
                    'phone_mobile1',
                    'desig_id',
                    'date_updated',
                ])
                ->with([
                    'school:census_id,sch_name',
                    'designation',
                    'gender',
                ])
                ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                    $builder->where('is_deleted', 0);
                })
                ->when($q !== '', function ($builder) use ($q): void {
                    $builder->where(function ($inner) use ($q): void {
                        $inner
                            ->where('name_with_ini', 'like', "%{$q}%")
                            ->orWhere('nic_no', 'like', "%{$q}%")
                            ->orWhere('phone_mobile1', 'like', "%{$q}%");
                    });
                })
                ->orderBy('census_id')
                ->orderBy('stf_id');

            if (!$this->isAdministrator($user)) {
                $this->applySchoolScope($query->getQuery(), $user, null, $staffSchoolColumn);
            }

            $paginated = $query->paginate($perPage);

            return response()->json([
                'data' => collect($paginated->items())->map(fn ($row): array => [
                    'stf_id' => (int) $row->stf_id,
                    'census_id' => isset($row->census_id) ? (string) $row->census_id : null,
                    'name_with_ini' => (string) ($row->name_with_ini ?? ''),
                    'nic_no' => (string) ($row->nic_no ?? ''),
                    'gender' => $this->localizedGenderLabel($row),
                    'phone_mobile1' => $row->phone_mobile1,
                    'designation' => $this->localizedDesignationLabel($row),
                    'school_name' => $row->school?->sch_name,
                    'last_update' => $row->date_updated,
                ])->all(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to load staff list.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function options(): JsonResponse
    {
        if (!Schema::hasTable('staff_tbl')) {
            return response()->json(['data' => []]);
        }

        $user = $this->authUser();
        $staffColumns = Schema::getColumnListing('staff_tbl');
        $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);

        $query = Staff::query()
            ->select(['stf_id', 'census_id', 'name_with_ini'])
            ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                $builder->where('is_deleted', 0);
            })
            ->orderBy('census_id')
            ->orderBy('name_with_ini')
            ->orderBy('stf_id');

        if (!$this->isAdministrator($user)) {
            $this->applySchoolScope($query->getQuery(), $user, null, $staffSchoolColumn);
        }

        $isAdmin = $this->isAdministrator($user);

        return response()->json([
            'data' => $query->get()->map(fn ($row): array => [
                'stf_id' => (int) $row->stf_id,
                'name_with_ini' => (string) ($row->name_with_ini ?? ''),
            ])->all(),
            'schools' => $isAdmin ? $this->loadSchoolOptions() : [],
            'genders' => $this->loadLookupRows('gender_tbl', 'gender_id', ['gender_name_en', 'gender_name_si', 'gender_name_ta', 'gender_name']),
            'civil_statuses' => $this->loadLookupRows('civil_status_tbl', 'civil_status_id', ['civil_status_type_en', 'civil_status_type_si', 'civil_status_type_ta']),
            'ethnic_groups' => $this->loadLookupRows('ethnic_group_tbl', 'ethnic_group_id', ['ethnic_group_en', 'ethnic_group_si', 'ethnic_group_ta']),
            'religions' => $this->loadLookupRows('religion_tbl', 'religion_id', ['religion_en', 'religion_si', 'religion_ta']),
            'designations' => $this->loadLookupRows('designation_tbl', 'desig_id', ['desig_type_en', 'desig_type_si', 'desig_type_ta']),
            'staff_types' => $this->loadLookupRows('staff_type_tbl', 'stf_type_id', ['stf_type_en', 'stf_type_si', 'stf_type_ta'], true),
            'staff_statuses' => $this->loadLookupRows('staff_status_tbl', 'stf_status_id', ['stf_status_en', 'stf_status_si', 'stf_status_ta'], true),
            'service_statuses' => $this->loadLookupRows('service_status_tbl', 'service_status_id', ['service_status_en', 'service_status_si', 'service_status_ta']),
            'subject_mediums' => $this->loadLookupRows('subject_medium_tbl', 'subj_med_id', ['subj_med_type_en', 'subj_med_type_si', 'subj_med_type_ta'], true),
            'appointment_types' => $this->loadLookupRows('appointment_type_tbl', 'app_type_id', ['app_type_en', 'app_type_si', 'app_type_ta']),
            'appointment_subjects' => $this->loadAppointmentSubjectRows(),
        ]);
    }

    public function store(StaffStoreRequest $request): JsonResponse
    {
        $user = $this->authUser();
        if (!$this->canManageStaff($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validated();
        $censusId = $this->resolveStaffCensusId($user, $validated);

        if ($censusId === null) {
            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? 'Please select a school first.'
                    : 'Unable to determine school census ID.',
                'errors' => ['census_id' => ['Please select a school first.']],
            ], 422);
        }

        $errors = $this->validateStaffLookupValues($validated);
        if ($errors !== []) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        Log::info('Staff store started.', [
            'census_id' => $censusId,
            'nic_no' => $validated['nic_no'] ?? null,
            'name_with_ini' => $validated['name_with_ini'] ?? null,
        ]);

        try {
            $staff = new Staff();
            $staff->fill($this->buildStaffData($validated, $censusId));

            if (Schema::hasColumn('staff_tbl', 'date_added')) {
                $staff->date_added = now();
            }
            if (Schema::hasColumn('staff_tbl', 'date_updated')) {
                $staff->date_updated = now();
            }
            if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
                $staff->is_deleted = 0;
            }

            $staff->save();
            $staff->load(['school:census_id,sch_name', 'designation', 'gender']);

            Log::info('Staff store completed.', [
                'stf_id' => $staff->stf_id,
                'census_id' => $staff->census_id,
            ]);

            return response()->json([
                'message' => 'Staff added successfully.',
                'data' => [
                    'stf_id' => (int) $staff->stf_id,
                    'name_with_ini' => (string) ($staff->name_with_ini ?? ''),
                    'school_name' => $staff->school?->sch_name,
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Staff store failed.', [
                'census_id' => $censusId,
                'nic_no' => $validated['nic_no'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to add staff.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reportSummary(Request $request): JsonResponse
    {
        $year = $request->query('year');
        $month = $request->query('month');
        $year = is_numeric($year) ? (int) $year : null;
        $month = is_numeric($month) ? (int) $month : null;

        try {
            if (!Schema::hasTable('staff_tbl')) {
                return response()->json([
                    'summary' => [
                        'total_staff' => 0,
                        'updated_staff' => 0,
                        'not_updated_staff' => 0,
                    ],
                ]);
            }

            $user = $this->authUser();
            $staffColumns = Schema::getColumnListing('staff_tbl');
            $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);

            $base = Staff::query()
                ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                    $builder->where('is_deleted', 0);
                });

            if (!$this->isAdministrator($user)) {
                $this->applySchoolScope($base->getQuery(), $user, null, $staffSchoolColumn);
            }

            $total = (int) (clone $base)->count();

            $updated = (clone $base)
                ->when($year !== null, function ($builder) use ($year): void {
                    $builder->whereYear('date_updated', $year);
                })
                ->when($month !== null, function ($builder) use ($month): void {
                    $builder->whereMonth('date_updated', $month);
                })
                ->count();

            return response()->json([
                'summary' => [
                    'total_staff' => $total,
                    'updated_staff' => $updated,
                    'not_updated_staff' => max(0, $total - $updated),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to load staff report summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function localizedDesignationLabel(Staff $staff): ?string
    {
        $designation = $staff->designation;
        if ($designation === null) {
            return null;
        }

        $language = $this->resolveRequestLanguage();
        $candidates = match ($language) {
            'si' => ['desig_type_si', 'desig_type_en', 'desig_type_ta', 'desig_type'],
            'ta' => ['desig_type_ta', 'desig_type_en', 'desig_type_si', 'desig_type'],
            default => ['desig_type_en', 'desig_type_si', 'desig_type_ta', 'desig_type'],
        };

        foreach ($candidates as $column) {
            $value = trim((string) ($designation->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function localizedGenderLabel(Staff $staff): ?string
    {
        $gender = $staff->gender;
        if ($gender === null) {
            return null;
        }

        $language = $this->resolveRequestLanguage();
        $candidates = match ($language) {
            'si' => ['gender_name_si', 'gender_name_en', 'gender_name_ta', 'gender_name'],
            'ta' => ['gender_name_ta', 'gender_name_en', 'gender_name_si', 'gender_name'],
            default => ['gender_name_en', 'gender_name_si', 'gender_name_ta', 'gender_name'],
        };

        foreach ($candidates as $column) {
            $value = trim((string) ($gender->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function canManageStaff(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        if ((int) ($user->role_id ?? 0) === 2) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return $roleName === 'principal';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveStaffCensusId(mixed $user, array $validated): ?string
    {
        if ($this->isAdministrator($user)) {
            $requested = trim((string) ($validated['census_id'] ?? ''));

            if ($requested !== '') {
                return $this->resolveCanonicalSchoolCensusId($requested);
            }

            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildStaffData(array $validated, string $censusId): array
    {
        $data = [
            'title' => trim((string) $validated['title']),
            'census_id' => $censusId,
            'full_name' => trim((string) $validated['full_name']),
            'name_with_ini' => trim((string) $validated['name_with_ini']),
            'gender_id' => (int) $validated['gender_id'],
            'desig_id' => (int) $validated['desig_id'],
        ];

        foreach ([
            'nick_name',
            'address1',
            'address2',
            'nic_no',
            'phone_home',
            'phone_mobile1',
            'phone_mobile2',
            'vehicle_no1',
            'vehicle_no2',
            'email',
            'stf_no',
            'salary_no',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $this->toNullableString($validated[$field] ?? null);
            }
        }

        foreach ([
            'civil_status_id',
            'ethnic_group_id',
            'religion_id',
            'stf_type_id',
            'stf_status_id',
            'service_status_id',
            'subj_med_id',
            'app_type_id',
            'app_subj_id',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $this->toIntOrNull($validated[$field] ?? null);
            }
        }

        foreach (['dob', 'first_app_dt', 'start_dt_this_sch', 'serv_grd_effective_dt'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $this->toNullableString($validated[$field] ?? null);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array<int, string>>
     */
    private function validateStaffLookupValues(array $validated): array
    {
        $errors = [];

        $lookupMap = [
            'gender_id' => ['gender_tbl', 'gender_id', false],
            'civil_status_id' => ['civil_status_tbl', 'civil_status_id', false],
            'ethnic_group_id' => ['ethnic_group_tbl', 'ethnic_group_id', true],
            'religion_id' => ['religion_tbl', 'religion_id', true],
            'desig_id' => ['designation_tbl', 'desig_id', false],
            'stf_type_id' => ['staff_type_tbl', 'stf_type_id', true],
            'stf_status_id' => ['staff_status_tbl', 'stf_status_id', true],
            'service_status_id' => ['service_status_tbl', 'service_status_id', false],
            'subj_med_id' => ['subject_medium_tbl', 'subj_med_id', true],
            'app_type_id' => ['appointment_type_tbl', 'app_type_id', false],
            'app_subj_id' => ['appointment_subject_tbl', 'app_subj_id', false],
        ];

        foreach ($lookupMap as $field => [$table, $idColumn, $excludeDeleted]) {
            $value = $this->toIntOrNull($validated[$field] ?? null);
            if ($value === null) {
                continue;
            }

            if (!$this->rowExists($table, $idColumn, $value, (bool) $excludeDeleted)) {
                $errors[$field] = ['Selected value is invalid.'];
            }
        }

        $appTypeId = $this->toIntOrNull($validated['app_type_id'] ?? null);
        $appSubjId = $this->toIntOrNull($validated['app_subj_id'] ?? null);
        if ($appTypeId !== null && $appSubjId !== null && Schema::hasTable('appointment_subject_tbl') && Schema::hasColumn('appointment_subject_tbl', 'app_type_id')) {
            $isMatch = DB::table('appointment_subject_tbl')
                ->where('app_subj_id', $appSubjId)
                ->where('app_type_id', $appTypeId)
                ->exists();

            if (!$isMatch) {
                $errors['app_subj_id'] = ['Appointment subject does not match the selected appointment type.'];
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function loadLookupRows(string $table, string $idColumn, array $labelColumns, bool $excludeDeleted = false): array
    {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $availableColumns = $this->availableLookupColumns($table, $labelColumns);
        if ($availableColumns === []) {
            return [];
        }

        $query = DB::table($table)
            ->select($idColumn)
            ->selectRaw($this->buildLocalizedLabelSelect($table, $availableColumns));

        if ($excludeDeleted && Schema::hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query
            ->orderBy($idColumn)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) ($row->{$idColumn} ?? 0),
                'label' => trim((string) ($row->label ?? '')) !== '' ? (string) $row->label : (string) ($row->{$idColumn} ?? ''),
            ])
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string, app_type_id: int}>
     */
    private function loadAppointmentSubjectRows(): array
    {
        if (!Schema::hasTable('appointment_subject_tbl')) {
            return [];
        }

        $availableColumns = $this->availableLookupColumns('appointment_subject_tbl', ['app_subj_en', 'app_subj_si', 'app_subj_ta']);
        if ($availableColumns === []) {
            return [];
        }

        return DB::table('appointment_subject_tbl')
            ->select(['app_subj_id', 'app_type_id'])
            ->selectRaw($this->buildLocalizedLabelSelect('appointment_subject_tbl', $availableColumns))
            ->orderBy('app_type_id')
            ->orderBy('app_subj_id')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) ($row->app_subj_id ?? 0),
                'label' => trim((string) ($row->label ?? '')) !== '' ? (string) $row->label : (string) ($row->app_subj_id ?? ''),
                'app_type_id' => (int) ($row->app_type_id ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function loadSchoolOptions(): array
    {
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
            ->map(fn ($row): array => [
                'id' => (int) $row->census_id,
                'label' => trim((string) ($row->sch_name ?? '')) !== '' ? (string) $row->sch_name : (string) ($row->census_id ?? ''),
            ])
            ->all();
    }

    private function rowExists(string $table, string $idColumn, int $id, bool $excludeDeleted = false): bool
    {
        if ($id <= 0 || !Schema::hasTable($table)) {
            return false;
        }

        $query = DB::table($table)->where($idColumn, $id);
        if ($excludeDeleted && Schema::hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->exists();
    }

    /**
     * @param  array<int, string>  $labelColumns
     * @return array<int, string>
     */
    private function availableLookupColumns(string $table, array $labelColumns): array
    {
        return array_values(array_filter(
            $labelColumns,
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    private function toNullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
