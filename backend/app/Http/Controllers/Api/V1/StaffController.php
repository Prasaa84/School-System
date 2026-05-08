<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json([
            'data' => $query->get()->map(fn ($row): array => [
                'stf_id' => (int) $row->stf_id,
                'name_with_ini' => (string) ($row->name_with_ini ?? ''),
            ])->all(),
        ]);
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
}
