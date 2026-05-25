<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FeatureAccessService
{
    public const STUDENT_VIEW = 'student.view';
    public const STUDENT_CREATE = 'student.create';
    public const STUDENT_UPDATE = 'student.update';
    public const STUDENT_DELETE = 'student.delete';
    public const GRADE_VIEW = 'grade.view';
    public const GRADE_CREATE = 'grade.create';
    public const GRADE_UPDATE = 'grade.update';
    public const GRADE_DELETE = 'grade.delete';
    public const CLASS_VIEW = 'class.view';
    public const CLASS_CREATE = 'class.create';
    public const CLASS_UPDATE = 'class.update';
    public const CLASS_DELETE = 'class.delete';
    public const STAFF_VIEW = 'staff.view';
    public const STAFF_CREATE = 'staff.create';
    public const STAFF_UPDATE = 'staff.update';
    public const PAYMENT_VIEW = 'payment.view';
    public const PAYMENT_CREATE = 'payment.create';
    public const FEE_TYPE_VIEW = 'payment.fee_type.view';
    public const FEE_TYPE_CREATE = 'payment.fee_type.create';
    public const FEE_TYPE_UPDATE = 'payment.fee_type.update';
    public const REPORT_VIEW = 'report.view';

    private const TABLE = 'role_feature_permission_tbl';

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    private function featureDefinitions(): array
    {
        return [
            ['key' => self::STUDENT_VIEW, 'label' => 'Student View', 'description' => 'View student records.'],
            ['key' => self::STUDENT_CREATE, 'label' => 'Student Add', 'description' => 'Create new student records.'],
            ['key' => self::STUDENT_UPDATE, 'label' => 'Student Edit', 'description' => 'Edit existing student records.'],
            ['key' => self::STUDENT_DELETE, 'label' => 'Student Delete', 'description' => 'Delete student records.'],
            ['key' => self::GRADE_VIEW, 'label' => 'Grades View', 'description' => 'View grade records.'],
            ['key' => self::GRADE_CREATE, 'label' => 'Grades Add', 'description' => 'Create or initialize grade records.'],
            ['key' => self::GRADE_UPDATE, 'label' => 'Grades Edit', 'description' => 'Edit grade assignments.'],
            ['key' => self::GRADE_DELETE, 'label' => 'Grades Delete', 'description' => 'Delete grade rows.'],
            ['key' => self::CLASS_VIEW, 'label' => 'Classes View', 'description' => 'View class records.'],
            ['key' => self::CLASS_CREATE, 'label' => 'Classes Add', 'description' => 'Create class rows.'],
            ['key' => self::CLASS_UPDATE, 'label' => 'Classes Edit', 'description' => 'Edit class assignments and counts.'],
            ['key' => self::CLASS_DELETE, 'label' => 'Classes Delete', 'description' => 'Delete class rows.'],
            ['key' => self::STAFF_VIEW, 'label' => 'Staff View', 'description' => 'View staff records and reports.'],
            ['key' => self::STAFF_CREATE, 'label' => 'Staff Add', 'description' => 'Create new staff records.'],
            ['key' => self::STAFF_UPDATE, 'label' => 'Staff Edit', 'description' => 'Edit existing staff records.'],
            ['key' => self::PAYMENT_VIEW, 'label' => 'Payments View', 'description' => 'View SDS payment records.'],
            ['key' => self::PAYMENT_CREATE, 'label' => 'Payments Add', 'description' => 'Record SDS payments.'],
            ['key' => self::FEE_TYPE_VIEW, 'label' => 'Fee Types View', 'description' => 'View annual SDS fee types.'],
            ['key' => self::FEE_TYPE_CREATE, 'label' => 'Fee Types Add', 'description' => 'Create annual SDS fee types.'],
            ['key' => self::FEE_TYPE_UPDATE, 'label' => 'Fee Types Edit', 'description' => 'Edit annual SDS fee types.'],
            ['key' => self::REPORT_VIEW, 'label' => 'Reports View', 'description' => 'View report tabs and report data.'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public function featureCatalog(): array
    {
        return $this->featureDefinitions();
    }

    /**
     * @return array<int, string>
     */
    public function featureKeys(): array
    {
        return array_map(
            static fn (array $feature): string => $feature['key'],
            $this->featureDefinitions(),
        );
    }

    public function hasFeature(?User $user, ?string $schoolCensusId, string $featureKey): bool
    {
        $permissions = $this->permissionMapForUser($user, $schoolCensusId);

        return (bool) ($permissions[$featureKey] ?? false);
    }

    /**
     * @return array<string, bool>
     */
    public function permissionMapForUser(?User $user, ?string $schoolCensusId): array
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
        return collect($this->featureKeys())
            ->mapWithKeys(fn (string $key): array => [$key => false])
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function adminPermissionMap(): array
    {
        return collect($this->featureKeys())
            ->mapWithKeys(fn (string $key): array => [$key => true])
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function defaultPermissionMapForRole(?int $roleId): array
    {
        $permissions = $this->emptyPermissionMap();

        if ((int) $roleId === 7) {
            foreach ([
                self::STUDENT_VIEW,
                self::PAYMENT_VIEW,
            ] as $featureKey) {
                $permissions[$featureKey] = true;
            }

            return $permissions;
        }

        foreach ([
            self::STUDENT_VIEW,
            self::GRADE_VIEW,
            self::CLASS_VIEW,
            self::STAFF_VIEW,
            self::PAYMENT_VIEW,
            self::FEE_TYPE_VIEW,
            self::REPORT_VIEW,
        ] as $featureKey) {
            $permissions[$featureKey] = true;
        }

        if (in_array((int) $roleId, [2, 4], true)) {
            foreach ([
                self::STUDENT_CREATE,
                self::STUDENT_UPDATE,
                self::STUDENT_DELETE,
                self::GRADE_CREATE,
                self::GRADE_UPDATE,
                self::GRADE_DELETE,
                self::CLASS_CREATE,
                self::CLASS_UPDATE,
                self::CLASS_DELETE,
                self::STAFF_CREATE,
                self::STAFF_UPDATE,
                self::PAYMENT_CREATE,
                self::FEE_TYPE_CREATE,
                self::FEE_TYPE_UPDATE,
            ] as $featureKey) {
                $permissions[$featureKey] = true;
            }
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

    private function isAdministrator(User $user): bool
    {
        if ((int) ($user->role_id ?? 0) === 1) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator'], true);
    }
}
