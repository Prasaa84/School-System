<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StaffStoreRequest;
use App\Models\SchoolDetail;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class StaffController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $schoolCensusId = trim((string) $request->query('school_census_id', ''));
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));
        $user = $this->authUser();
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);

        if ($this->isUnassignedClassTeacher($user)) {
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
                    $this->applyStaffSearchFilter($builder, $q);
                })
                ->orderBy('census_id')
                ->orderBy('stf_id');

            if ($this->isAdministrator($user)) {
                if ($schoolCensusId !== '') {
                    $canonicalSchoolCensusId = $this->resolveCanonicalSchoolCensusId($schoolCensusId);
                    if ($canonicalSchoolCensusId !== null) {
                        $query->where('census_id', $canonicalSchoolCensusId);
                    }
                }
            } else {
                $this->applySchoolScope($query->getQuery(), $user, null, $staffSchoolColumn);
            }

            if ($classTeacherAssignment !== null) {
                $this->applyClassTeacherParallelStaffScope($query, $classTeacherAssignment);
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
        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'data' => [],
                'schools' => [],
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
                'report_grades' => [],
                'report_classes' => [],
                'provinces' => $this->loadLookupRows('province_tbl', 'pro_id', ['pro_name_en', 'pro_name_si', 'pro_name_ta']),
                'zones' => $this->loadLookupRows('edu_zone_tbl', 'zone_id', ['zone_name_en', 'zone_name_si', 'zone_name_ta']),
                'all_schools' => $this->loadSchoolOptions(),
                'subject_mediums' => $this->loadLookupRows('subject_medium_tbl', 'subj_med_id', ['subj_med_type_en', 'subj_med_type_si', 'subj_med_type_ta'], true),
                'appointment_types' => $this->loadLookupRows('appointment_type_tbl', 'app_type_id', ['app_type_en', 'app_type_si', 'app_type_ta']),
                'appointment_subjects' => $this->loadAppointmentSubjectRows(),
                'involved_tasks' => $this->loadLookupRows('involved_task_tbl', 'involved_task_id', ['inv_task_en', 'inv_task_si', 'inv_task_ta'], true),
                'subjects' => $this->loadSubjectRows(),
                'login_roles' => $this->loadLoginRoleRows(),
            ]);
        }
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

        if ($classTeacherAssignment !== null) {
            $this->applyClassTeacherParallelStaffScope($query, $classTeacherAssignment);
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
            'report_grades' => $this->loadStaffReportGradeRows($user),
            'report_classes' => $this->loadStaffReportClassRows($user),
            'provinces' => $this->loadLookupRows('province_tbl', 'pro_id', ['pro_name_en', 'pro_name_si', 'pro_name_ta']),
            'zones' => $this->loadLookupRows('edu_zone_tbl', 'zone_id', ['zone_name_en', 'zone_name_si', 'zone_name_ta']),
            'all_schools' => $this->loadSchoolOptions(),
            'subject_mediums' => $this->loadLookupRows('subject_medium_tbl', 'subj_med_id', ['subj_med_type_en', 'subj_med_type_si', 'subj_med_type_ta'], true),
            'appointment_types' => $this->loadLookupRows('appointment_type_tbl', 'app_type_id', ['app_type_en', 'app_type_si', 'app_type_ta']),
            'appointment_subjects' => $this->loadAppointmentSubjectRows(),
            'involved_tasks' => $this->loadLookupRows('involved_task_tbl', 'involved_task_id', ['inv_task_en', 'inv_task_si', 'inv_task_ta'], true),
            'subjects' => $this->loadSubjectRows(),
            'login_roles' => $this->loadLoginRoleRows(),
        ]);
    }

    public function show(int $staff): JsonResponse
    {
        $user = $this->authUser();
        if (!$this->canManageStaff($user)) {
            Log::warning('Staff show forbidden.', [
                'requested_stf_id' => $staff,
                'user_id' => $user->user_id ?? $user->id ?? null,
            ]);

            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Log::info('Staff show started.', [
            'requested_stf_id' => $staff,
            'user_id' => $user->user_id ?? $user->id ?? null,
        ]);

        $staffRow = $this->findManageableStaff($staff, $user);
        if ($staffRow === null) {
            Log::warning('Staff show target not found.', [
                'requested_stf_id' => $staff,
                'user_id' => $user->user_id ?? $user->id ?? null,
            ]);

            return response()->json(['message' => 'Staff record not found.'], 404);
        }

        $payload = array_merge(
            $this->staffBaseDetail($staffRow),
            $this->loadCurrentServiceGradeDetail((int) $staffRow->stf_id),
            $this->loadCurrentServiceStatusDetail((int) $staffRow->stf_id),
            $this->loadCurrentInvolvedTaskDetail((int) $staffRow->stf_id),
            ['photo_url' => $this->resolveStaffPhotoUrl((int) $staffRow->stf_id)],
        );

        Log::info('Staff show completed.', [
            'stf_id' => (int) $staffRow->stf_id,
            'census_id' => $staffRow->census_id ?? null,
            'has_photo' => !empty($payload['photo_url']),
        ]);

        return response()->json([
            'data' => $payload,
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
            $this->validateStaffLoginFields($validated),
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

        $loginResult = [
            'created' => false,
            'updated' => false,
            'disabled' => false,
            'username' => null,
            'temporary_password' => null,
        ];

        try {
            $staff = DB::transaction(function () use ($validated, $censusId, $request, &$loginResult) {
                Log::info('Staff store transaction opened.', [
                    'census_id' => $censusId,
                ]);

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

                $loginResult = $this->syncStaffLogin($staff, $validated);
                Log::info('Staff login stage completed.', [
                    'stf_id' => $staff->stf_id,
                    'login_created' => $loginResult['created'],
                    'login_updated' => $loginResult['updated'],
                    'login_disabled' => $loginResult['disabled'],
                    'login_username' => $loginResult['username'],
                ]);

                return $staff;
            });

            $staff->load(['school:census_id,sch_name', 'designation', 'gender']);

            Log::info('Staff store completed.', [
                'stf_id' => $staff->stf_id,
                'census_id' => $staff->census_id,
            ]);

            return response()->json([
                'message' => $this->buildStaffSaveMessage('Staff added successfully.', $loginResult),
                'data' => [
                    'stf_id' => (int) $staff->stf_id,
                    'name_with_ini' => (string) ($staff->name_with_ini ?? ''),
                    'school_name' => $staff->school?->sch_name,
                    'login_account' => $loginResult,
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

    public function update(StaffStoreRequest $request, int $staff): JsonResponse
    {
        $user = $this->authUser();
        if (!$this->canManageStaff($user)) {
            Log::warning('Staff update forbidden.', [
                'requested_stf_id' => $staff,
                'user_id' => $user->user_id ?? $user->id ?? null,
            ]);

            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $staffRow = $this->findManageableStaff($staff, $user);
        if ($staffRow === null) {
            Log::warning('Staff update target not found.', [
                'requested_stf_id' => $staff,
                'user_id' => $user->user_id ?? $user->id ?? null,
            ]);

            return response()->json(['message' => 'Staff record not found.'], 404);
        }

        $validated = $request->validated();
        $censusId = $this->resolveStaffCensusIdForUpdate($user, $validated, $staffRow);

        if ($censusId === null) {
            return response()->json([
                'message' => $this->isAdministrator($user)
                    ? 'Please select a school first.'
                    : 'Unable to determine school census ID.',
                'errors' => ['census_id' => ['Please select a school first.']],
            ], 422);
        }

        Log::info('Staff update started.', [
            'stf_id' => (int) $staffRow->stf_id,
            'existing_census_id' => $staffRow->census_id ?? null,
            'target_census_id' => $censusId,
            'nic_no' => $validated['nic_no'] ?? null,
            'has_photo' => $request->hasFile('profile_photo'),
            'user_id' => $user->user_id ?? $user->id ?? null,
        ]);

        $errors = array_merge(
            $this->validateDuplicateNic($validated, (int) $staffRow->stf_id),
            $this->validateStaffLookupValues($validated),
            $this->validateStaffCompositeFields($validated),
            $this->validateStaffLoginFields($validated, (int) $staffRow->stf_id),
        );
        if ($errors !== []) {
            Log::warning('Staff update validation failed.', [
                'stf_id' => (int) $staffRow->stf_id,
                'error_fields' => array_keys($errors),
            ]);

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        try {
            $loginResult = [
                'created' => false,
                'updated' => false,
                'disabled' => false,
                'username' => null,
                'temporary_password' => null,
            ];

            DB::transaction(function () use ($request, $validated, $censusId, $staffRow, &$loginResult): void {
                Log::info('Staff update transaction opened.', [
                    'stf_id' => (int) $staffRow->stf_id,
                    'census_id' => $censusId,
                ]);

                $staffRow->fill($this->buildStaffData($validated, $censusId));

                if (Schema::hasColumn('staff_tbl', 'date_updated')) {
                    $staffRow->date_updated = now();
                }

                $staffRow->save();

                Log::info('Staff update base row saved.', [
                    'stf_id' => (int) $staffRow->stf_id,
                    'census_id' => $staffRow->census_id ?? null,
                ]);

                $this->syncServiceGradeRecord($staffRow, $validated, $censusId);
                Log::info('Staff update service grade synced.', [
                    'stf_id' => (int) $staffRow->stf_id,
                ]);

                $this->syncServiceStatusRecord($staffRow, $validated, $censusId);
                Log::info('Staff update service status synced.', [
                    'stf_id' => (int) $staffRow->stf_id,
                ]);

                $this->syncInvolvedTaskRecords($staffRow, $validated, $censusId);
                Log::info('Staff update involved tasks synced.', [
                    'stf_id' => (int) $staffRow->stf_id,
                ]);

                $this->storeStaffPhoto($request->file('profile_photo'), (int) $staffRow->stf_id);
                Log::info('Staff update photo stage completed.', [
                    'stf_id' => (int) $staffRow->stf_id,
                    'has_photo' => $request->hasFile('profile_photo'),
                ]);

                $loginResult = $this->syncStaffLogin($staffRow, $validated);
                Log::info('Staff update login stage completed.', [
                    'stf_id' => (int) $staffRow->stf_id,
                    'login_created' => $loginResult['created'],
                    'login_updated' => $loginResult['updated'],
                    'login_disabled' => $loginResult['disabled'],
                    'login_username' => $loginResult['username'],
                ]);
            });

            Log::info('Staff update completed.', [
                'stf_id' => (int) $staffRow->stf_id,
                'census_id' => $staffRow->census_id ?? null,
            ]);

            return response()->json([
                'message' => $this->buildStaffSaveMessage('Staff updated successfully.', $loginResult),
                'data' => [
                    'stf_id' => (int) $staffRow->stf_id,
                    'name_with_ini' => (string) ($staffRow->name_with_ini ?? ''),
                    'login_account' => $loginResult,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Staff update failed.', [
                'stf_id' => (int) $staffRow->stf_id,
                'census_id' => $censusId,
                'nic_no' => $validated['nic_no'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to update staff.',
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

    public function report(Request $request): JsonResponse
    {
        try {
            if (!Schema::hasTable('staff_tbl')) {
                return response()->json([
                    'data' => [],
                    'meta' => ['total' => 0],
                ]);
            }

            $user = $this->authUser();
            $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
            if ($this->isUnassignedClassTeacher($user)) {
                return response()->json([
                    'data' => [],
                ]);
            }
            $staffColumns = Schema::getColumnListing('staff_tbl');
            $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);
            $schoolCensusId = trim((string) $request->query('school_census_id', ''));
            $keyword = trim((string) $request->query('q', ''));

            $query = Staff::query()
                ->select([
                    'stf_id',
                    'census_id',
                    'name_with_ini',
                    'nic_no',
                    'gender_id',
                    'phone_mobile1',
                    'desig_id',
                ])
                ->with([
                    'school:census_id,sch_name',
                    'designation',
                    'gender',
                ])
                ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                    $builder->where('is_deleted', 0);
                })
                ->when($keyword !== '', function ($builder) use ($keyword): void {
                    $this->applyStaffSearchFilter($builder, $keyword);
                });

            if ($this->isAdministrator($user)) {
                if ($schoolCensusId !== '') {
                    $canonicalSchoolCensusId = $this->resolveCanonicalSchoolCensusId($schoolCensusId);
                    if ($canonicalSchoolCensusId !== null) {
                        $query->where('census_id', $canonicalSchoolCensusId);
                    }
                }
            } else {
                $this->applySchoolScope($query->getQuery(), $user, null, $staffSchoolColumn);
            }

            if ($classTeacherAssignment !== null) {
                $this->applyClassTeacherParallelStaffScope($query, $classTeacherAssignment);
            }

            $this->applyStaffLookupFilters($query, $request);

            $rows = $query
                ->orderBy('census_id')
                ->orderBy('name_with_ini')
                ->orderBy('stf_id')
                ->get();

            return response()->json([
                'data' => $rows->map(fn ($row): array => [
                    'stf_id' => (int) $row->stf_id,
                    'census_id' => isset($row->census_id) ? (string) $row->census_id : null,
                    'name_with_ini' => (string) ($row->name_with_ini ?? ''),
                    'nic_no' => (string) ($row->nic_no ?? ''),
                    'gender' => $this->localizedGenderLabel($row),
                    'phone_mobile1' => $row->phone_mobile1,
                    'designation' => $this->localizedDesignationLabel($row),
                    'school_name' => $row->school?->sch_name,
                ])->all(),
                'meta' => [
                    'total' => $rows->count(),
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Unable to load staff report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function applyStaffLookupFilters($query, Request $request): void
    {
        $filters = [
            'gender_id',
            'civil_status_id',
            'ethnic_group_id',
            'religion_id',
            'edu_q_id',
            'prof_q_id',
            'desig_id',
            'serv_grd_id',
            'sec_id',
            'sec_role_id',
            'stf_type_id',
            'stf_status_id',
            'service_status_id',
            'subj_med_id',
            'app_type_id',
            'app_subj_id',
        ];

        foreach ($filters as $column) {
            $value = (int) $request->query($column, 0);
            if ($value > 0 && Schema::hasColumn('staff_tbl', $column)) {
                $query->where($column, $value);
            }
        }

        $this->applyStaffGradeClassFilters($query, $request);
    }

    private function applyStaffSearchFilter($query, string $keyword): void
    {
        $trimmedKeyword = trim($keyword);
        if ($trimmedKeyword === '') {
            return;
        }

        $normalizedKeyword = preg_replace('/[^A-Za-z0-9]/', '', $trimmedKeyword) ?? '';

        $query->where(function ($inner) use ($trimmedKeyword, $normalizedKeyword): void {
            $inner->where('name_with_ini', 'like', "%{$trimmedKeyword}%");

            if (Schema::hasColumn('staff_tbl', 'full_name')) {
                $inner->orWhere('full_name', 'like', "%{$trimmedKeyword}%");
            }

            $inner
                ->orWhere('nic_no', 'like', "%{$trimmedKeyword}%")
                ->orWhere('phone_mobile1', 'like', "%{$trimmedKeyword}%");

            if ($normalizedKeyword !== '' && Schema::hasColumn('staff_tbl', 'nic_no')) {
                $inner->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(nic_no, ' ', ''), '-', ''), '/', ''), '.', '') LIKE ?",
                    ["%{$normalizedKeyword}%"]
                );
            }
        });
    }

    private function applyStaffGradeClassFilters($query, Request $request): void
    {
        $gradeId = (int) $request->query('grade_id', 0);
        $classId = (int) $request->query('class_id', 0);

        if ($gradeId <= 0 && $classId <= 0) {
            return;
        }

        $user = $this->authUser();
        $schoolCensusId = trim((string) $request->query('school_census_id', ''));
        $targetSchoolCensusId = $this->isAdministrator($user)
            ? ($schoolCensusId !== '' ? $this->resolveCanonicalSchoolCensusId($schoolCensusId) : null)
            : $this->resolveEffectiveSchoolCensusId($user);
        $currentGradeYear = $this->resolveLatestScopedYear('school_grade_tbl', 'sgt', $user, $targetSchoolCensusId);
        $currentClassYear = $this->resolveLatestScopedYear('school_grade_class_tbl', 'sgct', $user, $targetSchoolCensusId);

        $query->where(function ($outer) use ($gradeId, $classId, $targetSchoolCensusId, $currentGradeYear, $currentClassYear): void {
            $hasCondition = false;
            $includeGradeLevelAssignments = false;

            if ($includeGradeLevelAssignments && Schema::hasTable('school_grade_tbl') && Schema::hasColumn('school_grade_tbl', 'stf_id')) {
                $hasCondition = true;
                $outer->whereExists(function ($subQuery) use ($gradeId, $targetSchoolCensusId, $currentGradeYear): void {
                    $subQuery
                        ->select(DB::raw('1'))
                        ->from('school_grade_tbl as sgt')
                        ->whereColumn('sgt.stf_id', 'staff_tbl.stf_id')
                        ->where('sgt.grade_id', $gradeId);

                    if ($currentGradeYear !== null && Schema::hasColumn('school_grade_tbl', 'year')) {
                        $subQuery->where('sgt.year', $currentGradeYear);
                    }

                    if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
                        $subQuery->where('sgt.is_deleted', 0);
                    }

                    if ($targetSchoolCensusId !== null) {
                        $subQuery->whereIn('sgt.census_id', $this->censusCandidates($targetSchoolCensusId));
                    }
                });
            }

            if (Schema::hasTable('school_grade_class_tbl') && Schema::hasColumn('school_grade_class_tbl', 'stf_id')) {
                $method = $hasCondition ? 'orWhereExists' : 'whereExists';

                $outer->{$method}(function ($subQuery) use ($gradeId, $classId, $targetSchoolCensusId, $currentClassYear): void {
                    $subQuery
                        ->select(DB::raw('1'))
                        ->from('school_grade_class_tbl as sgct')
                        ->whereColumn('sgct.stf_id', 'staff_tbl.stf_id');

                    if ($gradeId > 0) {
                        $subQuery->where('sgct.grade_id', $gradeId);
                    }

                    if ($classId > 0) {
                        $subQuery->where('sgct.class_id', $classId);
                    }

                    if ($currentClassYear !== null && Schema::hasColumn('school_grade_class_tbl', 'year')) {
                        $subQuery->where('sgct.year', $currentClassYear);
                    }

                    if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                        $subQuery->where('sgct.is_deleted', 0);
                    }

                    if ($targetSchoolCensusId !== null) {
                        $subQuery->whereIn('sgct.census_id', $this->censusCandidates($targetSchoolCensusId));
                    }
                });
            }
        });
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function loadStaffReportGradeRows(?User $user): array
    {
        if (!Schema::hasTable('school_grade_tbl') || !Schema::hasTable('grade_tbl')) {
            return [];
        }

        $currentYear = $this->resolveLatestScopedYear('school_grade_tbl', 'sgt', $user);
        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', ['grade_en', 'grade_si', 'grade_ta']);
        if ($gradeLabelColumn === null) {
            return [];
        }

        $query = DB::table('school_grade_tbl as sgt')
            ->join('grade_tbl as gt', 'sgt.grade_id', '=', 'gt.grade_id')
            ->select(['sgt.grade_id'])
            ->selectRaw("gt.{$gradeLabelColumn} as grade")
            ->distinct()
            ->orderBy('sgt.grade_id');

        if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
            $query->where('sgt.is_deleted', 0);
        }

        if ($currentYear !== null && Schema::hasColumn('school_grade_tbl', 'year')) {
            $query->where('sgt.year', $currentYear);
        }

        $this->applySchoolScope($query, $user, 'sgt', 'census_id');

        $rows = $query->get()->map(fn ($row): array => [
            'id' => (int) ($row->grade_id ?? 0),
            'label' => trim((string) ($row->grade ?? '')) !== '' ? (string) ($row->grade ?? '') : (string) ($row->grade_id ?? ''),
        ])->filter(fn (array $row): bool => $row['id'] > 0)->values()->all();

        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        if ($classTeacherAssignment === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => (int) ($row['id'] ?? 0) === $classTeacherAssignment['grade_id']
        ));
    }

    /**
     * @return array<int, array{id: int, label: string, grade_id: int}>
     */
    private function loadStaffReportClassRows(?User $user): array
    {
        if (!Schema::hasTable('school_grade_class_tbl') || !Schema::hasTable('grade_tbl') || !Schema::hasTable('class_tbl')) {
            return [];
        }

        $currentYear = $this->resolveLatestScopedYear('school_grade_class_tbl', 'sgct', $user);
        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', ['grade_en', 'grade_si', 'grade_ta']);
        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', ['class_en', 'class_si', 'class_ta', 'class']);
        if ($gradeLabelColumn === null || $classLabelColumn === null) {
            return [];
        }

        $query = DB::table('school_grade_class_tbl as sgct')
            ->join('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->join('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select(['sgct.class_id', 'sgct.grade_id'])
            ->selectRaw("gt.{$gradeLabelColumn} as grade")
            ->selectRaw("ct.{$classLabelColumn} as class")
            ->distinct()
            ->orderBy('sgct.grade_id')
            ->orderBy('sgct.class_id');

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        if ($currentYear !== null && Schema::hasColumn('school_grade_class_tbl', 'year')) {
            $query->where('sgct.year', $currentYear);
        }

        $this->applySchoolScope($query, $user, 'sgct', 'census_id');

        $rows = $query->get()->map(fn ($row): array => [
            'id' => (int) ($row->class_id ?? 0),
            'grade_id' => (int) ($row->grade_id ?? 0),
            'label' => trim((string) ($row->grade ?? '')) !== '' || trim((string) ($row->class ?? '')) !== ''
                ? trim(sprintf('%s - %s', (string) ($row->grade ?? ''), (string) ($row->class ?? '')), ' -')
                : (string) ($row->class_id ?? ''),
        ])->filter(fn (array $row): bool => $row['id'] > 0 && $row['grade_id'] > 0)->values()->all();

        $classTeacherAssignment = $this->resolveClassTeacherAssignment($user);
        if ($classTeacherAssignment === null) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => (int) ($row['grade_id'] ?? 0) === $classTeacherAssignment['grade_id']
        ));
    }

    private function resolveLatestScopedYear(string $table, string $alias, ?User $user, ?string $targetSchoolCensusId = null): ?int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'year')) {
            return null;
        }

        $query = DB::table("{$table} as {$alias}");

        if (Schema::hasColumn($table, 'is_deleted')) {
            $query->where("{$alias}.is_deleted", 0);
        }

        if ($targetSchoolCensusId !== null && Schema::hasColumn($table, 'census_id')) {
            $query->whereIn("{$alias}.census_id", $this->censusCandidates($targetSchoolCensusId));
        } else {
            $this->applySchoolScope($query, $user, $alias, 'census_id');
        }

        $year = $query->max("{$alias}.year");

        return is_numeric($year) ? (int) $year : null;
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
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     */
    private function applyClassTeacherParallelStaffScope($query, array $assignment): void
    {
        $staffIds = $this->parallelClassTeacherStaffIds($assignment);
        if ($staffIds === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('stf_id', $staffIds);
    }

    /**
     * @param  array{sch_grd_cls_id:int, grade_id:int, class_id:int, year:int, census_id:string, stf_id:int}  $assignment
     * @return array<int, int>
     */
    private function parallelClassTeacherStaffIds(array $assignment): array
    {
        if (!Schema::hasTable('school_grade_class_tbl')) {
            return [];
        }

        $query = DB::table('school_grade_class_tbl as sgct')
            ->where('sgct.grade_id', $assignment['grade_id'])
            ->where('sgct.year', $assignment['year'])
            ->whereIn('sgct.census_id', $this->censusCandidates($assignment['census_id']))
            ->where('sgct.stf_id', '>', 0);

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        return $query->pluck('sgct.stf_id')
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
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
     * @return array<int, array{id: int, label: string}>
     */
    private function loadLoginRoleRows(): array
    {
        if (!Schema::hasTable('user_role_tbl')) {
            return [];
        }

        return DB::table('user_role_tbl')
            ->select(['role_id', 'role_name'])
            ->whereNotIn('role_id', [1, 7])
            ->orderBy('role_id')
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) ($row->role_id ?? 0),
                'label' => trim((string) ($row->role_name ?? '')) !== '' ? (string) ($row->role_name ?? '') : (string) ($row->role_id ?? ''),
            ])
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->values()
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
            'main_task',
            'second_task',
        ] as $prefix) {
            $taskId = $this->toIntOrNull($validated["{$prefix}_id"] ?? null);
            $sectionId = $this->toIntOrNull($validated["{$prefix}_section_id"] ?? null);
            $subjectId = $this->toIntOrNull($validated["{$prefix}_subject_id"] ?? null);

            if ($taskId === null && $sectionId === null && $subjectId === null) {
                continue;
            }

            if ($sectionId !== null && $subjectId !== null && !$this->subjectBelongsToSection($subjectId, $sectionId)) {
                $errors["{$prefix}_subject_id"] = ['Selected subject does not belong to the selected section.'];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array<int, string>>
     */
    private function validateDuplicateNic(array $validated, ?int $ignoreStaffId = null): array
    {
        $nic = trim((string) ($validated['nic_no'] ?? ''));
        if ($nic === '' || !Schema::hasTable('staff_tbl')) {
            return [];
        }

        if ($ignoreStaffId !== null && $ignoreStaffId > 0) {
            $currentStaff = Staff::query()->find($ignoreStaffId);
            if ($currentStaff !== null) {
                $currentNic = trim((string) ($currentStaff->nic_no ?? ''));
                if ($currentNic !== '' && strcasecmp($currentNic, $nic) === 0) {
                    return [];
                }
            }
        }

        $query = Staff::query()->where('nic_no', $nic);
        if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }
        if ($ignoreStaffId !== null && $ignoreStaffId > 0) {
            $query->where('stf_id', '!=', $ignoreStaffId);
        }

        return $query->exists()
            ? ['nic_no' => ['This NIC already exists.']]
            : [];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array<int, string>>
     */
    private function validateStaffLoginFields(array $validated, ?int $staffId = null): array
    {
        $errors = [];

        if (!$this->toBool($validated['create_user_login'] ?? false)) {
            return $errors;
        }

        $nic = preg_replace('/[^A-Za-z0-9]/', '', trim((string) ($validated['nic_no'] ?? '')));
        if ($nic === '') {
            $errors['nic_no'] = ['NIC is required when user login is enabled.'];
        }

        $loginRoleId = $this->toIntOrNull($validated['login_role_id'] ?? null);
        if ($loginRoleId === null) {
            $errors['login_role_id'] = ['User role is required when user login is enabled.'];
            return $errors;
        }

        if (!Schema::hasTable('user_role_tbl')) {
            $errors['login_role_id'] = ['User roles table is unavailable.'];
            return $errors;
        }

        $isAllowedRole = DB::table('user_role_tbl')
            ->where('role_id', $loginRoleId)
            ->whereNotIn('role_id', [1, 7])
            ->exists();

        if (!$isAllowedRole) {
            $errors['login_role_id'] = ['Selected user role is invalid for staff login.'];
        }

        if ($staffId !== null && $loginRoleId !== null && $this->staffAlreadyHasLoginRole($staffId, $loginRoleId)) {
            $roleName = $this->resolveUserRoleName($loginRoleId);
            $errors['login_role_id'] = [sprintf(
                'This staff member already has a%s login.',
                $roleName !== '' ? ' ' . $roleName : ' user role'
            )];
        }

        return $errors;
    }

    private function staffAlreadyHasLoginRole(int $staffId, int $roleId): bool
    {
        if ($staffId <= 0 || $roleId <= 0 || !Schema::hasTable('user_tbl')) {
            return false;
        }

        $userIds = [];

        if (Schema::hasTable('staff_user_tbl')) {
            $query = DB::table('staff_user_tbl')->where('stf_id', $staffId);
            if (Schema::hasColumn('staff_user_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $userIds = $query->pluck('user_id')
                ->map(fn ($value): int => (int) $value)
                ->filter(fn (int $value): bool => $value > 0)
                ->values()
                ->all();
        }

        if ($userIds === [] && Schema::hasTable('staff_tbl')) {
            $legacyUserId = DB::table('staff_tbl')
                ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), fn ($query) => $query->where('is_deleted', 0))
                ->where('stf_id', $staffId)
                ->value('user_id');

            if (is_numeric($legacyUserId) && (int) $legacyUserId > 0) {
                $userIds[] = (int) $legacyUserId;
            }
        }

        if ($userIds === []) {
            return false;
        }

        return User::query()
            ->whereIn('user_id', $userIds)
            ->where('role_id', $roleId)
            ->when(Schema::hasColumn('user_tbl', 'is_deleted'), fn ($query) => $query->where('is_deleted', 0))
            ->exists();
    }

    private function resolveUserRoleName(int $roleId): string
    {
        if ($roleId <= 0 || !Schema::hasTable('user_role_tbl')) {
            return '';
        }

        return trim((string) DB::table('user_role_tbl')
            ->where('role_id', $roleId)
            ->value('role_name'));
    }

    private function findManageableStaff(int $staffId, mixed $user): ?Staff
    {
        if ($staffId <= 0 || !Schema::hasTable('staff_tbl')) {
            return null;
        }

        $staffColumns = Schema::getColumnListing('staff_tbl');
        $staffSchoolColumn = $this->resolveSchoolColumn($staffColumns);

        $query = Staff::query()->where('stf_id', $staffId);
        if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if (!$this->isAdministrator($user)) {
            $this->applySchoolScope($query->getQuery(), $user, null, $staffSchoolColumn);
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function staffBaseDetail(Staff $staff): array
    {
        $linkedLogin = $this->loadLinkedLoginDetail($staff);
        $linkedLogins = $this->loadLinkedLogins($staff);

        return [
            'stf_id' => (int) $staff->stf_id,
            'title' => (string) ($staff->title ?? ''),
            'census_id' => isset($staff->census_id) ? (string) $staff->census_id : null,
            'full_name' => (string) ($staff->full_name ?? ''),
            'name_with_ini' => (string) ($staff->name_with_ini ?? ''),
            'nick_name' => (string) ($staff->nick_name ?? ''),
            'nic_no' => (string) ($staff->nic_no ?? ''),
            'dob' => $this->formatDateValue($staff->dob ?? null),
            'gender_id' => $this->toIntOrNull($staff->gender_id ?? null),
            'civil_status_id' => $this->toIntOrNull($staff->civil_status_id ?? null),
            'ethnic_group_id' => $this->toIntOrNull($staff->ethnic_group_id ?? null),
            'religion_id' => $this->toIntOrNull($staff->religion_id ?? null),
            'phone_home' => (string) ($staff->phone_home ?? ''),
            'phone_mobile1' => (string) ($staff->phone_mobile1 ?? ''),
            'phone_mobile2' => (string) ($staff->phone_mobile2 ?? ''),
            'address1' => (string) ($staff->address1 ?? ''),
            'address2' => (string) ($staff->address2 ?? ''),
            'email' => (string) ($staff->email ?? ''),
            'vehicle_no1' => (string) ($staff->vehicle_no1 ?? ''),
            'vehicle_no2' => (string) ($staff->vehicle_no2 ?? ''),
            'edu_q_id' => $this->toIntOrNull($staff->edu_q_id ?? null),
            'prof_q_id' => $this->toIntOrNull($staff->prof_q_id ?? null),
            'desig_id' => $this->toIntOrNull($staff->desig_id ?? null),
            'serv_grd_id' => $this->toIntOrNull($staff->serv_grd_id ?? null),
            'sec_id' => $this->toIntOrNull($staff->sec_id ?? null),
            'sec_role_id' => $this->toIntOrNull($staff->sec_role_id ?? null),
            'stf_type_id' => $this->toIntOrNull($staff->stf_type_id ?? null),
            'stf_status_id' => $this->toIntOrNull($staff->stf_status_id ?? null),
            'service_status_id' => $this->toIntOrNull($staff->service_status_id ?? null),
            'subj_med_id' => $this->toIntOrNull($staff->subj_med_id ?? null),
            'app_type_id' => $this->toIntOrNull($staff->app_type_id ?? null),
            'app_subj_id' => $this->toIntOrNull($staff->app_subj_id ?? null),
            'first_app_dt' => $this->formatDateValue($staff->first_app_dt ?? null),
            'start_dt_this_sch' => $this->formatDateValue($staff->start_dt_this_sch ?? null),
            'serv_grd_effective_dt' => $this->formatDateValue($staff->serv_grd_effective_dt ?? null),
            'sal_incr_dt' => $this->formatDateValue($staff->sal_incr_dt ?? null),
            'stf_no' => $this->toIntOrNull($staff->stf_no ?? null),
            'salary_no' => $this->toIntOrNull($staff->salary_no ?? null),
            'create_user_login' => false,
            'login_role_id' => null,
            'login_username' => $linkedLogin['login_username'],
            'linked_logins' => $linkedLogins,
        ];
    }

    private function resolveStaffCensusIdForUpdate(mixed $user, array $validated, Staff $staff): ?string
    {
        if ($this->isAdministrator($user)) {
            $requested = trim((string) ($validated['census_id'] ?? ''));

            if ($requested !== '') {
                return $this->resolveCanonicalSchoolCensusId($requested);
            }
        }

        return isset($staff->census_id) ? (string) $staff->census_id : null;
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
    private function syncServiceGradeRecord(Staff $staff, array $validated, string $censusId): void
    {
        $this->clearStaffRelatedRecords('staff_service_grade_tbl', (int) $staff->stf_id);
        $this->storeServiceGradeRecord($staff, $validated, $censusId);
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncServiceStatusRecord(Staff $staff, array $validated, string $censusId): void
    {
        $this->clearStaffRelatedRecords('staff_service_status_tbl', (int) $staff->stf_id);
        $this->storeServiceStatusRecord($staff, $validated, $censusId);
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncInvolvedTaskRecords(Staff $staff, array $validated, string $censusId): void
    {
        $this->clearStaffRelatedRecords('staff_involved_task_tbl', (int) $staff->stf_id);
        $this->storeInvolvedTaskRecords($staff, $validated, $censusId);
    }

    private function clearStaffRelatedRecords(string $table, int $staffId): void
    {
        if ($staffId <= 0 || !Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)->where('stf_id', $staffId);

        if (Schema::hasColumn($table, 'is_deleted')) {
            $payload = ['is_deleted' => 1];
            if (Schema::hasColumn($table, 'is_current')) {
                $payload['is_current'] = 0;
            }
            if (Schema::hasColumn($table, 'date_updated')) {
                $payload['date_updated'] = now();
            }
            $query->update($payload);
            return;
        }

        $query->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCurrentServiceGradeDetail(int $staffId): array
    {
        if ($staffId <= 0 || !Schema::hasTable('staff_service_grade_tbl')) {
            return ['serv_grd_id' => null, 'serv_grd_effective_dt' => ''];
        }

        $query = DB::table('staff_service_grade_tbl')->where('stf_id', $staffId);
        if (Schema::hasColumn('staff_service_grade_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }
        if (Schema::hasColumn('staff_service_grade_tbl', 'is_current')) {
            $query->orderByDesc('is_current');
        }
        if (Schema::hasColumn('staff_service_grade_tbl', 'date_updated')) {
            $query->orderByDesc('date_updated');
        }
        if (Schema::hasColumn('staff_service_grade_tbl', 'date_added')) {
            $query->orderByDesc('date_added');
        }

        $row = $query->first();

        return [
            'serv_grd_id' => $this->toIntOrNull($row->serv_grd_id ?? null),
            'serv_grd_effective_dt' => $this->formatDateValue($row->effective_date ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCurrentServiceStatusDetail(int $staffId): array
    {
        if ($staffId <= 0 || !Schema::hasTable('staff_service_status_tbl')) {
            return [
                'service_status_id' => null,
                'service_status_custom_institute' => '',
                'service_status_effective_date' => '',
                'service_status_period' => '',
                'service_status_is_current' => true,
            ];
        }

        $query = DB::table('staff_service_status_tbl')->where('stf_id', $staffId);
        if (Schema::hasColumn('staff_service_status_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }
        if (Schema::hasColumn('staff_service_status_tbl', 'is_current')) {
            $query->orderByDesc('is_current');
        }
        if (Schema::hasColumn('staff_service_status_tbl', 'date_updated')) {
            $query->orderByDesc('date_updated');
        }
        if (Schema::hasColumn('staff_service_status_tbl', 'date_added')) {
            $query->orderByDesc('date_added');
        }

        $row = $query->first();

        return [
            'service_status_id' => $this->toIntOrNull($row->status_id ?? null),
            'service_status_custom_institute' => (string) ($row->institute ?? ''),
            'service_status_effective_date' => $this->formatDateValue($row->effective_date ?? null),
            'service_status_period' => (string) ($row->period ?? ''),
            'service_status_is_current' => $this->toBool($row->is_current ?? 1),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadCurrentInvolvedTaskDetail(int $staffId): array
    {
        $detail = [
            'main_task_id' => null,
            'main_task_section_id' => null,
            'main_task_subject_id' => null,
            'second_task_id' => null,
            'second_task_section_id' => null,
            'second_task_subject_id' => null,
        ];

        if ($staffId <= 0 || !Schema::hasTable('staff_involved_task_tbl')) {
            return $detail;
        }

        $query = DB::table('staff_involved_task_tbl')->where('stf_id', $staffId);
        if (Schema::hasColumn('staff_involved_task_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $query->orderBy('involved_task_type_id');
        if (Schema::hasColumn('staff_involved_task_tbl', 'date_updated')) {
            $query->orderByDesc('date_updated');
        }

        $rows = $query->get();
        foreach ($rows as $row) {
            $prefix = (int) ($row->involved_task_type_id ?? 0) === 2 ? 'second_task' : 'main_task';
            $detail["{$prefix}_id"] = $this->toIntOrNull($row->involved_task_id ?? null);
            $detail["{$prefix}_section_id"] = $this->toIntOrNull($row->section_id ?? null);
            $detail["{$prefix}_subject_id"] = $this->toIntOrNull($row->subject_id ?? null);
        }

        return $detail;
    }

    private function resolveStaffPhotoUrl(int $staffId): ?string
    {
        if ($staffId <= 0) {
            return null;
        }

        $matches = glob(public_path('uploads/staff/' . $staffId . '.*')) ?: [];
        $path = $matches[0] ?? null;

        if ($path === null || !is_file($path)) {
            return null;
        }

        $version = @filemtime($path);

        return url('/uploads/staff/' . basename($path)) . ($version ? ('?v=' . $version) : '');
    }

    private function formatDateValue(mixed $value): string
    {
        $text = trim((string) $value);

        return $text === '' ? '' : substr($text, 0, 10);
    }

    /**
     * @return array{create_user_login: bool, login_role_id: int|null, login_username: string}
     */
    private function loadLinkedLoginDetail(Staff $staff): array
    {
        $linkedLogins = $this->loadLinkedLogins($staff);
        $primaryLogin = $linkedLogins[0] ?? null;
        if ($primaryLogin === null) {
            return [
                'create_user_login' => false,
                'login_role_id' => null,
                'login_username' => '',
            ];
        }

        return [
            'create_user_login' => false,
            'login_role_id' => $this->toIntOrNull($primaryLogin['role_id'] ?? null),
            'login_username' => (string) ($primaryLogin['username'] ?? ''),
        ];
    }

    /**
     * @return array<int, array{user_id:int, username:string, role_id:int|null, role_name:string|null, is_enabled:bool}>
     */
    private function loadLinkedLogins(Staff $staff): array
    {
        if (!Schema::hasTable('user_tbl')) {
            return [];
        }

        $userIds = [];

        if (Schema::hasTable('staff_user_tbl')) {
            $query = DB::table('staff_user_tbl')->where('stf_id', (int) $staff->stf_id);
            if (Schema::hasColumn('staff_user_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $userIds = $query->pluck('user_id')->map(fn ($value): int => (int) $value)->filter(fn (int $value): bool => $value > 0)->values()->all();
        }

        $legacyUserId = $this->toIntOrNull($staff->user_id ?? null);
        if ($legacyUserId !== null && !in_array($legacyUserId, $userIds, true)) {
            $userIds[] = $legacyUserId;
        }

        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->from('user_tbl as u')
            ->leftJoin('user_role_tbl as ur', 'u.role_id', '=', 'ur.role_id')
            ->select(['u.user_id', 'u.username', 'u.role_id', 'u.status_id', 'u.is_deleted', 'ur.role_name'])
            ->whereIn('u.user_id', $userIds)
            ->orderBy('u.username')
            ->get()
            ->map(function (object $row): array {
                return [
                    'user_id' => (int) $row->user_id,
                    'username' => (string) ($row->username ?? ''),
                    'role_id' => $this->toIntOrNull($row->role_id ?? null),
                    'role_name' => is_string($row->role_name ?? null) ? $row->role_name : null,
                    'is_enabled' => ((int) ($row->status_id ?? 0) === 1) && ((int) ($row->is_deleted ?? 0) === 0),
                ];
            })
            ->values()
            ->all();
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

    /**
     * @param  array<string, mixed>  $validated
     * @return array{created: bool, updated: bool, disabled: bool, username: string|null, temporary_password: string|null}
     */
    private function syncStaffLogin(Staff $staff, array $validated): array
    {
        $result = [
            'created' => false,
            'updated' => false,
            'disabled' => false,
            'username' => null,
            'temporary_password' => null,
        ];

        if (!Schema::hasTable('user_tbl')) {
            return $result;
        }

        $shouldEnableLogin = $this->toBool($validated['create_user_login'] ?? false);
        $loginRoleId = $this->toIntOrNull($validated['login_role_id'] ?? null);

        if (!$shouldEnableLogin) {
            return $result;
        }

        $username = $this->generateUniqueStaffUsername($staff, $validated);
        $temporaryPassword = Str::random(10);

        $user = new User();
        $user->role_id = $loginRoleId;
        $user->username = $username;
        $user->password = Hash::make($temporaryPassword);
        if (Schema::hasColumn('user_tbl', 'census_id')) {
            $user->census_id = $staff->census_id;
        }
        $user->grade_id = 0;
        $user->class_id = 0;
        $user->status_id = 1;
        if (Schema::hasColumn('user_tbl', 'date_added')) {
            $user->date_added = now();
        }
        if (Schema::hasColumn('user_tbl', 'date_updated')) {
            $user->date_updated = now();
        }
        if (Schema::hasColumn('user_tbl', 'is_deleted')) {
            $user->is_deleted = 0;
        }
        $user->save();

        $this->attachUserToStaff($user, $staff);

        $result['created'] = true;
        $result['username'] = $username;
        $result['temporary_password'] = $temporaryPassword;

        return $result;
    }

    private function attachUserToStaff(User $user, Staff $staff): void
    {
        if (Schema::hasTable('staff_user_tbl')) {
            $query = DB::table('staff_user_tbl')
                ->where('stf_id', (int) $staff->stf_id)
                ->where('user_id', (int) $user->user_id);

            $existing = $query->first();
            if ($existing === null) {
                DB::table('staff_user_tbl')->insert([
                    'stf_id' => (int) $staff->stf_id,
                    'user_id' => (int) $user->user_id,
                    'date_added' => now(),
                    'date_updated' => now(),
                    'is_deleted' => 0,
                ]);
            } elseif (Schema::hasColumn('staff_user_tbl', 'is_deleted') && (int) ($existing->is_deleted ?? 0) === 1) {
                DB::table('staff_user_tbl')
                    ->where('stf_user_id', (int) $existing->stf_user_id)
                    ->update([
                        'is_deleted' => 0,
                        'date_updated' => now(),
                    ]);
            }
        }

        if (Schema::hasColumn('staff_tbl', 'user_id') && !$this->toIntOrNull($staff->user_id ?? null)) {
            $staff->user_id = (int) $user->user_id;
            if (Schema::hasColumn('staff_tbl', 'date_updated')) {
                $staff->date_updated = now();
            }
            $staff->save();
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function generateUniqueStaffUsername(Staff $staff, array $validated): string
    {
        $baseNic = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]/', '', trim((string) ($validated['nic_no'] ?? '')))));
        $roleSuffix = $this->resolveLoginRoleUsernameSuffix($this->toIntOrNull($validated['login_role_id'] ?? null));

        $base = $baseNic;
        if ($base === '') {
            $base = 'staff' . (int) $staff->stf_id;
        }

        if ($roleSuffix !== '') {
            $base .= '.' . $roleSuffix;
        }

        $username = $base;
        $suffix = 1;
        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = $base . $suffix;
        }

        return $username;
    }

    private function resolveLoginRoleUsernameSuffix(?int $roleId): string
    {
        if ($roleId === null || !Schema::hasTable('user_role_tbl')) {
            return '';
        }

        $roleName = strtolower(trim((string) DB::table('user_role_tbl')
            ->where('role_id', $roleId)
            ->value('role_name')));

        return match ($roleName) {
            'sds user' => 'sds',
            'class teacher', 'class_teacher', 'classteacher' => 'ct',
            'grade head', 'grade_head', 'gradehead' => 'gh',
            'sectional head', 'sectional_head', 'sectionalhead' => 'sh',
            'principal' => 'principal',
            default => $this->slugUsernameRoleSuffix($roleName),
        };
    }

    private function slugUsernameRoleSuffix(string $roleName): string
    {
        $suffix = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '', $roleName)));

        return $suffix !== '' ? $suffix : 'user';
    }

    /**
     * @param  array{created: bool, updated: bool, disabled: bool, username: string|null, temporary_password: string|null}  $loginResult
     */
    private function buildStaffSaveMessage(string $baseMessage, array $loginResult): string
    {
        if ($loginResult['created'] && $loginResult['username'] !== null && $loginResult['temporary_password'] !== null) {
            return $baseMessage . ' Login created. Username: ' . $loginResult['username'] . '. Temporary password: ' . $loginResult['temporary_password'] . '.';
        }

        if ($loginResult['updated'] && $loginResult['username'] !== null) {
            return $baseMessage . ' Login updated for username: ' . $loginResult['username'] . '.';
        }

        if ($loginResult['disabled'] && $loginResult['username'] !== null) {
            return $baseMessage . ' Login disabled for username: ' . $loginResult['username'] . '.';
        }

        return $baseMessage;
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

    private function toBool(mixed $value): bool
    {
        return $this->toBoolInt($value) === 1;
    }
}
