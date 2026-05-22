<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use App\Models\SchoolDetail;
use App\Services\FeatureAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeaturePermissionController extends Controller
{
    use AppliesSchoolScope;

    public function __construct(private readonly FeatureAccessService $featureAccess)
    {
    }

    public function index(): JsonResponse
    {
        $authUser = $this->authUser();
        if (!$this->isAdministrator($authUser)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $schools = $this->loadSchools();
        $selectedSchoolCensusId = $this->resolveRequestedSchoolCensusId($authUser);

        $roles = [];
        if ($selectedSchoolCensusId !== null) {
            $roles = $this->loadRolePermissionsBySchool($selectedSchoolCensusId);
        }

        $response = [
            'schools' => $schools,
            'selected_school_census_id' => $selectedSchoolCensusId,
            'features' => $this->featureAccess->featureCatalog(),
            'roles' => $roles,
            'storage_ready' => $this->featureAccess->storageReady(),
        ];

        if (!$response['storage_ready']) {
            $response['message'] = 'Role feature permission table is missing. Run backend migrations.';
        }

        return response()->json($response);
    }

    public function updateRole(Request $request, int $roleId): JsonResponse
    {
        $authUser = $this->authUser();
        if (!$this->isAdministrator($authUser)) {
            return response()->json(['message' => __('messages.auth.forbidden')], 403);
        }

        $validated = $request->validate([
            'school_census_id' => ['required', 'string', 'regex:/^[0-9]{4,7}$/'],
            'permissions' => ['required', 'array'],
        ]);

        if (!$this->featureAccess->storageReady()) {
            return response()->json([
                'message' => 'Role feature permission table is missing. Run backend migrations.',
            ], 500);
        }

        $schoolCensusId = $this->resolveCanonicalSchoolCensusId((string) $validated['school_census_id']);
        if ($schoolCensusId === null) {
            return response()->json([
                'message' => 'Invalid school selection.',
            ], 422);
        }

        $role = DB::table('user_role_tbl')
            ->select(['role_id', 'role_name'])
            ->where('role_id', $roleId)
            ->first();

        if ($role === null) {
            return response()->json([
                'message' => 'Role not found.',
            ], 404);
        }

        if ((int) $roleId === 1) {
            return response()->json([
                'message' => 'Administrator permissions are fixed and cannot be changed.',
            ], 422);
        }

        $permissions = is_array($validated['permissions']) ? $validated['permissions'] : [];

        try {
            $this->featureAccess->savePermissionsForRole($roleId, $schoolCensusId, $permissions);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 500);
        }

        $effectivePermissions = $this->featureAccess->permissionMapForRole($roleId, $schoolCensusId);

        return response()->json([
            'message' => 'Role feature permissions updated successfully.',
            'data' => [
                'role_id' => $roleId,
                'role_name' => (string) ($role->role_name ?? ''),
                'school_census_id' => $schoolCensusId,
                'permissions' => $this->editablePermissions($effectivePermissions),
            ],
        ]);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function loadSchools(): array
    {
        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return [];
        }

        $query = SchoolDetail::query()->select(['census_id', 'sch_name']);
        if (Schema::hasColumn($schoolTable, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->orderBy('sch_name')->get()->map(fn ($row): array => [
            'id' => (int) $row->census_id,
            'label' => (string) $row->sch_name,
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRolePermissionsBySchool(string $schoolCensusId): array
    {
        if (!Schema::hasTable('user_role_tbl')) {
            return [];
        }

        $roles = DB::table('user_role_tbl')
            ->select(['role_id', 'role_name'])
            ->where('role_id', '<>', 1)
            ->orderBy('role_id')
            ->get();

        return $roles
            ->map(function (object $row) use ($schoolCensusId): array {
                $roleId = is_numeric($row->role_id ?? null) ? (int) $row->role_id : null;
                $permissionMap = $this->featureAccess->permissionMapForRole($roleId, $schoolCensusId);

                return [
                    'role_id' => $roleId,
                    'role_name' => $row->role_name !== null ? (string) $row->role_name : null,
                    'permissions' => $this->editablePermissions($permissionMap),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $permissionMap
     * @return array<string, bool>
     */
    private function editablePermissions(array $permissionMap): array
    {
        $editable = [];

        foreach ($this->featureAccess->featureKeys() as $featureKey) {
            $editable[$featureKey] = (bool) ($permissionMap[$featureKey] ?? false);
        }

        return $editable;
    }
}
