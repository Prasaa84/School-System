<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class StaffController extends Controller
{
    use AppliesSchoolScope;

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

            $query = DB::table('staff_tbl as st')
                ->leftJoin('designation_tbl as dt', 'st.desig_id', '=', 'dt.desig_id')
                ->select([
                    'st.stf_id',
                    'st.name_with_ini',
                    'st.nic_no',
                    'st.phone_mobile1',
                    DB::raw('dt.desig_type as designation'),
                    DB::raw('st.date_updated as last_update'),
                ])
                ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                    $builder->where('st.is_deleted', 0);
                })
                ->when($q !== '', function ($builder) use ($q): void {
                    $builder->where(function ($inner) use ($q): void {
                        $inner
                            ->where('st.name_with_ini', 'like', "%{$q}%")
                            ->orWhere('st.nic_no', 'like', "%{$q}%")
                            ->orWhere('st.phone_mobile1', 'like', "%{$q}%");
                    });
                })
                ->orderBy('st.stf_id', 'desc');

            $this->applySchoolScope($query, $user, 'st', $staffSchoolColumn);

            $paginated = $query->paginate($perPage);

            return response()->json([
                'data' => collect($paginated->items())->map(fn ($row): array => [
                    'stf_id' => (int) $row->stf_id,
                    'name_with_ini' => (string) ($row->name_with_ini ?? ''),
                    'nic_no' => (string) ($row->nic_no ?? ''),
                    'phone_mobile1' => $row->phone_mobile1,
                    'designation' => $row->designation,
                    'last_update' => $row->last_update,
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

        $query = DB::table('staff_tbl as st')
            ->select(['st.stf_id', 'st.name_with_ini'])
            ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                $builder->where('st.is_deleted', 0);
            })
            ->orderBy('st.name_with_ini');

        $this->applySchoolScope($query, $user, 'st', $staffSchoolColumn);

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

            $base = DB::table('staff_tbl as st')
                ->when(Schema::hasColumn('staff_tbl', 'is_deleted'), function ($builder): void {
                    $builder->where('st.is_deleted', 0);
                });

            $this->applySchoolScope($base, $user, 'st', $staffSchoolColumn);

            $total = (int) (clone $base)->count();

            $updated = (clone $base)
                ->when($year !== null, function ($builder) use ($year): void {
                    $builder->whereYear('st.date_updated', $year);
                })
                ->when($month !== null, function ($builder) use ($month): void {
                    $builder->whereMonth('st.date_updated', $month);
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
}
