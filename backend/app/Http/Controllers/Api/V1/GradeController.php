<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GradeController extends Controller
{
    use AppliesSchoolScope;

    public function __invoke(): JsonResponse
    {
        if (!Schema::hasTable('school_grade_tbl')) {
            return response()->json(['data' => []]);
        }

        $user = $this->authUser();
        $gradeColumns = Schema::getColumnListing('school_grade_tbl');
        $hasIsDeleted = in_array('is_deleted', $gradeColumns, true);
        $schoolColumn = $this->resolveSchoolColumn($gradeColumns);

        $latestYearQuery = DB::table('school_grade_tbl as sgt');
        if ($hasIsDeleted) {
            $latestYearQuery->where('sgt.is_deleted', 0);
        }
        $this->applySchoolScope($latestYearQuery, $user, 'sgt', $schoolColumn);
        $latestYear = $latestYearQuery->max('year');

        if ($latestYear === null) {
            return response()->json(['data' => []]);
        }

        $query = DB::table('school_grade_tbl as sgt')
            ->leftJoin('grade_tbl as gt', 'sgt.grade_id', '=', 'gt.grade_id')
            ->select([
                'sgt.sch_grd_id',
                'sgt.grade_id',
                'sgt.year',
                DB::raw('gt.grade as grade'),
            ])
            ->where('sgt.year', $latestYear)
            ->orderBy('sgt.grade_id');

        if ($schoolColumn !== null) {
            $query->addSelect(DB::raw("sgt.{$schoolColumn} as school_id"));
            $query->orderBy("sgt.{$schoolColumn}");
        }

        if ($hasIsDeleted) {
            $query->where('sgt.is_deleted', 0);
        }

        $this->applySchoolScope($query, $user, 'sgt', $schoolColumn);

        if (Schema::hasTable('school_tbl') && $schoolColumn !== null) {
            $query->leftJoin('school_tbl as sc', "sgt.{$schoolColumn}", '=', 'sc.census_id')
                ->addSelect(DB::raw('sc.sch_name as school_name'));
        }

        if (Schema::hasTable('staff_tbl') && in_array('stf_id', $gradeColumns, true)) {
            $query->leftJoin('staff_tbl as st', 'sgt.stf_id', '=', 'st.stf_id')
                ->addSelect(DB::raw('st.name_with_ini as grade_head'));
        }

        $rows = $query->get()->map(function ($row): array {
            return [
                'sch_grd_id' => isset($row->sch_grd_id) ? (int) $row->sch_grd_id : null,
                'school_id' => isset($row->school_id) ? (int) $row->school_id : null,
                'school_name' => $row->school_name ?? null,
                'grade_id' => isset($row->grade_id) ? (int) $row->grade_id : null,
                'grade' => $row->grade,
                'year' => isset($row->year) ? (int) $row->year : null,
                'grade_head' => $row->grade_head ?? null,
            ];
        })->all();

        return response()->json([
            'year' => (int) $latestYear,
            'data' => $rows,
        ]);
    }
}
