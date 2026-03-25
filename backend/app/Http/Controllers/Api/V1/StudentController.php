<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StudentIndexRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(StudentIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $search = isset($validated['q']) ? trim((string) $validated['q']) : '';

        $studentsQuery = DB::table('student_tbl as st')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                DB::raw('st.fullname as full_name'),
                'st.phone_no',
                'st.whatsapp_no',
                'st.dob',
                'st.d_o_admission',
                DB::raw('st.date_updated as last_update'),
            ])
            ->where('st.is_deleted', 0)
            ->when($search !== '', function ($builder) use ($search): void {
                $builder->where(function ($inner) use ($search): void {
                    $inner
                        ->where('st.index_no', 'like', "%{$search}%")
                        ->orWhere('st.name_with_initials', 'like', "%{$search}%")
                        ->orWhere('st.fullname', 'like', "%{$search}%");
                });
            })
            ->orderBy('st.index_no');

        $students = $studentsQuery->paginate($perPage);
        $items = collect($students->items());

        $indexNumbers = $items
            ->pluck('index_no')
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->values()
            ->all();

        $currentGradeClasses = collect();

        if (!empty($indexNumbers)) {
            $latestYearSubquery = DB::table('student_grade_class_tbl as sgc_latest')
                ->selectRaw('sgc_latest.index_no, MAX(sgc_latest.year) as latest_year')
                ->where('sgc_latest.is_deleted', 0)
                ->groupBy('sgc_latest.index_no');

            $currentGradeClasses = DB::table('student_grade_class_tbl as sgc')
                ->joinSub($latestYearSubquery, 'latest', function ($join): void {
                    $join
                        ->on('sgc.index_no', '=', 'latest.index_no')
                        ->on('sgc.year', '=', 'latest.latest_year');
                })
                ->leftJoin('grade_tbl as gt', 'sgc.grade_id', '=', 'gt.grade_id')
                ->leftJoin('class_tbl as ct', 'sgc.class_id', '=', 'ct.class_id')
                ->whereIn('sgc.index_no', $indexNumbers)
                ->where('sgc.is_deleted', 0)
                ->select([
                    'sgc.index_no',
                    'sgc.year',
                    'gt.grade',
                    'ct.class',
                ])
                ->orderBy('sgc.index_no')
                ->get()
                ->keyBy(fn ($row) => (string) $row->index_no);
        }

        $data = $items->map(function ($student) use ($currentGradeClasses): array {
            $indexKey = (string) $student->index_no;
            $gradeClass = $currentGradeClasses->get($indexKey);

            $gradeClassLabel = 'N/A';
            $currentYear = null;

            if ($gradeClass !== null) {
                $grade = trim((string) ($gradeClass->grade ?? ''));
                $className = trim((string) ($gradeClass->class ?? ''));
                $gradeClassLabel = trim("{$grade} {$className}") !== '' ? trim("{$grade} {$className}") : 'N/A';
                $currentYear = isset($gradeClass->year) ? (int) $gradeClass->year : null;
            }

            return [
                'std_id' => (int) $student->std_id,
                'index_no' => (string) $student->index_no,
                'name_with_initials' => (string) ($student->name_with_initials ?? ''),
                'full_name' => (string) ($student->full_name ?? ''),
                'phone_no' => $student->phone_no,
                'whatsapp_no' => $student->whatsapp_no,
                'dob' => $student->dob,
                'd_o_admission' => $student->d_o_admission,
                'last_update' => $student->last_update,
                'grade_class' => $gradeClassLabel,
                'current_year' => $currentYear,
            ];
        })->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $students->currentPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
                'last_page' => $students->lastPage(),
            ],
        ]);
    }
}
