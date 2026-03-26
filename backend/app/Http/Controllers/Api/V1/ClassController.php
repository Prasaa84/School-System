<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClassController extends Controller
{
    use AppliesSchoolScope;

    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasTable('school_grade_class_tbl')) {
            return response()->json(['data' => []]);
        }

        $user = $this->authUser();
        $gradeClassColumns = Schema::getColumnListing('school_grade_class_tbl');
        $hasIsDeleted = in_array('is_deleted', $gradeClassColumns, true);
        $schoolColumn = $this->resolveSchoolColumn($gradeClassColumns);

        $latestYearQuery = DB::table('school_grade_class_tbl as sgct');
        if ($hasIsDeleted) {
            $latestYearQuery->where('sgct.is_deleted', 0);
        }
        $this->applySchoolScope($latestYearQuery, $user, 'sgct', $schoolColumn);
        $latestYear = $latestYearQuery->max('year');

        if ($latestYear === null) {
            return response()->json(['data' => []]);
        }

        $gradeId = $request->query('grade_id');
        $gradeId = is_numeric($gradeId) ? (int) $gradeId : null;

        $query = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.grade_id',
                'sgct.class_id',
                'sgct.year',
                DB::raw('gt.grade as grade'),
                DB::raw('ct.class as class'),
            ])
            ->where('sgct.year', $latestYear)
            ->orderBy('sgct.grade_id')
            ->orderBy('sgct.class_id');

        if (in_array('stf_id', $gradeClassColumns, true)) {
            $query->addSelect('sgct.stf_id');
        }

        if ($schoolColumn !== null) {
            $query->addSelect(DB::raw("sgct.{$schoolColumn} as census_id"));
            $query->orderBy("sgct.{$schoolColumn}");
        }

        if ($hasIsDeleted) {
            $query->where('sgct.is_deleted', 0);
        }

        if ($gradeId !== null) {
            $query->where('sgct.grade_id', $gradeId);
        }

        $this->applySchoolScope($query, $user, 'sgct', $schoolColumn);

        if (in_array('approved_std_count', $gradeClassColumns, true)) {
            $query->addSelect('sgct.approved_std_count');
        }

        if (in_array('std_count', $gradeClassColumns, true)) {
            $query->addSelect('sgct.std_count');
        }

        if (Schema::hasTable('school_tbl') && $schoolColumn !== null) {
            $query->leftJoin('school_tbl as sc', "sgct.{$schoolColumn}", '=', 'sc.census_id')
                ->addSelect(DB::raw('sc.sch_name as school_name'));
        }

        if (Schema::hasTable('staff_tbl') && in_array('stf_id', $gradeClassColumns, true)) {
            $query->leftJoin('staff_tbl as st', 'sgct.stf_id', '=', 'st.stf_id')
                ->addSelect(DB::raw('st.name_with_ini as class_teacher'));
        }

        $rows = $query->get()->map(function ($row): array {
            return [
                'sch_grd_cls_id' => isset($row->sch_grd_cls_id) ? (int) $row->sch_grd_cls_id : null,
                'census_id' => isset($row->census_id) ? (int) $row->census_id : null,
                'school_name' => $row->school_name ?? null,
                'grade_id' => isset($row->grade_id) ? (int) $row->grade_id : null,
                'grade' => $row->grade,
                'class_id' => isset($row->class_id) ? (int) $row->class_id : null,
                'class' => $row->class,
                'year' => isset($row->year) ? (int) $row->year : null,
                'stf_id' => isset($row->stf_id) ? (int) $row->stf_id : null,
                'approved_std_count' => isset($row->approved_std_count) ? (int) $row->approved_std_count : null,
                'std_count' => isset($row->std_count) ? (int) $row->std_count : null,
                'class_teacher' => $row->class_teacher ?? null,
            ];
        })->all();

        return response()->json([
            'year' => (int) $latestYear,
            'data' => $rows,
        ]);
    }

    public function update(Request $request, int $classRowId): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!in_array((int) $user->role_id, [1, 2], true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $columns = Schema::getColumnListing('school_grade_class_tbl');
        $schoolColumn = $this->resolveSchoolColumn($columns);

        $query = DB::table('school_grade_class_tbl as sgct')->where('sgct.sch_grd_cls_id', $classRowId);
        if (in_array('is_deleted', $columns, true)) {
            $query->where('sgct.is_deleted', 0);
        }
        $this->applySchoolScope($query, $user, 'sgct', $schoolColumn);

        $row = $query->first();
        if ($row === null) {
            return response()->json(['message' => 'Class row not found.'], 404);
        }

        $updates = [];

        $stfId = $request->input('stf_id');
        if (in_array('stf_id', $columns, true)) {
            $updates['stf_id'] = is_numeric($stfId) ? (int) $stfId : null;
        }

        $approved = $request->input('approved_std_count');
        if (in_array('approved_std_count', $columns, true) && $approved !== null) {
            $updates['approved_std_count'] = is_numeric($approved) ? (int) $approved : null;
        }

        $stdCount = $request->input('std_count');
        if (in_array('std_count', $columns, true) && $stdCount !== null) {
            $updates['std_count'] = is_numeric($stdCount) ? (int) $stdCount : null;
        }

        if (in_array('date_updated', $columns, true)) {
            $updates['date_updated'] = now();
        }
        if (in_array('updated_dt', $columns, true)) {
            $updates['updated_dt'] = now();
        }

        if (!empty($updates)) {
            DB::table('school_grade_class_tbl')->where('sch_grd_cls_id', $classRowId)->update($updates);
        }

        return response()->json(['message' => 'Class row updated.']);
    }
}



