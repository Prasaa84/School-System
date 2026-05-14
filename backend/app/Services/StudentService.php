<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\SchoolGradeClass;
use App\Models\Student;
use App\Models\StudentGradeClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StudentService
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{std_id:int,index_no:string,census_id:string}
     */
    public function createStudent(array $validated, string $censusId): array
    {
        $indexNo = trim((string) $validated['index_no']);
        Log::info('Student create requested.', [
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ]);

        $studentExists = Student::query()
            ->where('index_no', $indexNo)
            ->where('census_id', $censusId)
            ->where('is_deleted', 0)
            ->exists();

        if ($studentExists) {
            Log::warning('Student save rejected: duplicate admission.', [
                'index_no' => $indexNo,
                'census_id' => $censusId,
            ]);

            throw ValidationException::withMessages([
                'index_no' => [__('messages.students.already_exists', ['index_no' => $indexNo])],
            ]);
        }

        [, , , $schoolGradeClassId, ] = $this->validateAssignment($validated, $censusId);

        Log::info('Student save transaction begin.', [
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ]);

        $student = null;

        DB::transaction(function () use ($validated, $indexNo, $censusId, $schoolGradeClassId, &$student): void {
            $now = now();

            $student = Student::query()->create([
                'index_no' => $indexNo,
                'fullname' => $validated['full_name'],
                'name_with_initials' => $validated['name_with_initials'],
                'address1' => $validated['address1'] ?? '',
                'address2' => $validated['address2'] ?? '',
                'phone_no' => $validated['phone_no'] ?? '',
                'whatsapp_no' => $validated['whatsapp_no'] ?? '',
                'phone_home' => $validated['phone_home'] ?? '',
                'dob' => $validated['dob'] ?? null,
                'email' => $validated['email'] ?? '',
                'gender_id' => (int) $validated['gender_id'],
                'ethnic_group_id' => is_numeric($validated['ethnic_group_id'] ?? null) ? (int) $validated['ethnic_group_id'] : 0,
                'religion_id' => is_numeric($validated['religion_id'] ?? null) ? (int) $validated['religion_id'] : 0,
                'd_o_admission' => $validated['d_o_admission'] ?? null,
                'census_id' => $censusId,
                'st_status_id' => 1,
                'date_added' => $now,
                'date_updated' => $now,
                'is_deleted' => 0,
            ]);

            if ($schoolGradeClassId !== null && Schema::hasTable('student_grade_class_tbl')) {
                StudentGradeClass::query()->create([
                    'std_id' => (int) $student->std_id,
                    'sch_grd_cls_id' => $schoolGradeClassId,
                    'date_added' => $now,
                    'date_updated' => $now,
                    'is_deleted' => 0,
                ]);
            }

            $hasGuardianData = collect([
                $validated['father_name'] ?? null,
                $validated['father_job'] ?? null,
                $validated['father_mobile'] ?? null,
                $validated['mother_name'] ?? null,
                $validated['mother_job'] ?? null,
                $validated['mother_mobile'] ?? null,
                $validated['guardian_name'] ?? null,
                $validated['guardian_job'] ?? null,
                $validated['guardian_mobile'] ?? null,
            ])->contains(fn ($value): bool => trim((string) $value) !== '');

            if ($hasGuardianData && Schema::hasTable('guardian_tbl')) {
                Guardian::query()->create([
                    'index_no' => $indexNo,
                    'census_id' => $censusId,
                    'f_name' => $validated['father_name'] ?? '',
                    'f_job' => $validated['father_job'] ?? '',
                    'f_mobile' => $validated['father_mobile'] ?? '',
                    'm_name' => $validated['mother_name'] ?? '',
                    'm_job' => $validated['mother_job'] ?? '',
                    'm_mobile' => $validated['mother_mobile'] ?? '',
                    'g_name' => $validated['guardian_name'] ?? '',
                    'g_job' => $validated['guardian_job'] ?? '',
                    'g_mobile' => $validated['guardian_mobile'] ?? '',
                    'date_added' => $now,
                    'date_updated' => $now,
                    'is_deleted' => 0,
                ]);
            }
        });

        Log::info('Student create completed.', [
            'std_id' => (int) ($student?->std_id ?? 0),
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ]);

        return [
            'std_id' => (int) ($student?->std_id ?? 0),
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{std_id:int,index_no:string,census_id:string}
     */
    public function updateStudent(Student $student, array $validated, string $censusId): array
    {
        $studentId = (int) $student->std_id;
        $originalCensusId = trim((string) ($student->census_id ?? ''));
        $oldIndexNo = trim((string) ($student->index_no ?? ''));
        $indexNo = trim((string) $validated['index_no']);

        Log::info('Student update requested.', [
            'std_id' => $studentId,
            'old_index_no' => $oldIndexNo,
            'new_index_no' => $indexNo,
            'from_census_id' => $originalCensusId,
            'to_census_id' => $censusId,
        ]);

        $duplicateExists = Student::query()
            ->where('index_no', $indexNo)
            ->where('census_id', $censusId)
            ->where('is_deleted', 0)
            ->where('std_id', '<>', $studentId)
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'index_no' => [__('messages.students.already_exists', ['index_no' => $indexNo])],
            ]);
        }

        [, , , $schoolGradeClassId, $hasCompleteAssignment] = $this->validateAssignment(
            $validated,
            $censusId,
        );

        DB::transaction(function () use (
            $student,
            $validated,
            $censusId,
            $indexNo,
            $oldIndexNo,
            $originalCensusId,
            $schoolGradeClassId,
            $hasCompleteAssignment
        ): void {
            $now = now();

            $student->fill([
                'index_no' => $indexNo,
                'fullname' => $validated['full_name'],
                'name_with_initials' => $validated['name_with_initials'],
                'address1' => $validated['address1'] ?? '',
                'address2' => $validated['address2'] ?? '',
                'phone_no' => $validated['phone_no'] ?? '',
                'whatsapp_no' => $validated['whatsapp_no'] ?? '',
                'phone_home' => $validated['phone_home'] ?? '',
                'dob' => $validated['dob'] ?? null,
                'email' => $validated['email'] ?? '',
                'gender_id' => (int) $validated['gender_id'],
                'ethnic_group_id' => is_numeric($validated['ethnic_group_id'] ?? null) ? (int) $validated['ethnic_group_id'] : 0,
                'religion_id' => is_numeric($validated['religion_id'] ?? null) ? (int) $validated['religion_id'] : 0,
                'd_o_admission' => $validated['d_o_admission'] ?? null,
                'census_id' => $censusId,
                'date_updated' => $now,
            ]);
            $student->save();

            $this->replaceStudentAssignment($student, $censusId, $schoolGradeClassId, $hasCompleteAssignment, $now);
            $this->syncGuardian($validated, $indexNo, $oldIndexNo, $censusId, $originalCensusId, $now);
        });

        Log::info('Student update completed.', [
            'std_id' => $studentId,
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ]);

        return [
            'std_id' => $studentId,
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ];
    }

    public function deleteStudent(Student $student): void
    {
        $studentId = (int) $student->std_id;
        $indexNo = trim((string) ($student->index_no ?? ''));
        $censusId = trim((string) ($student->census_id ?? ''));

        Log::info('Student delete requested.', [
            'std_id' => $studentId,
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ]);

        DB::transaction(function () use ($student, $studentId, $indexNo, $censusId): void {
            $now = now();

            if (Schema::hasColumn('student_tbl', 'is_deleted')) {
                $student->fill(['is_deleted' => 1]);
                if (Schema::hasColumn('student_tbl', 'date_updated')) {
                    $student->fill(['date_updated' => $now]);
                }
                $student->save();
            } else {
                $student->delete();
            }

            if (Schema::hasTable('student_grade_class_tbl')) {
                $assignments = StudentGradeClass::query()->where('std_id', $studentId);
                if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                    $updates = ['is_deleted' => 1];
                    if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                        $updates['date_updated'] = $now;
                    }
                    $assignments->update($updates);
                } else {
                    $assignments->delete();
                }
            }

            if (Schema::hasTable('guardian_tbl')) {
                $guardians = Guardian::query()->where('index_no', $indexNo);
                if (Schema::hasColumn('guardian_tbl', 'census_id')) {
                    $guardians->where('census_id', $censusId);
                }

                if (Schema::hasColumn('guardian_tbl', 'is_deleted')) {
                    $updates = ['is_deleted' => 1];
                    if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                        $updates['date_updated'] = $now;
                    }
                    $guardians->update($updates);
                } else {
                    $guardians->delete();
                }
            }
        });

        Log::info('Student delete completed.', [
            'std_id' => $studentId,
            'index_no' => $indexNo,
            'census_id' => $censusId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0:?int,1:?int,2:?int,3:?int,4:bool}
     */
    private function validateAssignment(array $validated, string $censusId): array
    {
        $gradeId = is_numeric($validated['grade_id'] ?? null) ? (int) $validated['grade_id'] : null;
        $classId = is_numeric($validated['class_id'] ?? null) ? (int) $validated['class_id'] : null;
        $year = is_numeric($validated['year'] ?? null) ? (int) $validated['year'] : null;

        if ($gradeId !== null && $classId !== null) {
            $gradeStreamId = DB::table('grade_tbl')->where('grade_id', $gradeId)->value('stream_id');
            $classStreamId = DB::table('class_tbl')->where('class_id', $classId)->value('stream_id');

            if ($gradeStreamId === null || $classStreamId === null || (int) $gradeStreamId !== (int) $classStreamId) {
                throw ValidationException::withMessages([
                    'grade_id' => [__('messages.students.grade_class_mismatch')],
                    'class_id' => [__('messages.students.grade_class_mismatch')],
                ]);
            }
        }

        $hasAnyAssignment = $gradeId !== null || $classId !== null || $year !== null;
        $hasCompleteAssignment = $gradeId !== null && $classId !== null && $year !== null;
        if ($hasAnyAssignment && !$hasCompleteAssignment) {
            $errors = [];
            if ($gradeId === null || $classId === null) {
                $message = __('messages.students.validation.grade_class_required');
                $errors['grade_id'] = [$message];
                $errors['class_id'] = [$message];
            }
            if ($year === null) {
                $errors['year'] = [__('messages.students.validation.year_invalid')];
            }

            throw ValidationException::withMessages($errors);
        }

        $schoolGradeClassId = null;
        if ($hasCompleteAssignment) {
            $schoolGradeClassId = $this->resolveSchoolGradeClassIdForAssignment($gradeId, $classId, $year, $censusId);
            if ($schoolGradeClassId === null) {
                throw ValidationException::withMessages([
                    'grade_id' => [__('messages.students.grade_class_mismatch')],
                    'class_id' => [__('messages.students.grade_class_mismatch')],
                    'year' => [__('messages.students.grade_class_mismatch')],
                ]);
            }
        }

        return [$gradeId, $classId, $year, $schoolGradeClassId, $hasCompleteAssignment];
    }

    private function replaceStudentAssignment(Student $student, string $censusId, ?int $schoolGradeClassId, bool $hasCompleteAssignment, mixed $now): void
    {
        if (!Schema::hasTable('student_grade_class_tbl')) {
            return;
        }

        $assignments = StudentGradeClass::query()->where('std_id', (int) $student->std_id);
        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $updates = ['is_deleted' => 1];
            if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                $updates['date_updated'] = $now;
            }
            $assignments->where('is_deleted', 0)->update($updates);
        } else {
            $assignments->delete();
        }

        if ($hasCompleteAssignment && $schoolGradeClassId !== null) {
            $insert = [
                'std_id' => (int) $student->std_id,
                'sch_grd_cls_id' => $schoolGradeClassId,
            ];
            if (Schema::hasColumn('student_grade_class_tbl', 'date_added')) {
                $insert['date_added'] = $now;
            }
            if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                $insert['date_updated'] = $now;
            }
            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $insert['is_deleted'] = 0;
            }

            StudentGradeClass::query()->create($insert);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncGuardian(array $validated, string $indexNo, string $oldIndexNo, string $censusId, string $originalCensusId, mixed $now): void
    {
        if (!Schema::hasTable('guardian_tbl')) {
            return;
        }

        $guardianBase = ['index_no' => $indexNo];
        if (Schema::hasColumn('guardian_tbl', 'census_id')) {
            $guardianBase['census_id'] = $censusId;
        }

        $guardianData = [
            'f_name' => trim((string) ($validated['father_name'] ?? '')),
            'f_job' => trim((string) ($validated['father_job'] ?? '')),
            'f_mobile' => trim((string) ($validated['father_mobile'] ?? '')),
            'm_name' => trim((string) ($validated['mother_name'] ?? '')),
            'm_job' => trim((string) ($validated['mother_job'] ?? '')),
            'm_mobile' => trim((string) ($validated['mother_mobile'] ?? '')),
            'g_name' => trim((string) ($validated['guardian_name'] ?? '')),
            'g_job' => trim((string) ($validated['guardian_job'] ?? '')),
            'g_mobile' => trim((string) ($validated['guardian_mobile'] ?? '')),
        ];

        if ($originalCensusId !== $censusId && Schema::hasColumn('guardian_tbl', 'census_id')) {
            $moveQuery = Guardian::query()
                ->where('index_no', $oldIndexNo)
                ->where('census_id', $originalCensusId);

            $moveUpdates = ['census_id' => $censusId];
            if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                $moveUpdates['date_updated'] = $now;
            }
            $moveQuery->update($moveUpdates);
        }

        if ($oldIndexNo !== '' && $oldIndexNo !== $indexNo) {
            $renameQuery = Guardian::query()->where('index_no', $oldIndexNo);
            if (Schema::hasColumn('guardian_tbl', 'census_id')) {
                $renameQuery->where('census_id', $censusId);
            }

            $renameUpdates = ['index_no' => $indexNo];
            if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                $renameUpdates['date_updated'] = $now;
            }
            $renameQuery->update($renameUpdates);
        }

        $hasData = collect($guardianData)->contains(fn ($value): bool => $value !== '');
        $guardian = Guardian::query()->where($guardianBase)->first();

        if ($guardian !== null) {
            $updates = $guardianData;
            if (Schema::hasColumn('guardian_tbl', 'is_deleted')) {
                $updates['is_deleted'] = 0;
            }
            if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
                $updates['date_updated'] = $now;
            }
            $guardian->fill($updates)->save();
            return;
        }

        if (!$hasData) {
            return;
        }

        $insert = array_merge($guardianBase, $guardianData);
        if (Schema::hasColumn('guardian_tbl', 'is_deleted')) {
            $insert['is_deleted'] = 0;
        }
        if (Schema::hasColumn('guardian_tbl', 'date_added')) {
            $insert['date_added'] = $now;
        }
        if (Schema::hasColumn('guardian_tbl', 'date_updated')) {
            $insert['date_updated'] = $now;
        }

        Guardian::query()->create($insert);
    }

    public function resolveSchoolGradeClassIdForAssignment(int $gradeId, int $classId, int $year, string $censusId): ?int
    {
        if (!Schema::hasTable('school_grade_class_tbl')) {
            return null;
        }

        $query = SchoolGradeClass::query()
            ->where('grade_id', $gradeId)
            ->where('class_id', $classId)
            ->where('year', $year)
            ->whereIn('census_id', $this->censusCandidatesForAssignment($censusId));

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $schoolGradeClassId = $query
            ->orderByDesc('sch_grd_cls_id')
            ->value('sch_grd_cls_id');

        return is_numeric($schoolGradeClassId) ? (int) $schoolGradeClassId : null;
    }

    /**
     * @return array<int, string>
     */
    public function censusCandidatesForAssignment(string $censusId): array
    {
        $raw = trim($censusId);
        $candidates = collect([$raw])->filter(fn ($value): bool => $value !== '');

        if (is_numeric($raw)) {
            $asNumber = (string) ((int) $raw);
            $candidates
                ->push($asNumber)
                ->push(str_pad($asNumber, 5, '0', STR_PAD_LEFT))
                ->push(str_pad($asNumber, 7, '0', STR_PAD_LEFT));
        }

        return $candidates->uniqueStrict()->values()->all();
    }

    public function upsertStudentAssignmentForYear(int $studentId, int $schoolGradeClassId, int $targetYear): void
    {
        if (!Schema::hasTable('student_grade_class_tbl') || !Schema::hasTable('school_grade_class_tbl')) {
            return;
        }

        $existingAssignments = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->where('sgc.std_id', $studentId)
            ->where('sgct.year', $targetYear)
            ->when(Schema::hasColumn('student_grade_class_tbl', 'is_deleted'), function ($query): void {
                $query->where('sgc.is_deleted', 0);
            })
            ->when(Schema::hasColumn('school_grade_class_tbl', 'is_deleted'), function ($query): void {
                $query->where('sgct.is_deleted', 0);
            })
            ->orderByDesc('sgc.st_gr_cl_id')
            ->select(['sgc.st_gr_cl_id', 'sgc.sch_grd_cls_id'])
            ->get();

        $now = now();
        $primaryAssignment = $existingAssignments->shift();

        if ($primaryAssignment !== null) {
            $updates = ['sch_grd_cls_id' => $schoolGradeClassId];
            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $updates['is_deleted'] = 0;
            }
            if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                $updates['date_updated'] = $now;
            }

            StudentGradeClass::query()
                ->where('st_gr_cl_id', (int) $primaryAssignment->st_gr_cl_id)
                ->update($updates);
        } else {
            $insert = [
                'std_id' => $studentId,
                'sch_grd_cls_id' => $schoolGradeClassId,
            ];
            if (Schema::hasColumn('student_grade_class_tbl', 'date_added')) {
                $insert['date_added'] = $now;
            }
            if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                $insert['date_updated'] = $now;
            }
            if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                $insert['is_deleted'] = 0;
            }

            StudentGradeClass::query()->create($insert);
        }

        foreach ($existingAssignments as $duplicateAssignment) {
            if (!Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
                StudentGradeClass::query()->where('st_gr_cl_id', (int) $duplicateAssignment->st_gr_cl_id)->delete();
                continue;
            }

            $duplicateUpdates = ['is_deleted' => 1];
            if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
                $duplicateUpdates['date_updated'] = $now;
            }

            StudentGradeClass::query()
                ->where('st_gr_cl_id', (int) $duplicateAssignment->st_gr_cl_id)
                ->update($duplicateUpdates);
        }
    }

    public function replaceStudentAssignmentForYearHardDelete(int $studentId, int $schoolGradeClassId, int $targetYear): void
    {
        if (!Schema::hasTable('student_grade_class_tbl') || !Schema::hasTable('school_grade_class_tbl')) {
            return;
        }

        $assignmentIds = DB::table('student_grade_class_tbl as sgc')
            ->join('school_grade_class_tbl as sgct', 'sgc.sch_grd_cls_id', '=', 'sgct.sch_grd_cls_id')
            ->where('sgc.std_id', $studentId)
            ->where('sgct.year', $targetYear)
            ->select('sgc.st_gr_cl_id')
            ->pluck('st_gr_cl_id')
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0)
            ->values()
            ->all();

        if ($assignmentIds !== []) {
            StudentGradeClass::query()->whereIn('st_gr_cl_id', $assignmentIds)->delete();
        }

        $insert = [
            'std_id' => $studentId,
            'sch_grd_cls_id' => $schoolGradeClassId,
        ];
        $now = now();
        if (Schema::hasColumn('student_grade_class_tbl', 'date_added')) {
            $insert['date_added'] = $now;
        }
        if (Schema::hasColumn('student_grade_class_tbl', 'date_updated')) {
            $insert['date_updated'] = $now;
        }
        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $insert['is_deleted'] = 0;
        }

        StudentGradeClass::query()->create($insert);
    }

    public function hardDeleteAssignmentsForSchoolGradeClass(int $schoolGradeClassId): int
    {
        if (!Schema::hasTable('student_grade_class_tbl') || $schoolGradeClassId <= 0) {
            return 0;
        }

        return StudentGradeClass::query()
            ->where('sch_grd_cls_id', $schoolGradeClassId)
            ->delete();
    }

    public function hardDeleteStudentAssignmentForSchoolGradeClass(int $studentId, int $schoolGradeClassId): int
    {
        if (!Schema::hasTable('student_grade_class_tbl') || $studentId <= 0 || $schoolGradeClassId <= 0) {
            return 0;
        }

        return StudentGradeClass::query()
            ->where('std_id', $studentId)
            ->where('sch_grd_cls_id', $schoolGradeClassId)
            ->delete();
    }
}
