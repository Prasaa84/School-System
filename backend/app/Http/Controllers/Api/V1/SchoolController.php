<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Models\SchoolDetail;
use App\Models\SdsUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class SchoolController extends Controller
{
    use AppliesSchoolScope;

    public function show(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $selectedSchoolCensusId = $this->resolveTargetSchoolCensusId($user);
        $schools = $this->loadSchoolListForAdministrator($user);

        if ($selectedSchoolCensusId === null) {
            $message = $this->isAdministrator($user)
                ? 'Select a school first.'
                : 'School is not assigned for this user.';

            return response()->json([
                'school' => null,
                'schools' => $schools,
                'selected_school_census_id' => null,
                'can_edit' => $this->canEditSchoolDetails($user),
                'can_toggle_status' => $this->canManageSchools($user) && $this->supportsSchoolStatus(),
                'status_supported' => $this->supportsSchoolStatus(),
                'message' => $message,
            ]);
        }

        $includeDeleted = $this->isAdministrator($user);
        $school = $this->loadSchoolDetails($selectedSchoolCensusId, $includeDeleted);
        if ($school === null) {
            return response()->json([
                'message' => 'School details not found.',
            ], 404);
        }

        return response()->json([
            'school' => $school,
            'schools' => $schools,
            'selected_school_census_id' => $selectedSchoolCensusId,
            'can_edit' => $this->canEditSchoolDetails($user),
            'can_toggle_status' => $this->canManageSchools($user) && $this->supportsSchoolStatus(),
            'status_supported' => $this->supportsSchoolStatus(),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canEditSchoolDetails($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'province_id' => ['nullable', 'integer', 'min:0'],
            'district_id' => ['nullable', 'integer', 'min:0'],
            'zone_id' => ['nullable', 'integer', 'min:0'],
            'divisional_secretariat_id' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        $provinceId = $this->toPositiveInt($validated['province_id'] ?? null);
        $districtId = $this->toPositiveInt($validated['district_id'] ?? null);
        $zoneId = $this->toPositiveInt($validated['zone_id'] ?? null);
        $divisionalSecretariatId = $this->toPositiveInt($validated['divisional_secretariat_id'] ?? null);

        $divisionalSecretariatCode = null;
        if ($divisionalSecretariatId !== null && Schema::hasTable('div_secretariat_tbl')) {
            $code = DB::table('div_secretariat_tbl')
                ->where('div_sec_id', $divisionalSecretariatId)
                ->value('div_sec_dis_id');

            $divisionalSecretariatCode = is_numeric($code) ? (int) $code : null;
        }

        $gradeSpans = [];
        if (Schema::hasTable('grade_span_tbl')) {
            $gradeSpans = DB::table('grade_span_tbl')
                ->select(['grd_span_id', 'grd_span', 'grd_span_desc'])
                ->orderBy('grd_span_id')
                ->get()
                ->map(function (object $row): array {
                    $span = trim((string) ($row->grd_span ?? ''));
                    $description = trim((string) ($row->grd_span_desc ?? ''));
                    $label = $span !== '' ? $span : (string) ($row->grd_span_id ?? '');

                    if ($description !== '') {
                        $label = "{$label} - {$description}";
                    }

                    return [
                        'id' => (int) $row->grd_span_id,
                        'label' => $label,
                    ];
                })
                ->all();
        }

        return response()->json([
            'provinces' => $this->loadOptionRows(
                'province_tbl',
                'pro_id',
                ['pro_name'],
            ),
            'districts' => $this->loadOptionRows(
                'district_tbl',
                'dis_id',
                ['dis_name'],
                function (Builder $query) use ($provinceId): void {
                    if ($provinceId !== null && Schema::hasColumn('district_tbl', 'pro_id')) {
                        $query->where('pro_id', $provinceId);
                    }
                },
            ),
            'education_zones' => $this->loadOptionRows(
                'edu_zone_tbl',
                'zone_id',
                ['zone_name'],
                function (Builder $query) use ($provinceId, $districtId): void {
                    if ($provinceId !== null && Schema::hasColumn('edu_zone_tbl', 'pro_id')) {
                        $query->where('pro_id', $provinceId);
                    }

                    if ($districtId !== null && Schema::hasColumn('edu_zone_tbl', 'dis_id')) {
                        $query->where('dis_id', $districtId);
                    }
                },
            ),
            'education_divisions' => $this->loadOptionRows(
                'edu_div_tbl',
                'div_id',
                ['div_name'],
                function (Builder $query) use ($zoneId): void {
                    if ($zoneId !== null && Schema::hasColumn('edu_div_tbl', 'zone_id')) {
                        $query->where('zone_id', $zoneId);
                    }
                },
            ),
            'divisional_secretariats' => $this->loadOptionRows(
                'div_secretariat_tbl',
                'div_sec_id',
                ['div_sec_name_en', 'div_sec_name_si', 'div_sec_name_ta'],
                function (Builder $query) use ($districtId): void {
                    if ($districtId !== null && Schema::hasColumn('div_secretariat_tbl', 'dis_id')) {
                        $query->where('dis_id', $districtId);
                    }
                },
            ),
            'grama_niladhari_divisions' => $this->loadOptionRows(
                'gs_divisions_tbl',
                'gs_div_id',
                ['gs_name_en', 'gs_name_si', 'gs_name_ta'],
                function (Builder $query) use ($divisionalSecretariatCode): void {
                    if ($divisionalSecretariatCode !== null && Schema::hasColumn('gs_divisions_tbl', 'div_sec_dis_id')) {
                        $query->where('div_sec_dis_id', $divisionalSecretariatCode);
                    }
                },
            ),
            'school_types' => $this->loadOptionRows(
                'school_type_tbl',
                'sch_type_id',
                ['sch_type'],
                null,
                true,
            ),
            'school_belongs_to' => $this->loadOptionRows(
                'school_belongs_tbl',
                'belongs_to_id',
                ['belongs_to_name'],
            ),
            'grade_spans' => $gradeSpans,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canEditSchoolDetails($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $selectedSchoolCensusId = $this->resolveTargetSchoolCensusId($user);
        if ($selectedSchoolCensusId === null) {
            return response()->json([
                'message' => 'Select a school first.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'exam_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sch_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'web_address' => ['sometimes', 'nullable', 'string', 'max:150'],
            'pro_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dis_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'zone_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'div_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'div_sec_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'gs_div_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sch_type_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'belongs_to_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'grd_span_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_deleted' => ['sometimes', 'integer', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if ($validated === []) {
            return response()->json([
                'message' => 'No update fields provided.',
            ], 422);
        }

        $updates = [];

        foreach (['exam_no', 'sch_name', 'address1', 'address2', 'contact_no', 'email', 'web_address'] as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            $value = trim((string) ($validated[$field] ?? ''));
            $updates[$field] = $value === '' ? null : $value;
        }

        foreach (['pro_id', 'dis_id', 'zone_id', 'div_id', 'div_sec_id', 'gs_div_id', 'sch_type_id', 'belongs_to_id', 'grd_span_id'] as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            $updates[$field] = $this->toIntOrZero($validated[$field] ?? null);
        }

        if (array_key_exists('is_deleted', $validated)) {
            if (!$this->canManageSchools($user)) {
                return response()->json([
                    'message' => 'Only admin can change school status.',
                ], 403);
            }

            if (!$this->supportsSchoolStatus()) {
                return response()->json([
                    'message' => 'School status flag is not available.',
                ], 422);
            }

            $updates['is_deleted'] = ((int) ($validated['is_deleted'] ?? 0)) === 1 ? 1 : 0;
        }

        $errors = $this->validateDropdownValues($updates);
        if ($errors !== []) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return response()->json([
                'message' => 'School details table is missing.',
            ], 500);
        }

        if (Schema::hasColumn($schoolTable, 'date_updated')) {
            $updates['date_updated'] = now();
        }

        DB::table($schoolTable)
            ->whereIn('census_id', $this->censusCandidates($selectedSchoolCensusId))
            ->update($updates);

        $school = $this->loadSchoolDetails($selectedSchoolCensusId, $this->isAdministrator($user));

        return response()->json([
            'message' => 'School details updated successfully.',
            'school' => $school,
        ]);
    }


    public function store(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageSchools($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'census_id' => ['required', 'string', 'regex:/^[0-9]{4,7}$/'],
            'exam_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'sch_name' => ['required', 'string', 'max:255'],
            'address1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'web_address' => ['sometimes', 'nullable', 'string', 'max:150'],
            'pro_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'dis_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'zone_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'div_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'div_sec_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'gs_div_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sch_type_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'belongs_to_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'grd_span_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $schoolTable = (new SchoolDetail())->getTable();

        if (!Schema::hasTable($schoolTable)) {
            return response()->json([
                'message' => 'School details table is missing.',
            ], 500);
        }

        $censusId = trim((string) ($validated['census_id'] ?? ''));
        $existsQuery = DB::table($schoolTable)
            ->whereIn('census_id', $this->censusCandidates($censusId));

        if ($existsQuery->exists()) {
            return response()->json([
                'message' => 'A school already exists with this census ID. You can change its status to Active.',
            ], 422);
        }

        $insert = [
            'census_id' => $censusId,
            'exam_no' => $this->toNullableString($validated['exam_no'] ?? null),
            'sch_name' => $this->toNullableString($validated['sch_name'] ?? null),
            'address1' => $this->toNullableString($validated['address1'] ?? null),
            'address2' => $this->toNullableString($validated['address2'] ?? null),
            'contact_no' => $this->toNullableString($validated['contact_no'] ?? null),
            'email' => $this->toNullableString($validated['email'] ?? null),
            'web_address' => $this->toNullableString($validated['web_address'] ?? null),
            'pro_id' => $this->toIntOrZero($validated['pro_id'] ?? null),
            'dis_id' => $this->toIntOrZero($validated['dis_id'] ?? null),
            'zone_id' => $this->toIntOrZero($validated['zone_id'] ?? null),
            'div_id' => $this->toIntOrZero($validated['div_id'] ?? null),
            'div_sec_id' => $this->toIntOrZero($validated['div_sec_id'] ?? null),
            'gs_div_id' => $this->toIntOrZero($validated['gs_div_id'] ?? null),
            'sch_type_id' => $this->toIntOrZero($validated['sch_type_id'] ?? null),
            'belongs_to_id' => $this->toIntOrZero($validated['belongs_to_id'] ?? null),
            'grd_span_id' => $this->toIntOrZero($validated['grd_span_id'] ?? null),
        ];

        $errors = $this->validateDropdownValues($insert);
        if ($errors !== []) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], 422);
        }

        $now = now();
        if (Schema::hasColumn($schoolTable, 'date_added')) {
            $insert['date_added'] = $now;
        }
        if (Schema::hasColumn($schoolTable, 'date_updated')) {
            $insert['date_updated'] = $now;
        }
        if (Schema::hasColumn($schoolTable, 'is_deleted')) {
            $insert['is_deleted'] = 0;
        }

        DB::table($schoolTable)->insert($insert);

        return response()->json([
            'message' => 'School added successfully.',
            'school' => $this->loadSchoolDetails($censusId, true),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageSchools($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'school_census_id' => ['nullable', 'string', 'regex:/^[0-9]{4,7}$/'],
        ])->validate();

        $requested = trim((string) ($validated['school_census_id'] ?? ''));
        $targetCensusId = $requested !== '' ? $requested : $this->resolveRequestedSchoolCensusId($user);

        if ($targetCensusId === null || trim((string) $targetCensusId) === '') {
            return response()->json([
                'message' => 'Select a school first.',
            ], 422);
        }

        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return response()->json([
                'message' => 'School details table is missing.',
            ], 500);
        }

        $query = DB::table($schoolTable)->whereIn('census_id', $this->censusCandidates($targetCensusId));

        if (Schema::hasColumn($schoolTable, 'is_deleted')) {
            $activeQuery = clone $query;
            $activeRow = $activeQuery->where('is_deleted', 0)->first();
            if ($activeRow === null) {
                return response()->json([
                    'message' => 'School not found.',
                ], 404);
            }

            $updates = ['is_deleted' => 1];
            if (Schema::hasColumn($schoolTable, 'date_updated')) {
                $updates['date_updated'] = now();
            }

            DB::table($schoolTable)
                ->whereIn('census_id', $this->censusCandidates($targetCensusId))
                ->where('is_deleted', 0)
                ->update($updates);
        } else {
            $existing = (clone $query)->first();
            if ($existing === null) {
                return response()->json([
                    'message' => 'School not found.',
                ], 404);
            }

            DB::table($schoolTable)
                ->whereIn('census_id', $this->censusCandidates($targetCensusId))
                ->delete();
        }

        return response()->json([
            'message' => 'School deleted successfully.',
        ]);
    }

    private function resolveTargetSchoolCensusId(?SdsUser $user): ?string
    {
        if ($this->isAdministrator($user)) {
            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    private function canEditSchoolDetails(?SdsUser $user): bool
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

    private function canManageSchools(?SdsUser $user): bool
    {
        return $user !== null && $this->isAdministrator($user);
    }

    private function supportsSchoolStatus(): bool
    {
        $schoolTable = (new SchoolDetail())->getTable();

        return Schema::hasTable($schoolTable) && Schema::hasColumn($schoolTable, 'is_deleted');
    }

    /**
     * @return array<int, array{id: int, label: string, census_id: string, is_deleted: int}>
     */
    private function loadSchoolListForAdministrator(?SdsUser $user): array
    {
        if (!$this->isAdministrator($user)) {
            return [];
        }

        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return [];
        }

        $hasIsDeleted = Schema::hasColumn($schoolTable, 'is_deleted');
        $selectColumns = ['census_id', 'sch_name'];
        if ($hasIsDeleted) {
            $selectColumns[] = 'is_deleted';
        }

        return SchoolDetail::query()
            ->select($selectColumns)
            ->orderBy('sch_name')
            ->get()
            ->map(function (SchoolDetail $row) use ($hasIsDeleted): array {
                $name = trim((string) ($row->sch_name ?? ''));
                $censusId = (string) ($row->census_id ?? '');
                $isDeleted = $hasIsDeleted ? (((int) ($row->is_deleted ?? 0)) === 1 ? 1 : 0) : 0;

                $label = $name !== '' ? $name : $censusId;

                return [
                    'id' => (int) $row->census_id,
                    'label' => $label,
                    'census_id' => $censusId,
                    'is_deleted' => $isDeleted,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadSchoolDetails(string $censusId, bool $includeDeleted = false): ?array
    {
        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return null;
        }

        $hasIsDeleted = Schema::hasColumn($schoolTable, 'is_deleted');

        $query = DB::table($schoolTable)->whereIn('census_id', $this->censusCandidates($censusId));
        if ($hasIsDeleted && !$includeDeleted) {
            $query->where('is_deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $school = [
            'census_id' => (string) ($row->census_id ?? ''),
            'exam_no' => $this->toNullableString($row->exam_no ?? null),
            'sch_name' => $this->toNullableString($row->sch_name ?? null),
            'address1' => $this->toNullableString($row->address1 ?? null),
            'address2' => $this->toNullableString($row->address2 ?? null),
            'contact_no' => $this->toNullableString($row->contact_no ?? null),
            'email' => $this->toNullableString($row->email ?? null),
            'web_address' => $this->toNullableString($row->web_address ?? null),
            'pro_id' => $this->toIntOrZero($row->pro_id ?? null),
            'dis_id' => $this->toIntOrZero($row->dis_id ?? null),
            'zone_id' => $this->toIntOrZero($row->zone_id ?? null),
            'div_id' => $this->toIntOrZero($row->div_id ?? null),
            'div_sec_id' => $this->toIntOrZero($row->div_sec_id ?? null),
            'gs_div_id' => $this->toIntOrZero($row->gs_div_id ?? null),
            'sch_type_id' => $this->toIntOrZero($row->sch_type_id ?? null),
            'belongs_to_id' => $this->toIntOrZero($row->belongs_to_id ?? null),
            'grd_span_id' => $this->toIntOrZero($row->grd_span_id ?? null),
            'is_deleted' => $hasIsDeleted ? (((int) ($row->is_deleted ?? 0)) === 1 ? 1 : 0) : 0,
        ];

        $school['province_name'] = $this->resolveOptionLabel('province_tbl', 'pro_id', $school['pro_id'], ['pro_name']);
        $school['district_name'] = $this->resolveOptionLabel('district_tbl', 'dis_id', $school['dis_id'], ['dis_name']);
        $school['education_zone_name'] = $this->resolveOptionLabel('edu_zone_tbl', 'zone_id', $school['zone_id'], ['zone_name']);
        $school['education_division_name'] = $this->resolveOptionLabel('edu_div_tbl', 'div_id', $school['div_id'], ['div_name']);
        $school['divisional_secretariat_name'] = $this->resolveOptionLabel('div_secretariat_tbl', 'div_sec_id', $school['div_sec_id'], ['div_sec_name_en', 'div_sec_name_si', 'div_sec_name_ta']);
        $school['grama_niladhari_division_name'] = $this->resolveOptionLabel('gs_divisions_tbl', 'gs_div_id', $school['gs_div_id'], ['gs_name_en', 'gs_name_si', 'gs_name_ta']);
        $school['school_type_name'] = $this->resolveOptionLabel('school_type_tbl', 'sch_type_id', $school['sch_type_id'], ['sch_type']);
        $school['belongs_to_name'] = $this->resolveOptionLabel('school_belongs_tbl', 'belongs_to_id', $school['belongs_to_id'], ['belongs_to_name']);
        $school['grade_span_name'] = $this->resolveOptionLabel('grade_span_tbl', 'grd_span_id', $school['grd_span_id'], ['grd_span', 'grd_span_desc']);

        return $school;
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, array<int, string>>
     */
    private function validateDropdownValues(array $updates): array
    {
        $errors = [];

        $provinceId = $this->toPositiveInt($updates['pro_id'] ?? null);
        $districtId = $this->toPositiveInt($updates['dis_id'] ?? null);
        $zoneId = $this->toPositiveInt($updates['zone_id'] ?? null);
        $divisionId = $this->toPositiveInt($updates['div_id'] ?? null);
        $divisionalSecretariatId = $this->toPositiveInt($updates['div_sec_id'] ?? null);
        $gsDivisionId = $this->toPositiveInt($updates['gs_div_id'] ?? null);
        $schoolTypeId = $this->toPositiveInt($updates['sch_type_id'] ?? null);
        $belongsToId = $this->toPositiveInt($updates['belongs_to_id'] ?? null);
        $gradeSpanId = $this->toPositiveInt($updates['grd_span_id'] ?? null);

        if ($provinceId !== null && !$this->rowExists('province_tbl', 'pro_id', $provinceId)) {
            $errors['pro_id'] = ['Selected province is invalid.'];
        }

        if ($districtId !== null && !$this->rowExists('district_tbl', 'dis_id', $districtId)) {
            $errors['dis_id'] = ['Selected district is invalid.'];
        }

        if ($zoneId !== null && !$this->rowExists('edu_zone_tbl', 'zone_id', $zoneId)) {
            $errors['zone_id'] = ['Selected education zone is invalid.'];
        }

        if ($divisionId !== null && !$this->rowExists('edu_div_tbl', 'div_id', $divisionId)) {
            $errors['div_id'] = ['Selected education division is invalid.'];
        }

        if ($divisionalSecretariatId !== null && !$this->rowExists('div_secretariat_tbl', 'div_sec_id', $divisionalSecretariatId)) {
            $errors['div_sec_id'] = ['Selected divisional secretariat is invalid.'];
        }

        if ($gsDivisionId !== null && !$this->rowExists('gs_divisions_tbl', 'gs_div_id', $gsDivisionId)) {
            $errors['gs_div_id'] = ['Selected grama niladhari division is invalid.'];
        }

        if ($schoolTypeId !== null && !$this->rowExists('school_type_tbl', 'sch_type_id', $schoolTypeId, true)) {
            $errors['sch_type_id'] = ['Selected school type is invalid.'];
        }

        if ($belongsToId !== null && !$this->rowExists('school_belongs_tbl', 'belongs_to_id', $belongsToId)) {
            $errors['belongs_to_id'] = ['Selected school category is invalid.'];
        }

        if ($gradeSpanId !== null && !$this->rowExists('grade_span_tbl', 'grd_span_id', $gradeSpanId)) {
            $errors['grd_span_id'] = ['Selected grade span is invalid.'];
        }

        if (
            $provinceId !== null
            && $districtId !== null
            && Schema::hasTable('district_tbl')
            && Schema::hasColumn('district_tbl', 'pro_id')
        ) {
            $isMatch = DB::table('district_tbl')
                ->where('dis_id', $districtId)
                ->where('pro_id', $provinceId)
                ->exists();

            if (!$isMatch) {
                $errors['dis_id'] = ['District does not belong to the selected province.'];
            }
        }

        if ($zoneId !== null && Schema::hasTable('edu_zone_tbl')) {
            $zoneQuery = DB::table('edu_zone_tbl')->where('zone_id', $zoneId);

            if ($districtId !== null && Schema::hasColumn('edu_zone_tbl', 'dis_id')) {
                $zoneQuery->where('dis_id', $districtId);
            }
            if ($provinceId !== null && Schema::hasColumn('edu_zone_tbl', 'pro_id')) {
                $zoneQuery->where('pro_id', $provinceId);
            }

            if (!$zoneQuery->exists()) {
                $errors['zone_id'] = ['Education zone does not match selected province/district.'];
            }
        }

        if (
            $divisionId !== null
            && $zoneId !== null
            && Schema::hasTable('edu_div_tbl')
            && Schema::hasColumn('edu_div_tbl', 'zone_id')
        ) {
            $isMatch = DB::table('edu_div_tbl')
                ->where('div_id', $divisionId)
                ->where('zone_id', $zoneId)
                ->exists();

            if (!$isMatch) {
                $errors['div_id'] = ['Education division does not belong to the selected education zone.'];
            }
        }

        if (
            $divisionalSecretariatId !== null
            && $districtId !== null
            && Schema::hasTable('div_secretariat_tbl')
            && Schema::hasColumn('div_secretariat_tbl', 'dis_id')
        ) {
            $isMatch = DB::table('div_secretariat_tbl')
                ->where('div_sec_id', $divisionalSecretariatId)
                ->where('dis_id', $districtId)
                ->exists();

            if (!$isMatch) {
                $errors['div_sec_id'] = ['Divisional secretariat does not belong to the selected district.'];
            }
        }

        if (
            $gsDivisionId !== null
            && $divisionalSecretariatId !== null
            && Schema::hasTable('div_secretariat_tbl')
            && Schema::hasTable('gs_divisions_tbl')
            && Schema::hasColumn('div_secretariat_tbl', 'div_sec_dis_id')
            && Schema::hasColumn('gs_divisions_tbl', 'div_sec_dis_id')
        ) {
            $divisionalSecretariatCode = DB::table('div_secretariat_tbl')
                ->where('div_sec_id', $divisionalSecretariatId)
                ->value('div_sec_dis_id');

            if (is_numeric($divisionalSecretariatCode)) {
                $isMatch = DB::table('gs_divisions_tbl')
                    ->where('gs_div_id', $gsDivisionId)
                    ->where('div_sec_dis_id', (int) $divisionalSecretariatCode)
                    ->exists();

                if (!$isMatch) {
                    $errors['gs_div_id'] = ['Grama niladhari division does not belong to the selected divisional secretariat.'];
                }
            }
        }

        return $errors;
    }

    private function toNullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function toIntOrZero(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    private function toPositiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function rowExists(string $table, string $idColumn, int $id, bool $excludeDeleted = false): bool
    {
        if (!Schema::hasTable($table)) {
            return true;
        }

        $query = DB::table($table)->where($idColumn, $id);
        if ($excludeDeleted && Schema::hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->exists();
    }

    /**
     * @param  array<int, string>  $labelColumns
     */
    private function resolveOptionLabel(string $table, string $idColumn, int $id, array $labelColumns): ?string
    {
        if ($id <= 0 || !Schema::hasTable($table)) {
            return null;
        }

        $availableColumns = collect($labelColumns)
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($availableColumns === []) {
            return null;
        }

        $row = DB::table($table)
            ->where($idColumn, $id)
            ->first($availableColumns);

        if ($row === null) {
            return null;
        }

        foreach ($availableColumns as $column) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $labelColumns
     * @return array<int, array{id: int, label: string}>
     */
    private function loadOptionRows(
        string $table,
        string $idColumn,
        array $labelColumns,
        ?\Closure $applyFilters = null,
        bool $excludeDeleted = false,
    ): array {
        if (!Schema::hasTable($table)) {
            return [];
        }

        $existingLabelColumns = collect($labelColumns)
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        $selectColumns = array_values(array_unique(array_merge([$idColumn], $existingLabelColumns)));

        $query = DB::table($table)->select($selectColumns);
        if ($excludeDeleted && Schema::hasColumn($table, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if ($applyFilters !== null) {
            $applyFilters($query);
        }

        return $query
            ->orderBy($idColumn)
            ->get()
            ->map(function (object $row) use ($idColumn, $existingLabelColumns): array {
                $id = is_numeric($row->{$idColumn} ?? null) ? (int) $row->{$idColumn} : 0;
                $label = (string) $id;

                foreach ($existingLabelColumns as $column) {
                    $value = trim((string) ($row->{$column} ?? ''));
                    if ($value !== '') {
                        $label = $value;
                        break;
                    }
                }

                return [
                    'id' => $id,
                    'label' => $label,
                ];
            })
            ->filter(fn (array $row): bool => $row['id'] > 0)
            ->values()
            ->all();
    }
}


