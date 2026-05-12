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
            'education_levels' => $this->loadLookupRows('edu_quali_tbl', 'edu_q_id', ['edu_q_name_en', 'edu_q_name_si', 'edu_q_name_ta'], true),
            'professional_levels' => $this->loadLookupRows('prof_quali_tbl', 'prof_q_id', ['prof_q_description_en', 'prof_q_description_si', 'prof_q_description_ta', 'prof_q_name']),
            'designations' => $this->loadLookupRows('designation_tbl', 'desig_id', ['desig_type_en', 'desig_type_si', 'desig_type_ta']),
            'service_grades' => $this->loadLookupRows('service_grade_tbl', 'serv_grd_id', ['serv_grd_desc_en', 'serv_grd_desc_si', 'serv_grd_desc_ta', 'serv_grd_type'], true),
            'sections' => $this->loadSectionRows(),
            'section_roles' => $this->loadLookupRows('section_role_tbl', 'sec_role_id', ['sec_role_name_en', 'sec_role_name_si', 'sec_role_name_ta'], true),
            'staff_types' => $this->loadLookupRows('staff_type_tbl', 'stf_type_id', ['stf_type_en', 'stf_type_si', 'stf_type_ta'], true),
            'staff_statuses' => $this->loadLookupRows('staff_status_tbl', 'stf_status_id', ['stf_status_en', 'stf_status_si', 'stf_status_ta'], true),
            'service_statuses' => $this->loadLookupRows('service_status_tbl', 'service_status_id', ['service_status_en', 'service_status_si', 'service_status_ta']),
            'provinces' => $this->loadLookupRows('province_tbl', 'pro_id', ['pro_name_en', 'pro_name_si', 'pro_name_ta']),
            'zones' => $this->loadLookupRows('edu_zone_tbl', 'zone_id', ['zone_name_en', 'zone_name_si', 'zone_name_ta']),
            'all_schools' => $this->loadSchoolOptions(),
            'subject_mediums' => $this->loadLookupRows('subject_medium_tbl', 'subj_med_id', ['subj_med_type_en', 'subj_med_type_si', 'subj_med_type_ta'], true),
            'appointment_types' => $this->loadLookupRows('appointment_type_tbl', 'app_type_id', ['app_type_en', 'app_type_si', 'app_type_ta']),
            'appointment_subjects' => $this->loadAppointmentSubjectRows(),
            'involved_tasks' => $this->loadLookupRows('involved_task_tbl', 'involved_task_id', ['inv_task_en', 'inv_task_si', 'inv_task_ta'], true),
            'subjects' => $this->loadSubjectRows(),
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

        $errors = array_merge(
            $this->validateDuplicateNic($validated),
            $this->validateStaffLookupValues($validated),
            $this->validateStaffCompositeFields($validated),
        );
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
            'has_photo' => $request->hasFile('profile_photo'),
        ]);

        try {
            $staff = DB::transaction(function () use ($validated, $censusId, $user, $request) {
                Log::info('Staff store transaction opened.', [
                    'census_id' => $censusId,
                ]);

                $staff = new Staff();
                $staff->fill($this->buildStaffData($validated, $censusId, $user));

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

                Log::info('Staff base row saved.', [
                    'stf_id' => $staff->stf_id,
                    'census_id' => $censusId,
                ]);

                $this->storeServiceGradeRecord($staff, $validated, $censusId);
                Log::info('Staff service grade stage completed.', [
                    'stf_id' => $staff->stf_id,
                ]);

                $this->storeServiceStatusRecord($staff, $validated, $censusId);
                Log::info('Staff service status stage completed.', [
                    'stf_id' => $staff->stf_id,
                ]);

                $this->storeInvolvedTaskRecords($staff, $validated, $censusId);
                Log::info('Staff involved tasks stage completed.', [
                    'stf_id' => $staff->stf_id,
                ]);

                $this->storeStaffPhoto($request->file('profile_photo'), (int) $staff->stf_id);
                Log::info('Staff photo stage completed.', [
                    'stf_id' => $staff->stf_id,
                    'has_photo' => $request->hasFile('profile_photo'),
                ]);

                return $staff;
            });

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
    private function buildStaffData(array $validated, string $censusId, mixed $user): array
    {
        $data = [
            'title' => trim((string) $validated['title']),
            'census_id' => $censusId,
            'full_name' => trim((string) $validated['full_name']),
            'name_with_ini' => trim((string) $validated['name_with_ini']),
            'gender_id' => (int) $validated['gender_id'],
            'desig_id' => (int) $validated['desig_id'],
            'user_id' => $this->resolveStaffUserId($user),
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
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $this->toNullableString($validated[$field] ?? null);
            }
        }

        foreach ([
            'civil_status_id',
            'ethnic_group_id',
            'religion_id',
            'edu_q_id',
            'prof_q_id',
            'serv_grd_id',
            'sec_id',
            'sec_role_id',
            'stf_type_id',
            'stf_status_id',
            'service_status_id',
            'subj_med_id',
            'app_type_id',
            'app_subj_id',
            'stf_no',
            'salary_no',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $this->toIntOrNull($validated[$field] ?? null);
            }
        }

        foreach (['dob', 'first_app_dt', 'start_dt_this_sch', 'serv_grd_effective_dt', 'sal_incr_dt'] as $field) {
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
            'edu_q_id' => ['edu_quali_tbl', 'edu_q_id', true],
            'prof_q_id' => ['prof_quali_tbl', 'prof_q_id', false],
            'desig_id' => ['designation_tbl', 'desig_id', false],
            'serv_grd_id' => ['service_grade_tbl', 'serv_grd_id', true],
            'sec_id' => ['section_tbl', 'section_id', false],
            'sec_role_id' => ['section_role_tbl', 'sec_role_id', true],
            'stf_type_id' => ['staff_type_tbl', 'stf_type_id', true],
            'stf_status_id' => ['staff_status_tbl', 'stf_status_id', true],
            'service_status_id' => ['service_status_tbl', 'service_status_id', false],
            'service_status_province_id' => ['province_tbl', 'pro_id', false],
            'service_status_zone_id' => ['edu_zone_tbl', 'zone_id', false],
            'subj_med_id' => ['subject_medium_tbl', 'subj_med_id', true],
            'app_type_id' => ['appointment_type_tbl', 'app_type_id', false],
            'app_subj_id' => ['appointment_subject_tbl', 'app_subj_id', false],
            'main_task_id' => ['involved_task_tbl', 'involved_task_id', true],
            'main_task_section_id' => ['section_tbl', 'section_id', false],
            'main_task_subject_id' => ['subject_tbl', 'subject_id', false],
            'second_task_id' => ['involved_task_tbl', 'involved_task_id', true],
            'second_task_section_id' => ['section_tbl', 'section_id', false],
            'second_task_subject_id' => ['subject_tbl', 'subject_id', false],
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

        $serviceStatusSchoolCensusId = trim((string) ($validated['service_status_school_census_id'] ?? ''));
        if ($serviceStatusSchoolCensusId !== '' && $this->resolveCanonicalSchoolCensusId($serviceStatusSchoolCensusId) === null) {
            $errors['service_status_school_census_id'] = ['Selected attached school is invalid.'];
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
    private function loadSectionRows(): array
    {
        if (!Schema::hasTable('section_tbl')) {
            return [];
        }

        $availableColumns = $this->availableLookupColumns('section_tbl', ['section_name_en', 'section_name_si', 'section_name_ta', 'section_name']);
        if ($availableColumns === []) {
            return [];
        }

        return DB::table('section_tbl')
            ->select('section_id')
            ->selectRaw($this->buildLocalizedLabelSelect('section_tbl', $availableColumns))
            ->orderBy('section_id')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) ($row->section_id ?? 0),
                'label' => trim((string) ($row->label ?? '')) !== '' ? (string) $row->label : (string) ($row->section_id ?? ''),
            ])
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string, section_id: int}>
     */
    private function loadSubjectRows(): array
    {
        if (!Schema::hasTable('subject_tbl')) {
            return [];
        }

        $availableColumns = $this->availableLookupColumns('subject_tbl', ['subject_en', 'subject_si', 'subject_ta']);
        if ($availableColumns === []) {
            return [];
        }

        return DB::table('subject_tbl')
            ->select(['subject_id', 'section_id'])
            ->selectRaw($this->buildLocalizedLabelSelect('subject_tbl', $availableColumns))
            ->orderBy('section_id')
            ->orderBy('subject_id')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) ($row->subject_id ?? 0),
                'label' => trim((string) ($row->label ?? '')) !== '' ? (string) $row->label : (string) ($row->subject_id ?? ''),
                'section_id' => (int) ($row->section_id ?? 0),
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array<int, string>>
     */
    private function validateStaffCompositeFields(array $validated): array
    {
        $errors = [];

        foreach ([
            'main_task' => 'Main task',
            'second_task' => 'Second task',
        ] as $prefix => $label) {
            $taskId = $this->toIntOrNull($validated["{$prefix}_id"] ?? null);
            $sectionId = $this->toIntOrNull($validated["{$prefix}_section_id"] ?? null);
            $subjectId = $this->toIntOrNull($validated["{$prefix}_subject_id"] ?? null);

            if ($taskId === null && $sectionId === null && $subjectId === null) {
                continue;
            }

            if ($taskId === null) {
                $errors["{$prefix}_id"] = ["{$label} is required."];
            }
            if ($sectionId === null) {
                $errors["{$prefix}_section_id"] = ["{$label} section is required."];
            }
            if ($subjectId === null) {
                $errors["{$prefix}_subject_id"] = ["{$label} subject is required."];
            }

            if ($sectionId !== null && $subjectId !== null && !$this->subjectBelongsToSection($subjectId, $sectionId)) {
                $errors["{$prefix}_subject_id"] = ['Selected subject does not belong to the selected section.'];
            }
        }

        $serviceStatusId = $this->toIntOrNull($validated['service_status_id'] ?? null);
        $serviceStatusInstitute = $this->resolveServiceStatusInstitute($validated);
        $serviceStatusEffectiveDate = $this->toNullableString($validated['service_status_effective_date'] ?? null);
        $serviceStatusPeriod = $this->toNullableString($validated['service_status_period'] ?? null);

        $hasServiceStatusDetails = $serviceStatusInstitute !== null || $serviceStatusEffectiveDate !== null || $serviceStatusPeriod !== null;
        if ($hasServiceStatusDetails && $serviceStatusId === null) {
            $errors['service_status_id'] = ['Service status is required when status details are filled.'];
        }
        if ($hasServiceStatusDetails && $serviceStatusInstitute === null) {
            $errors['service_status_institute'] = ['Institute is required when status details are filled.'];
        }
        if ($hasServiceStatusDetails && $serviceStatusEffectiveDate === null) {
            $errors['service_status_effective_date'] = ['Effective date is required when status details are filled.'];
        }
        if ($hasServiceStatusDetails && $serviceStatusPeriod === null) {
            $errors['service_status_period'] = ['Period is required when status details are filled.'];
        }

        $serviceGradeId = $this->toIntOrNull($validated['serv_grd_id'] ?? null);
        $serviceGradeDate = $this->toNullableString($validated['serv_grd_effective_dt'] ?? null);
        if (($serviceGradeId !== null || $serviceGradeDate !== null) && ($serviceGradeId === null || $serviceGradeDate === null)) {
            if ($serviceGradeId === null) {
                $errors['serv_grd_id'] = ['Service grade is required when effective date is filled.'];
            }
            if ($serviceGradeDate === null) {
                $errors['serv_grd_effective_dt'] = ['Service grade effective date is required when service grade is selected.'];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array<int, string>>
     */
    private function validateDuplicateNic(array $validated): array
    {
        $nic = trim((string) ($validated['nic_no'] ?? ''));
        if ($nic === '' || !Schema::hasTable('staff_tbl')) {
            return [];
        }

        $query = Staff::query()->where('nic_no', $nic);
        if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->exists()
            ? ['nic_no' => ['This NIC already exists.']]
            : [];
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeServiceGradeRecord(Staff $staff, array $validated, string $censusId): void
    {
        $serviceGradeId = $this->toIntOrNull($validated['serv_grd_id'] ?? null);
        $effectiveDate = $this->toNullableString($validated['serv_grd_effective_dt'] ?? null);

        if ($serviceGradeId === null || $effectiveDate === null || !Schema::hasTable('staff_service_grade_tbl')) {
            return;
        }

        DB::table('staff_service_grade_tbl')->insert([
            'stf_id' => (int) $staff->stf_id,
            'census_id' => (int) $censusId,
            'serv_grd_id' => $serviceGradeId,
            'effective_date' => $effectiveDate,
            'is_current' => 1,
            'date_added' => now(),
            'date_updated' => now(),
            'is_deleted' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeServiceStatusRecord(Staff $staff, array $validated, string $censusId): void
    {
        $serviceStatusId = $this->toIntOrNull($validated['service_status_id'] ?? null);
        $institute = $this->resolveServiceStatusInstitute($validated);
        $effectiveDate = $this->toNullableString($validated['service_status_effective_date'] ?? null);
        $period = $this->toNullableString($validated['service_status_period'] ?? null);

        if (
            $serviceStatusId === null ||
            $institute === null ||
            $effectiveDate === null ||
            $period === null ||
            !Schema::hasTable('staff_service_status_tbl')
        ) {
            return;
        }

        DB::table('staff_service_status_tbl')->insert([
            'stf_id' => (int) $staff->stf_id,
            'census_id' => $censusId,
            'status_id' => $serviceStatusId,
            'institute' => $institute,
            'effective_date' => $effectiveDate,
            'period' => $period,
            'is_current' => $this->toBoolInt($validated['service_status_is_current'] ?? true),
            'date_added' => now(),
            'date_updated' => now(),
            'is_deleted' => 0,
        ]);
    }

    private function storeStaffPhoto(mixed $photo, int $staffId): void
    {
        if ($staffId <= 0 || !$photo instanceof \Illuminate\Http\UploadedFile) {
            return;
        }

        $targetDir = public_path('uploads/staff');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $extension = strtolower((string) $photo->getClientOriginalExtension());
        if ($extension === '') {
            $extension = 'jpg';
        }

        foreach (glob($targetDir . DIRECTORY_SEPARATOR . $staffId . '.*') ?: [] as $existingFile) {
            if (is_file($existingFile)) {
                @unlink($existingFile);
            }
        }

        $photo->move($targetDir, $staffId . '.' . $extension);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function storeInvolvedTaskRecords(Staff $staff, array $validated, string $censusId): void
    {
        if (!Schema::hasTable('staff_involved_task_tbl')) {
            return;
        }

        foreach ([
            ['main_task', 1],
            ['second_task', 2],
        ] as [$prefix, $taskTypeId]) {
            $taskId = $this->toIntOrNull($validated["{$prefix}_id"] ?? null);
            $sectionId = $this->toIntOrNull($validated["{$prefix}_section_id"] ?? null);
            $subjectId = $this->toIntOrNull($validated["{$prefix}_subject_id"] ?? null);

            if ($taskId === null || $sectionId === null || $subjectId === null) {
                continue;
            }

            DB::table('staff_involved_task_tbl')->insert([
                'stf_id' => (int) $staff->stf_id,
                'census_id' => $censusId,
                'involved_task_type_id' => (int) $taskTypeId,
                'involved_task_id' => $taskId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'date_added' => now(),
                'date_updated' => now(),
                'is_deleted' => 0,
            ]);
        }
    }

    private function resolveStaffUserId(mixed $user): int
    {
        $id = (int) ($user->user_id ?? $user->id ?? 0);

        return max(0, $id);
    }

    private function subjectBelongsToSection(int $subjectId, int $sectionId): bool
    {
        if ($subjectId <= 0 || $sectionId <= 0 || !Schema::hasTable('subject_tbl')) {
            return false;
        }

        return DB::table('subject_tbl')
            ->where('subject_id', $subjectId)
            ->where('section_id', $sectionId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveServiceStatusInstitute(array $validated): ?string
    {
        $direct = $this->toNullableString($validated['service_status_institute'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        $provinceId = $this->toIntOrNull($validated['service_status_province_id'] ?? null);
        if ($provinceId !== null) {
            return $this->lookupLabelById('province_tbl', 'pro_id', $provinceId, ['pro_name_en', 'pro_name_si', 'pro_name_ta']);
        }

        $zoneId = $this->toIntOrNull($validated['service_status_zone_id'] ?? null);
        if ($zoneId !== null) {
            return $this->lookupLabelById('edu_zone_tbl', 'zone_id', $zoneId, ['zone_name_en', 'zone_name_si', 'zone_name_ta']);
        }

        $schoolCensusId = trim((string) ($validated['service_status_school_census_id'] ?? ''));
        if ($schoolCensusId !== '') {
            $canonical = $this->resolveCanonicalSchoolCensusId($schoolCensusId);
            if ($canonical !== null) {
                return SchoolDetail::query()->where('census_id', $canonical)->value('sch_name');
            }
        }

        return $this->toNullableString($validated['service_status_custom_institute'] ?? null);
    }

    private function lookupLabelById(string $table, string $idColumn, int $id, array $labelColumns): ?string
    {
        if ($id <= 0 || !Schema::hasTable($table)) {
            return null;
        }

        $availableColumns = $this->availableLookupColumns($table, $labelColumns);
        if ($availableColumns === []) {
            return null;
        }

        $row = DB::table($table)
            ->select($idColumn)
            ->selectRaw($this->buildLocalizedLabelSelect($table, $availableColumns))
            ->where($idColumn, $id)
            ->first();

        if ($row === null) {
            return null;
        }

        $label = trim((string) ($row->label ?? ''));

        return $label === '' ? null : $label;
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

    private function toBoolInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) $value > 0 ? 1 : 0;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }
}
