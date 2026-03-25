<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\SdsUser;
use Illuminate\Database\Query\Builder;

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

        foreach (['census_id', 'school_id'] as $column) {
            $value = $user->{$column} ?? null;
            if (is_numeric($value)) {
                return (int) $value;
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

        if (in_array('school_id', $columns, true)) {
            return 'school_id';
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
