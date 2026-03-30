<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\SchoolDetail;
use App\Models\SdsUser;
use App\Models\Staff;
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

    protected function resolveUserCensusId(?SdsUser $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $directValue = $this->normalizeCensusId($user->census_id ?? null);
        if ($directValue !== null) {
            return $directValue;
        }

        $userId = is_numeric($user->user_id ?? null) ? (int) $user->user_id : null;
        if ($userId === null) {
            return null;
        }

        if (Schema::hasTable('staff_tbl')) {
            $query = Staff::query()->where('user_id', $userId);
            if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $staffCensusId = $this->normalizeCensusId($query->value('census_id'));
            if ($staffCensusId !== null) {
                return $staffCensusId;
            }
        }

        if ($this->isStudentRole($user)) {
            return $this->resolveStudentCensusIdByUsername($user);
        }

        return null;
    }

    protected function hasRequestedSchoolContext(): bool
    {
        return $this->resolveRequestedSchoolCensusIdFromRequest() !== null;
    }

    protected function resolveRequestedSchoolCensusId(?SdsUser $user): ?string
    {
        if (!$this->isAdministrator($user)) {
            return null;
        }

        $requestedCensusId = $this->resolveRequestedSchoolCensusIdFromRequest();
        if ($requestedCensusId === null) {
            return null;
        }

        return $this->resolveCanonicalSchoolCensusId($requestedCensusId);
    }

    protected function resolveEffectiveSchoolCensusId(?SdsUser $user): ?string
    {
        if ($this->isAdministrator($user)) {
            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    protected function schoolExists(string $censusId): bool
    {
        return $this->resolveCanonicalSchoolCensusId($censusId) !== null;
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
        $candidates = $this->censusCandidates($censusId);
        if ($candidates === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($qualified, $candidates);
    }

    private function resolveRequestedSchoolCensusIdFromRequest(): ?string
    {
        $request = request();
        $rawValue = $request->header('X-School-Census-Id');

        if ($rawValue === null || trim((string) $rawValue) === '') {
            $rawValue = $request->query('school_census_id');
        }

        if ($rawValue === null || trim((string) $rawValue) === '') {
            $rawValue = $request->input('school_census_id');
        }

        return $this->normalizeCensusId($rawValue);
    }

    private function isStudentRole(?SdsUser $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ((int) ($user->role_id ?? 0) === 7) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return $roleName === 'student';
    }

    private function resolveStudentCensusIdByUsername(SdsUser $user): ?string
    {
        if (!Schema::hasTable('student_tbl')) {
            return null;
        }

        $indexNo = trim((string) ($user->username ?? ''));
        if ($indexNo === '') {
            return null;
        }

        $baseQuery = DB::table('student_tbl')->where('index_no', $indexNo);
        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $baseQuery->where('is_deleted', 0);
        }

        $requestedCensusId = $this->resolveRequestedSchoolCensusIdFromRequest();
        if ($requestedCensusId !== null) {
            $requestedCandidates = $this->censusCandidates($requestedCensusId);

            $matchedCensus = (clone $baseQuery)
                ->whereIn('census_id', $requestedCandidates)
                ->value('census_id');

            $normalizedMatch = $this->normalizeCensusId($matchedCensus);
            if ($normalizedMatch !== null) {
                return $normalizedMatch;
            }
        }

        $candidateCensusIds = (clone $baseQuery)
            ->select('census_id')
            ->distinct()
            ->pluck('census_id')
            ->map(fn ($value): ?string => $this->normalizeCensusId($value))
            ->filter(fn ($value): bool => $value !== null)
            ->uniqueStrict()
            ->values();

        if ($candidateCensusIds->count() === 1) {
            return (string) $candidateCensusIds->first();
        }

        if ($requestedCensusId !== null) {
            foreach ($candidateCensusIds as $candidate) {
                if ($this->censusEquivalent((string) $candidate, $requestedCensusId)) {
                    return (string) $candidate;
                }
            }
        }

        return null;
    }

    private function resolveCanonicalSchoolCensusId(string $requestedCensusId): ?string
    {
        $schoolTable = (new SchoolDetail())->getTable();
        if (!Schema::hasTable($schoolTable)) {
            return null;
        }

        $query = SchoolDetail::query()->whereIn('census_id', $this->censusCandidates($requestedCensusId));
        if (Schema::hasColumn($schoolTable, 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $matched = $query->orderByDesc('census_id')->value('census_id');

        return $this->normalizeCensusId($matched);
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

    private function censusEquivalent(string $left, string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        return is_numeric($left) && is_numeric($right) && ((int) $left === (int) $right);
    }
}



