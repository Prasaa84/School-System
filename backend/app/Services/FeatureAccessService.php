<?php

namespace App\Services;

use App\Models\SdsUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeatureAccessService
{
    public const STUDENT_VIEW = 'student.view';
    public const STUDENT_CREATE = 'student.create';
    public const STUDENT_UPDATE = 'student.update';
    public const STUDENT_DELETE = 'student.delete';

    private const TABLE = 'role_feature_permission_tbl';

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function featureCatalog(): array
    {
        return [
            [
                'key' => self::STUDENT_CREATE,
                'label' => 'Student Add',
                'description' => 'Create new student records.',
            ],
            [
                'key' => self::STUDENT_UPDATE,
                'label' => 'Student Edit',
                'description' => 'Edit existing student records.',
            ],
            [
                'key' => self::STUDENT_DELETE,
                'label' => 'Student Delete',
                'description' => 'Delete student records.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function featureKeys(): array
    {
        return [
            self::STUDENT_VIEW,
            self::STUDENT_CREATE,
            self::STUDENT_UPDATE,
            self::STUDENT_DELETE,
        ];
    }

    public function hasFeature(?SdsUser $user, ?string $schoolCensusId, string $featureKey): bool
    {
        $permissions = $this->permissionMapForUser($user, $schoolCensusId);

        return (bool) ($permissions[$featureKey] ?? false);
    }

    /**
     * @return array<string, bool>
     */
    public function permissionMapForUser(?SdsUser $user, ?string $schoolCensusId): array
    {
        if ($user === null) {
            return $this->emptyPermissionMap();
        }

        if ($this->isAdministrator($user)) {
            return $this->adminPermissionMap();
        }

        $roleId = is_numeric($user->role_id ?? null) ? (int) $user->role_id : null;

        return $this->permissionMapForRole($roleId, $schoolCensusId);
    }

    /**
     * @return array<string, bool>
     */
    public function permissionMapForRole(?int $roleId, ?string $schoolCensusId): array
    {
        $permissions = $this->defaultPermissionMapForRole($roleId);

        if ($roleId === null || $schoolCensusId === null || !Schema::hasTable(self::TABLE)) {
            return $permissions;
        }

        $candidates = $this->censusCandidates($schoolCensusId);
        if ($candidates === []) {
            return $permissions;
        }

        $rows = DB::table(self::TABLE)
            ->select(['feature_key', 'can_access'])
            ->where('role_id', $roleId)
            ->whereIn('census_id', $candidates)
            ->whereIn('feature_key', $this->featureKeys())
            ->get();

        foreach ($rows as $row) {
            $featureKey = is_string($row->feature_key ?? null) ? $row->feature_key : null;
            if ($featureKey === null || !array_key_exists($featureKey, $permissions)) {
                continue;
            }

            $permissions[$featureKey] = (int) ($row->can_access ?? 0) === 1;
        }

        return $permissions;
    }

    public function storageReady(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /**
     * @param  array<string, mixed>  $permissionUpdates
     */
    public function savePermissionsForRole(int $roleId, string $schoolCensusId, array $permissionUpdates): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            throw new \RuntimeException('Feature permission table is missing.');
        }

        $normalizedCensusId = $this->normalizeCensusId($schoolCensusId);
        if ($normalizedCensusId === null) {
            throw new \InvalidArgumentException('Invalid school census ID.');
        }

        $updates = $this->normalizePermissionUpdates($permissionUpdates);
        if ($updates === []) {
            throw new \InvalidArgumentException('No valid permission keys provided.');
        }

        $now = now();

        $rows = collect($updates)
            ->map(fn (bool $canAccess, string $featureKey): array => [
                'role_id' => $roleId,
                'census_id' => $normalizedCensusId,
                'feature_key' => $featureKey,
                'can_access' => $canAccess ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        DB::table(self::TABLE)->upsert(
            $rows,
            ['role_id', 'census_id', 'feature_key'],
            ['can_access', 'updated_at'],
        );
    }

    /**
     * @param  array<string, mixed>  $permissionUpdates
     * @return array<string, bool>
     */
    private function normalizePermissionUpdates(array $permissionUpdates): array
    {
        $allowed = array_flip($this->featureKeys());
        $normalized = [];

        foreach ($permissionUpdates as $featureKey => $rawValue) {
            if (!is_string($featureKey) || !array_key_exists($featureKey, $allowed)) {
                continue;
            }

            $normalized[$featureKey] = (bool) $rawValue;
        }

        return $normalized;
    }

    /**
     * @return array<string, bool>
     */
    private function emptyPermissionMap(): array
    {
        return [
            self::STUDENT_VIEW => false,
            self::STUDENT_CREATE => false,
            self::STUDENT_UPDATE => false,
            self::STUDENT_DELETE => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function adminPermissionMap(): array
    {
        return [
            self::STUDENT_VIEW => true,
            self::STUDENT_CREATE => true,
            self::STUDENT_UPDATE => true,
            self::STUDENT_DELETE => true,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function defaultPermissionMapForRole(?int $roleId): array
    {
        $permissions = [
            self::STUDENT_VIEW => true,
            self::STUDENT_CREATE => false,
            self::STUDENT_UPDATE => false,
            self::STUDENT_DELETE => false,
        ];

        if (in_array((int) $roleId, [1, 2, 4], true)) {
            $permissions[self::STUDENT_CREATE] = true;
            $permissions[self::STUDENT_UPDATE] = true;
            $permissions[self::STUDENT_DELETE] = true;
        }

        return $permissions;
    }

    private function normalizeCensusId(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '' || !preg_match('/^[0-9]{4,7}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * @return array<int, string>
     */
    private function censusCandidates(string $censusId): array
    {
        $base = $this->normalizeCensusId($censusId);
        if ($base === null) {
            return [];
        }

        $candidates = collect([$base]);

        if (is_numeric($base)) {
            $asNumber = (string) ((int) $base);
            $candidates
                ->push($asNumber)
                ->push(str_pad($asNumber, 5, '0', STR_PAD_LEFT))
                ->push(str_pad($asNumber, 7, '0', STR_PAD_LEFT));
        }

        return $candidates
            ->map(fn ($value): ?string => $this->normalizeCensusId($value))
            ->filter(fn ($value): bool => $value !== null)
            ->uniqueStrict()
            ->values()
            ->all();
    }

    private function isAdministrator(SdsUser $user): bool
    {
        if ((int) ($user->role_id ?? 0) === 1) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator'], true);
    }
}
