<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\SchoolDetail;
use App\Models\SdsUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait AppliesSchoolScope
{
    protected function authUser(): ?SdsUser
    {
        $user = request()->attributes->get('auth_user');

        return $user instanceof SdsUser ? $user : null;
    }

    protected function isAdministrator(?SdsUser $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) ($user->role_id ?? 0) === 1) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator'], true);
    }

    protected function resolveUserCensusId(?SdsUser $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $value = $user->census_id ?? null;
        if (is_numeric($value)) {
            return (int) $value;
        }

        $userId = is_numeric($user->user_id ?? null) ? (int) $user->user_id : null;
        if ($userId === null) {
            return null;
        }

        $schoolTable = (new SchoolDetail())->getTable();
        if (Schema::hasTable($schoolTable) && Schema::hasColumn($schoolTable, 'user_id')) {
            $query = SchoolDetail::query()->where('user_id', $userId);
            if (Schema::hasColumn($schoolTable, 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $censusId = $query->value('census_id');
            if (is_numeric($censusId)) {
                return (int) $censusId;
            }
        }

        if (Schema::hasTable('staff_tbl')) {
            $query = DB::table('staff_tbl')->where('user_id', $userId);
            if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $censusId = $query->value('census_id');
            if (is_numeric($censusId)) {
                return (int) $censusId;
            }
        }

        return null;
    }

    protected function hasRequestedSchoolContext(): bool
    {
        $request = request();
        $headerValue = trim((string) ($request->header('X-School-Census-Id') ?? ''));
        if ($headerValue !== '') {
            return true;
        }

        return trim((string) ($request->query('school_census_id') ?? '')) !== '';
    }

    protected function resolveRequestedSchoolCensusId(?SdsUser $user): ?int
    {
        if (!$this->isAdministrator($user)) {
            return null;
        }

        $request = request();
        $rawValue = $request->header('X-School-Census-Id');
        if ($rawValue === null || trim((string) $rawValue) === '') {
            $rawValue = $request->query('school_census_id');
        }

        if ($rawValue === null || trim((string) $rawValue) === '') {
            return null;
        }

        if (!is_numeric($rawValue)) {
            return null;
        }

        $censusId = (int) $rawValue;
        if ($censusId <= 0) {
            return null;
        }

        if (!$this->schoolExists($censusId)) {
            return null;
        }

        return $censusId;
    }

    protected function resolveEffectiveSchoolCensusId(?SdsUser $user): ?int
    {
        if ($this->isAdministrator($user)) {
            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    protected function schoolExists(int $censusId): bool
    {
        $schoolTable = (new SchoolDetail())->getTable();
        if ($censusId <= 0 || !Schema::hasTable($schoolTable)) {
            return false;
        }

        $query = SchoolDetail::query()->where('census_id', $censusId);
        if (Schema::hasColumn($schoolTable, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->exists();
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function resolveSchoolColumn(array $columns): ?string
    {
        if (in_array('census_id', $columns, true)) {
            return 'census_id';
        }

        return null;
    }

    protected function applySchoolScope(Builder $query, ?SdsUser $user, ?string $alias, ?string $schoolColumn): void
    {
        if ($schoolColumn === null) {
            $query->whereRaw('1 = 0');
            return;
        }

        $isAdmin = $this->isAdministrator($user);
        $censusId = $this->resolveEffectiveSchoolCensusId($user);

        if ($censusId === null) {
            if ($isAdmin) {
                if ($this->hasRequestedSchoolContext()) {
                    $query->whereRaw('1 = 0');
                }
                return;
            }

            $query->whereRaw('1 = 0');
            return;
        }

        $qualified = $alias !== null ? "{$alias}.{$schoolColumn}" : $schoolColumn;
        $query->where($qualified, $censusId);
    }
}





