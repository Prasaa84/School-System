<?php

namespace App\Http\Controllers\Api\V1\Concerns;

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
        return (int) ($user?->role_id ?? 0) === 1;
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

        if (Schema::hasTable('school_details_tbl')) {
            $query = DB::table('school_details_tbl')->where('user_id', $userId);
            if (Schema::hasColumn('school_details_tbl', 'is_deleted')) {
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
        if ($this->isAdministrator($user)) {
            return;
        }

        if ($schoolColumn === null) {
            $query->whereRaw('1 = 0');
            return;
        }

        $censusId = $this->resolveUserCensusId($user);
        if ($censusId === null) {
            $query->whereRaw('1 = 0');
            return;
        }

        $qualified = $alias !== null ? "{$alias}.{$schoolColumn}" : $schoolColumn;
        $query->where($qualified, $censusId);
    }
}
