<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\StudentDailyAttendance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class StudentAttendanceController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function index(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->isClassTeacher($user)) {
            return response()->json(['message' => 'Only class teachers can access daily attendance.'], 403);
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json([
                'date' => $this->resolveAttendanceDate($request),
                'class_info' => null,
                'data' => [],
                'message' => 'No class assignment found for this class teacher.',
            ]);
        }

        if (!Schema::hasTable('student_daily_attendance_tbl')) {
            return response()->json(['message' => 'Attendance table is not available.'], 422);
        }

        $assignment = $this->resolveClassTeacherAssignment($user);
        if ($assignment === null) {
            return response()->json([
                'date' => $this->resolveAttendanceDate($request),
                'class_info' => null,
                'data' => [],
                'message' => 'No class assignment found for this class teacher.',
            ]);
        }

        $date = $this->resolveAttendanceDate($request);
        $students = $this->loadAttendanceStudents($assignment['sch_grd_cls_id'], $date);
        $classInfo = $this->loadAttendanceClassInfo($assignment['sch_grd_cls_id']);
        if ($classInfo !== null) {
            $classInfo['student_count'] = count($students);
        }

        return response()->json([
            'date' => $date,
            'class_info' => $classInfo,
            'data' => $students,
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->isClassTeacher($user)) {
            return response()->json(['message' => 'Only class teachers can mark daily attendance.'], 403);
        }

        if ($this->isUnassignedClassTeacher($user)) {
            return response()->json(['message' => 'No class assignment found for this class teacher.'], 422);
        }

        if (!Schema::hasTable('student_daily_attendance_tbl')) {
            return response()->json(['message' => 'Attendance table is not available.'], 422);
        }

        $assignment = $this->resolveClassTeacherAssignment($user);
        if ($assignment === null) {
            return response()->json(['message' => 'No class assignment found for this class teacher.'], 422);
        }

        $validated = Validator::make($request->all(), [
            'student_id' => ['required', 'integer', 'exists:student_tbl,std_id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        $studentId = (int) $validated['student_id'];
        $date = isset($validated['date']) ? (string) $validated['date'] : $this->resolveAttendanceDate($request);

        if (!$this->studentBelongsToAssignedClass($studentId, $assignment['sch_grd_cls_id'])) {
            return response()->json(['message' => 'This student does not belong to the assigned class.'], 403);
        }

        /** @var StudentDailyAttendance|null $attendance */
        $attendance = StudentDailyAttendance::query()
            ->where('std_id', $studentId)
            ->where('attendance_date', $date)
            ->where('is_deleted', 0)
            ->first();

        $newStatus = $attendance === null || (int) ($attendance->status ?? 0) === 0 ? 1 : 0;
        $now = now();

        if ($attendance === null) {
            $attendance = new StudentDailyAttendance();
            $attendance->std_id = $studentId;
            $attendance->attendance_date = $date;
            $attendance->date_added = $now;
        }

        $attendance->status = $newStatus;
        $attendance->sch_grd_cls_id = $assignment['sch_grd_cls_id'];
        $attendance->census_id = $assignment['census_id'];
        $attendance->marked_by_user_id = is_numeric($user->user_id ?? null) ? (int) $user->user_id : null;
        $attendance->date_updated = $now;
        $attendance->is_deleted = 0;
        $attendance->save();

        return response()->json([
            'message' => $newStatus === 1 ? 'Attendance recorded.' : 'Attendance cleared.',
            'data' => [
                'student_id' => $studentId,
                'status' => $newStatus,
                'date' => $date,
            ],
        ]);
    }

    private function resolveAttendanceDate(Request $request): string
    {
        $validated = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        return isset($validated['date']) ? (string) $validated['date'] : now()->toDateString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadAttendanceStudents(int $schoolGradeClassId, string $date): array
    {
        $query = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->join('student_tbl as st', 'sgc.std_id', '=', 'st.std_id')
            ->leftJoin('student_daily_attendance_tbl as sda', function ($join) use ($date): void {
                $join->on('sda.std_id', '=', 'st.std_id')
                    ->where('sda.attendance_date', '=', $date)
                    ->where('sda.is_deleted', '=', 0);
            })
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId)
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
                DB::raw('COALESCE(sda.status, 0) as status'),
            ])
            ->orderBy('st.index_no');

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }
        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        return $query->get()->map(fn ($row): array => [
            'std_id' => (int) ($row->std_id ?? 0),
            'index_no' => (string) ($row->index_no ?? ''),
            'admission_no' => (string) ($row->index_no ?? ''),
            'name_with_initials' => (string) ($row->name_with_initials ?? ''),
            'status' => (int) ($row->status ?? 0),
        ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadAttendanceClassInfo(int $schoolGradeClassId): ?array
    {
        $gradeLabelColumn = $this->resolveLookupLabelColumn('grade_tbl', [
            'grade_en',
            'grade_si',
            'grade_ta',
        ]);
        $classLabelColumn = $this->resolveLookupLabelColumn('class_tbl', [
            'class_en',
            'class_si',
            'class_ta',
            'class',
        ]);

        $query = DB::table('school_grade_class_tbl as sgct')
            ->leftJoin('grade_tbl as gt', 'sgct.grade_id', '=', 'gt.grade_id')
            ->leftJoin('class_tbl as ct', 'sgct.class_id', '=', 'ct.class_id')
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId)
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.year',
                'sgct.grade_id',
                'sgct.class_id',
            ]);

        if ($gradeLabelColumn !== null) {
            $query->addSelect(DB::raw("gt.{$gradeLabelColumn} as grade"));
        }
        if ($classLabelColumn !== null) {
            $query->addSelect(DB::raw("ct.{$classLabelColumn} as class"));
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $row = $query->first();
        if ($row === null) {
            return null;
        }

        $grade = trim((string) ($row->grade ?? ''));
        $className = trim((string) ($row->class ?? ''));

        return [
            'sch_grd_cls_id' => (int) ($row->sch_grd_cls_id ?? 0),
            'year' => (int) ($row->year ?? 0),
            'grade_id' => (int) ($row->grade_id ?? 0),
            'class_id' => (int) ($row->class_id ?? 0),
            'grade' => $grade,
            'class' => $className,
            'grade_class' => trim("{$grade} {$className}"),
            'student_count' => 0,
        ];
    }

    private function studentBelongsToAssignedClass(int $studentId, int $schoolGradeClassId): bool
    {
        $query = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->where('sgc.std_id', $studentId)
            ->where('sgct.sch_grd_cls_id', $schoolGradeClassId);

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        return $query->exists();
    }
}
