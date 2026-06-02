<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\SchoolDetail;
use App\Models\TermTestAbsentee;
use App\Models\TermTestMark;
use App\Models\TermTestMarksConfirm;
use App\Models\TermTestResult;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class TermTestMarksController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    private const VIEW_ROLE_IDS = [1, 2, 3, 5, 6, 7, 8];

    private const TERM_OPTIONS = [
        ['id' => 1, 'label' => 'Term Test 1'],
        ['id' => 2, 'label' => 'Term Test 2'],
        ['id' => 3, 'label' => 'Term Test 3'],
    ];

    public function options(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        Log::info('Term test marks options requested.', array_merge(
            $this->marksLogContext($user),
            [
                'requested_year' => is_numeric($request->query('year')) ? (int) $request->query('year') : null,
                'requested_grade_id' => is_numeric($request->query('grade_id')) ? (int) $request->query('grade_id') : null,
            ]
        ));

        if (!$this->canViewMarks($user)) {
            Log::warning('Term test marks options forbidden.', $this->marksLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $censusId = $this->resolveMarksTargetSchoolCensusId($user);
        if ($this->isAdministrator($user) && $censusId === null) {
            Log::info('Term test marks options returned without school context for administrator.', $this->marksLogContext($user));
            return response()->json([
                'schools' => $this->loadSchoolOptions($user),
                'years' => [],
                'terms' => self::TERM_OPTIONS,
                'grades' => [],
                'classes' => [],
                'selected_school_census_id' => null,
                'can_manage' => $this->canManageMarks($user),
                'scope' => null,
            ]);
        }

        if ($censusId === null) {
            Log::warning('Term test marks options blocked: school context missing.', $this->marksLogContext($user));
            return response()->json(['message' => $this->schoolContextRequiredMessage($user)], 422);
        }

        $yearOptions = $this->loadMarksYearOptions($censusId);
        $requestedYear = is_numeric($request->query('year')) ? (int) $request->query('year') : null;
        $selectedYear = $requestedYear !== null && in_array($requestedYear, $yearOptions, true)
            ? $requestedYear
            : ($yearOptions[0] ?? (int) now()->year);

        $scope = $this->resolveMarksScope($user, $censusId, $selectedYear);
        if (($scope['requires_assignment'] ?? false) && empty($scope['allowed_grade_ids'] ?? [])) {
            Log::warning('Term test marks options returned empty scope.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'selected_year' => $selectedYear,
                    'scope' => $scope,
                ]
            ));
            return response()->json([
                'schools' => $this->loadSchoolOptions($user),
                'years' => $yearOptions,
                'terms' => self::TERM_OPTIONS,
                'grades' => [],
                'classes' => [],
                'selected_school_census_id' => $censusId,
                'can_manage' => $this->canManageMarks($user),
                'scope' => $scope,
                'message' => 'No class or grade assignment found for the selected year.',
            ]);
        }

        $gradeOptions = $this->loadMarksGradeOptions($censusId, $selectedYear, $scope);
        $lockedGradeId = $scope['locked_grade_id'] ?? null;
        $selectedGradeId = is_numeric($request->query('grade_id')) ? (int) $request->query('grade_id') : 0;
        if ($lockedGradeId !== null) {
            $selectedGradeId = (int) $lockedGradeId;
        } elseif ($selectedGradeId <= 0 && count($gradeOptions) === 1) {
            $selectedGradeId = (int) $gradeOptions[0]['grade_id'];
        }

        $classOptions = $selectedGradeId > 0
            ? $this->loadMarksClassOptions($censusId, $selectedYear, $selectedGradeId, $scope)
            : [];

        Log::info('Term test marks options loaded.', array_merge(
            $this->marksLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'selected_year' => $selectedYear,
                'selected_grade_id' => $selectedGradeId > 0 ? $selectedGradeId : null,
                'scope' => $scope,
                'grade_option_count' => count($gradeOptions),
                'class_option_count' => count($classOptions),
            ]
        ));

        return response()->json([
            'schools' => $this->loadSchoolOptions($user),
            'years' => $yearOptions,
            'terms' => self::TERM_OPTIONS,
            'grades' => $gradeOptions,
            'classes' => $classOptions,
            'selected_school_census_id' => $censusId,
            'can_manage' => $this->canManageMarks($user),
            'scope' => $scope,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewMarks($user)) {
            Log::warning('Term test marks list forbidden.', $this->marksLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'term' => ['required', 'integer', 'in:1,2,3'],
            'grade_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        Log::info('Term test marks list requested.', array_merge(
            $this->marksLogContext($user),
            [
                'filters' => [
                    'year' => (int) $validated['year'],
                    'term' => (int) $validated['term'],
                    'grade_id' => (int) $validated['grade_id'],
                    'class_id' => (int) $validated['class_id'],
                ],
            ]
        ));

        $censusId = $this->resolveMarksTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Term test marks list blocked: school context missing.', $this->marksLogContext($user));
            return response()->json(['message' => $this->schoolContextRequiredMessage($user)], 422);
        }

        $scope = $this->resolveMarksScope($user, $censusId, (int) $validated['year']);
        $selectionError = $this->validateMarksSelectionAgainstScope($scope, (int) $validated['grade_id'], (int) $validated['class_id']);
        if ($selectionError !== null) {
            Log::warning('Term test marks list blocked by scope.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'scope' => $scope,
                    'selection_error' => $selectionError,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => $selectionError], 403);
        }

        $classRow = $this->loadClassRow($censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id']);
        if ($classRow === null) {
            Log::warning('Term test marks list failed: class row not found.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'Class not found for the selected year.'], 404);
        }

        $subjects = $this->loadMarksSubjects($censusId, (int) $validated['year'], (int) $validated['grade_id']);
        $students = $this->loadMarksRoster((int) $classRow->sch_grd_cls_id);

        if ($this->isStudentMarksViewer($user)) {
            $studentIndexNo = $this->resolveStudentIndexNoForUser($user);
            $students = array_values(array_filter(
                $students,
                fn (array $row): bool => $studentIndexNo !== null && $row['index_no'] === $studentIndexNo
            ));
        }

        $indexNumbers = array_values(array_map(fn (array $row): string => $row['index_no'], $students));
        $subjectIds = array_values(array_map(fn (array $row): int => (int) $row['subject_id'], $subjects));
        $markMap = $this->loadExistingMarkMap($censusId, (int) $validated['year'], (int) $validated['term'], $indexNumbers, $subjectIds);
        $absentMap = $this->loadExistingAbsentMap($censusId, (int) $validated['year'], (int) $validated['term'], $indexNumbers, $subjectIds);
        $resultMap = $this->loadExistingResultMap($censusId, (int) $validated['year'], (int) $validated['term'], $indexNumbers);
        $confirm = $this->loadMarksConfirmation($censusId, (int) $validated['year'], (int) $validated['term'], (int) $validated['grade_id'], (int) $validated['class_id']);

        $rows = array_map(function (array $student) use ($subjects, $markMap, $absentMap, $resultMap): array {
            $indexNo = $student['index_no'];
            $marks = [];

            foreach ($subjects as $subject) {
                $subjectId = (int) $subject['subject_id'];
                $cellKey = "{$indexNo}:{$subjectId}";
                $marks[(string) $subjectId] = isset($absentMap[$cellKey])
                    ? 'AB'
                    : (isset($markMap[$cellKey]) ? (string) $markMap[$cellKey] : '');
            }

            $result = $resultMap[$indexNo] ?? ['total' => null, 'average' => null];

            return [
                'std_id' => $student['std_id'],
                'index_no' => $indexNo,
                'name_with_initials' => $student['name_with_initials'],
                'marks' => $marks,
                'total' => $result['total'],
                'average' => $result['average'],
            ];
        }, $students);

        Log::info('Term test marks list loaded.', array_merge(
            $this->marksLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'scope' => $scope,
                'filters' => $validated,
                'class_row_id' => (int) $classRow->sch_grd_cls_id,
                'subject_count' => count($subjects),
                'roster_count' => count($students),
                'returned_student_count' => count($rows),
                'mark_count' => count($markMap),
                'absent_count' => count($absentMap),
                'result_count' => count($resultMap),
                'can_manage' => $this->canManageMarksForSelection($user, $censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id']),
            ]
        ));

        return response()->json([
            'filters' => [
                'year' => (int) $validated['year'],
                'term' => (int) $validated['term'],
                'grade_id' => (int) $validated['grade_id'],
                'class_id' => (int) $validated['class_id'],
            ],
            'class' => [
                'sch_grd_cls_id' => (int) $classRow->sch_grd_cls_id,
                'grade_id' => (int) $classRow->grade_id,
                'class_id' => (int) $classRow->class_id,
                'grade' => (string) $classRow->grade,
                'class_name' => (string) $classRow->class_name,
                'label' => trim(sprintf('%s %s', (string) $classRow->grade, (string) $classRow->class_name)),
            ],
            'subjects' => $subjects,
            'students' => $rows,
            'can_manage' => $this->canManageMarksForSelection($user, $censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id']),
            'scope' => $scope,
            'confirmation' => $confirm,
            'summary' => [
                'total_students' => count($rows),
                'total_subjects' => count($subjects),
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageMarks($user)) {
            Log::warning('Term test marks save forbidden.', $this->marksLogContext($user));
            return response()->json(['message' => 'Only class teachers can save term test marks.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'term' => ['required', 'integer', 'in:1,2,3'],
            'grade_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.index_no' => ['required', 'string'],
            'entries.*.marks' => ['required', 'array'],
        ])->validate();

        Log::info('Term test marks save requested.', array_merge(
            $this->marksLogContext($user),
            [
                'filters' => [
                    'year' => (int) $validated['year'],
                    'term' => (int) $validated['term'],
                    'grade_id' => (int) $validated['grade_id'],
                    'class_id' => (int) $validated['class_id'],
                ],
                'entry_count' => count($validated['entries'] ?? []),
            ]
        ));

        $censusId = $this->resolveMarksTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Term test marks save blocked: school context missing.', $this->marksLogContext($user));
            return response()->json(['message' => $this->schoolContextRequiredMessage($user)], 422);
        }

        if (!$this->canManageMarksForSelection($user, $censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id'])) {
            Log::warning('Term test marks save blocked by class scope.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'You can only enter marks for your assigned class.'], 403);
        }

        $classRow = $this->loadClassRow($censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id']);
        if ($classRow === null) {
            Log::warning('Term test marks save failed: class row not found.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'Class not found for the selected year.'], 404);
        }

        $subjects = $this->loadMarksSubjects($censusId, (int) $validated['year'], (int) $validated['grade_id']);
        if ($subjects === []) {
            Log::warning('Term test marks save failed: no subjects configured.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'No subjects configured for the selected grade and year.'], 422);
        }

        $roster = $this->loadMarksRoster((int) $classRow->sch_grd_cls_id);
        if ($roster === []) {
            Log::warning('Term test marks save failed: empty roster.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'class_row_id' => (int) $classRow->sch_grd_cls_id,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'No students found in the selected class.'], 422);
        }

        $validIndexes = array_fill_keys(array_map(fn (array $row): string => $row['index_no'], $roster), true);
        $subjectLookup = array_fill_keys(array_map(fn (array $row): int => (int) $row['subject_id'], $subjects), true);
        $entryLookup = [];
        $cellErrors = [];

        foreach ($validated['entries'] as $entry) {
            $indexNo = trim((string) ($entry['index_no'] ?? ''));
            if ($indexNo === '' || !isset($validIndexes[$indexNo])) {
                continue;
            }

            $marks = is_array($entry['marks'] ?? null) ? $entry['marks'] : [];
            $entryLookup[$indexNo] = [];

            foreach ($marks as $subjectId => $value) {
                $resolvedSubjectId = is_numeric($subjectId) ? (int) $subjectId : 0;
                if ($resolvedSubjectId <= 0 || !isset($subjectLookup[$resolvedSubjectId])) {
                    continue;
                }

                $normalized = $this->normalizeMarkCellValue($value);
                if ($normalized === null) {
                    $cellErrors[] = sprintf('Invalid mark for student %s and subject %d. Use 0-100 or AB.', $indexNo, $resolvedSubjectId);
                    continue;
                }

                $entryLookup[$indexNo][$resolvedSubjectId] = $normalized;
            }
        }

        if ($cellErrors !== []) {
            Log::warning('Term test marks save failed validation.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'class_row_id' => (int) $classRow->sch_grd_cls_id,
                    'subject_count' => count($subjects),
                    'roster_count' => count($roster),
                    'first_error' => $cellErrors[0],
                ]
            ));
            return response()->json(['message' => $cellErrors[0]], 422);
        }

        $filledCells = 0;
        $absentCells = 0;
        $numericCells = 0;
        foreach ($entryLookup as $studentMarks) {
            foreach ($studentMarks as $cell) {
                if ($cell === '') {
                    continue;
                }
                $filledCells++;
                if ($cell === 'AB') {
                    $absentCells++;
                } else {
                    $numericCells++;
                }
            }
        }

        DB::transaction(function () use ($censusId, $validated, $roster, $subjects, $entryLookup): void {
            $year = (int) $validated['year'];
            $term = (int) $validated['term'];
            $gradeId = (int) $validated['grade_id'];
            $classId = (int) $validated['class_id'];

            foreach ($roster as $student) {
                $indexNo = $student['index_no'];
                $studentMarks = $entryLookup[$indexNo] ?? [];

                foreach ($subjects as $subject) {
                    $subjectId = (int) $subject['subject_id'];
                    $cell = $studentMarks[$subjectId] ?? '';

                    if ($cell === '') {
                        TermTestMark::query()
                            ->where('census_id', $censusId)
                            ->where('index_no', $indexNo)
                            ->where('year', $year)
                            ->where('term', $term)
                            ->where('subj_id', $subjectId)
                            ->delete();

                        TermTestAbsentee::query()
                            ->where('census_id', $censusId)
                            ->where('index_no', $indexNo)
                            ->where('year', $year)
                            ->where('term', $term)
                            ->where('subj_id', $subjectId)
                            ->delete();

                        continue;
                    }

                    if ($cell === 'AB') {
                        TermTestAbsentee::query()->updateOrCreate(
                            [
                                'census_id' => $censusId,
                                'index_no' => $indexNo,
                                'year' => $year,
                                'term' => $term,
                                'subj_id' => $subjectId,
                            ],
                            []
                        );

                        TermTestMark::query()
                            ->where('census_id', $censusId)
                            ->where('index_no', $indexNo)
                            ->where('year', $year)
                            ->where('term', $term)
                            ->where('subj_id', $subjectId)
                            ->delete();

                        continue;
                    }

                    TermTestMark::query()->updateOrCreate(
                        [
                            'census_id' => $censusId,
                            'index_no' => $indexNo,
                            'year' => $year,
                            'term' => $term,
                            'subj_id' => $subjectId,
                        ],
                        [
                            'marks' => (int) $cell,
                        ]
                    );

                    TermTestAbsentee::query()
                        ->where('census_id', $censusId)
                        ->where('index_no', $indexNo)
                        ->where('year', $year)
                        ->where('term', $term)
                        ->where('subj_id', $subjectId)
                        ->delete();
                }
            }

            $this->rebuildResultsAndConfirmation($censusId, $year, $term, $gradeId, $classId, $roster, $subjects);
        });

        Log::info('Term test marks save completed.', array_merge(
            $this->marksLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'class_row_id' => (int) $classRow->sch_grd_cls_id,
                'filters' => [
                    'year' => (int) $validated['year'],
                    'term' => (int) $validated['term'],
                    'grade_id' => (int) $validated['grade_id'],
                    'class_id' => (int) $validated['class_id'],
                ],
                'roster_count' => count($roster),
                'subject_count' => count($subjects),
                'entry_count' => count($validated['entries'] ?? []),
                'filled_cell_count' => $filledCells,
                'absent_cell_count' => $absentCells,
                'numeric_cell_count' => $numericCells,
            ]
        ));

        return response()->json(['message' => 'Term test marks saved successfully.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageMarks($user)) {
            Log::warning('Term test marks delete forbidden.', $this->marksLogContext($user));
            return response()->json(['message' => 'Only class teachers can delete term test marks.'], 403);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'term' => ['required', 'integer', 'in:1,2,3'],
            'grade_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        Log::info('Term test marks delete requested.', array_merge(
            $this->marksLogContext($user),
            [
                'filters' => [
                    'year' => (int) $validated['year'],
                    'term' => (int) $validated['term'],
                    'grade_id' => (int) $validated['grade_id'],
                    'class_id' => (int) $validated['class_id'],
                ],
            ]
        ));

        $censusId = $this->resolveMarksTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Term test marks delete blocked: school context missing.', $this->marksLogContext($user));
            return response()->json(['message' => $this->schoolContextRequiredMessage($user)], 422);
        }

        if (!$this->canManageMarksForSelection($user, $censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id'])) {
            Log::warning('Term test marks delete blocked by class scope.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'You can only delete marks for your assigned class.'], 403);
        }

        $classRow = $this->loadClassRow($censusId, (int) $validated['year'], (int) $validated['grade_id'], (int) $validated['class_id']);
        if ($classRow === null) {
            Log::warning('Term test marks delete failed: class row not found.', array_merge(
                $this->marksLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'filters' => $validated,
                ]
            ));
            return response()->json(['message' => 'Class not found for the selected year.'], 404);
        }

        $roster = $this->loadMarksRoster((int) $classRow->sch_grd_cls_id);
        $indexNumbers = array_values(array_map(fn (array $row): string => $row['index_no'], $roster));

        DB::transaction(function () use ($censusId, $validated, $indexNumbers): void {
            $year = (int) $validated['year'];
            $term = (int) $validated['term'];
            $gradeId = (int) $validated['grade_id'];
            $classId = (int) $validated['class_id'];

            if ($indexNumbers !== []) {
                TermTestMark::query()
                    ->where('census_id', $censusId)
                    ->where('year', $year)
                    ->where('term', $term)
                    ->whereIn('index_no', $indexNumbers)
                    ->delete();

                TermTestAbsentee::query()
                    ->where('census_id', $censusId)
                    ->where('year', $year)
                    ->where('term', $term)
                    ->whereIn('index_no', $indexNumbers)
                    ->delete();

                TermTestResult::query()
                    ->where('census_id', $censusId)
                    ->where('year', $year)
                    ->where('term', $term)
                    ->whereIn('index_no', $indexNumbers)
                    ->delete();
            }

            TermTestMarksConfirm::query()
                ->where('census_id', $censusId)
                ->where('year', $year)
                ->where('term', $term)
                ->where('grade_id', $gradeId)
                ->where('class_id', $classId)
                ->delete();
        });

        Log::info('Term test marks delete completed.', array_merge(
            $this->marksLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'class_row_id' => (int) $classRow->sch_grd_cls_id,
                'filters' => [
                    'year' => (int) $validated['year'],
                    'term' => (int) $validated['term'],
                    'grade_id' => (int) $validated['grade_id'],
                    'class_id' => (int) $validated['class_id'],
                ],
                'roster_count' => count($roster),
            ]
        ));

        return response()->json(['message' => 'Term test marks deleted successfully.']);
    }

    private function canViewMarks(?User $user): bool
    {
        return $user !== null && in_array((int) ($user->role_id ?? 0), self::VIEW_ROLE_IDS, true);
    }

    private function canManageMarks(?User $user): bool
    {
        return $this->isClassTeacher($user);
    }

    private function isStudentMarksViewer(?User $user): bool
    {
        return $user !== null && (int) ($user->role_id ?? 0) === 7;
    }

    private function isGradeHead(?User $user): bool
    {
        return $user !== null && (int) ($user->role_id ?? 0) === 8;
    }

    private function isSectionalHead(?User $user): bool
    {
        return $user !== null && (int) ($user->role_id ?? 0) === 3;
    }

    private function isPrincipal(?User $user): bool
    {
        return $user !== null && (int) ($user->role_id ?? 0) === 2;
    }

    private function isClerk(?User $user): bool
    {
        return $user !== null && (int) ($user->role_id ?? 0) === 6;
    }

    private function resolveMarksTargetSchoolCensusId(?User $user): ?string
    {
        if ($this->isAdministrator($user)) {
            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMarksScope(?User $user, string $censusId, int $year): array
    {
        if ($this->isClassTeacher($user)) {
            $assignment = $this->resolveClassTeacherAssignment($user);
            if ($assignment === null || (int) $assignment['year'] !== $year) {
                return [
                    'role' => 'class_teacher',
                    'requires_assignment' => true,
                    'allowed_grade_ids' => [],
                    'allowed_class_ids_by_grade' => [],
                    'locked_year' => null,
                    'locked_grade_id' => null,
                    'locked_class_id' => null,
                ];
            }

            return [
                'role' => 'class_teacher',
                'requires_assignment' => true,
                'allowed_grade_ids' => [(int) $assignment['grade_id']],
                'allowed_class_ids_by_grade' => [
                    (int) $assignment['grade_id'] => [(int) $assignment['class_id']],
                ],
                'locked_year' => null,
                'locked_grade_id' => (int) $assignment['grade_id'],
                'locked_class_id' => (int) $assignment['class_id'],
            ];
        }

        if ($this->isGradeHead($user)) {
            $gradeIds = $this->loadGradeHeadGradeIds($user, $censusId, $year);

            return [
                'role' => 'grade_head',
                'requires_assignment' => true,
                'allowed_grade_ids' => $gradeIds,
                'allowed_class_ids_by_grade' => [],
                'locked_year' => null,
                'locked_grade_id' => null,
                'locked_class_id' => null,
            ];
        }

        if ($this->isSectionalHead($user)) {
            $sectionId = $this->loadSectionalHeadSectionId($user, $censusId);
            $gradeIds = $sectionId !== null ? $this->loadSectionGradeIds($sectionId) : [];

            return [
                'role' => 'sectional_head',
                'requires_assignment' => true,
                'section_id' => $sectionId,
                'allowed_grade_ids' => $gradeIds,
                'allowed_class_ids_by_grade' => [],
                'locked_year' => null,
                'locked_grade_id' => null,
                'locked_class_id' => null,
            ];
        }

        if ($this->isStudentMarksViewer($user)) {
            $studentScope = $this->resolveStudentMarksScope($user, $censusId);
            if ($studentScope === null) {
                return [
                    'role' => 'student',
                    'requires_assignment' => true,
                    'allowed_grade_ids' => [],
                    'allowed_class_ids_by_grade' => [],
                    'locked_year' => $year,
                    'locked_grade_id' => null,
                    'locked_class_id' => null,
                ];
            }

            return [
                'role' => 'student',
                'requires_assignment' => true,
                'allowed_grade_ids' => [(int) $studentScope['grade_id']],
                'allowed_class_ids_by_grade' => [
                    (int) $studentScope['grade_id'] => [(int) $studentScope['class_id']],
                ],
                'locked_year' => (int) $studentScope['year'],
                'locked_grade_id' => (int) $studentScope['grade_id'],
                'locked_class_id' => (int) $studentScope['class_id'],
            ];
        }

        if ($this->isPrincipal($user) || $this->isClerk($user) || $this->isAdministrator($user)) {
            return [
                'role' => 'school',
                'requires_assignment' => false,
                'allowed_grade_ids' => [],
                'allowed_class_ids_by_grade' => [],
                'locked_year' => null,
                'locked_grade_id' => null,
                'locked_class_id' => null,
            ];
        }

        return [
            'role' => 'unknown',
            'requires_assignment' => true,
            'allowed_grade_ids' => [],
            'allowed_class_ids_by_grade' => [],
            'locked_year' => null,
            'locked_grade_id' => null,
            'locked_class_id' => null,
        ];
    }

    /**
     * @return array<int, array{grade_id:int,label:string}>
     */
    private function loadMarksGradeOptions(string $censusId, int $year, array $scope): array
    {
        if (!Schema::hasTable('school_grade_tbl') || !Schema::hasTable('grade_tbl')) {
            return [];
        }

        $gradeLabel = $this->resolveLookupLabelColumn('grade_tbl', ['grade_en', 'grade_si', 'grade_ta']);
        if ($gradeLabel === null) {
            return [];
        }

        $query = DB::table('school_grade_tbl as sgt')
            ->join('grade_tbl as gt', 'gt.grade_id', '=', 'sgt.grade_id')
            ->select([
                'sgt.grade_id',
                DB::raw("COALESCE(NULLIF(TRIM(gt.{$gradeLabel}), ''), CONCAT('Grade ', sgt.grade_id)) as label"),
            ])
            ->where('sgt.year', $year)
            ->whereIn('sgt.census_id', $this->censusValues($censusId));

        if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
            $query->where('sgt.is_deleted', 0);
        }

        if (!empty($scope['allowed_grade_ids'] ?? [])) {
            $query->whereIn('sgt.grade_id', array_values($scope['allowed_grade_ids']));
        }

        return $query
            ->distinct()
            ->orderBy('sgt.grade_id')
            ->get()
            ->map(fn ($row): array => [
                'grade_id' => (int) $row->grade_id,
                'label' => (string) $row->label,
            ])
            ->all();
    }

    /**
     * @return array<int, array{class_id:int,label:string}>
     */
    private function loadMarksClassOptions(string $censusId, int $year, int $gradeId, array $scope): array
    {
        if (!Schema::hasTable('school_grade_class_tbl') || !Schema::hasTable('class_tbl')) {
            return [];
        }

        $classLabel = $this->resolveLookupLabelColumn('class_tbl', ['class_en', 'class_si', 'class_ta', 'class']);
        if ($classLabel === null) {
            return [];
        }

        $query = DB::table('school_grade_class_tbl as sgct')
            ->join('class_tbl as ct', 'ct.class_id', '=', 'sgct.class_id')
            ->select([
                'sgct.class_id',
                DB::raw("COALESCE(NULLIF(TRIM(ct.{$classLabel}), ''), CONCAT('Class ', sgct.class_id)) as label"),
            ])
            ->where('sgct.year', $year)
            ->where('sgct.grade_id', $gradeId)
            ->whereIn('sgct.census_id', $this->censusValues($censusId));

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $allowedClassIdsByGrade = $scope['allowed_class_ids_by_grade'] ?? [];
        if (isset($allowedClassIdsByGrade[$gradeId]) && is_array($allowedClassIdsByGrade[$gradeId]) && $allowedClassIdsByGrade[$gradeId] !== []) {
            $query->whereIn('sgct.class_id', array_values($allowedClassIdsByGrade[$gradeId]));
        }

        return $query
            ->distinct()
            ->orderBy('sgct.class_id')
            ->get()
            ->map(fn ($row): array => [
                'class_id' => (int) $row->class_id,
                'label' => (string) $row->label,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function loadMarksYearOptions(string $censusId): array
    {
        if (!Schema::hasTable('school_grade_tbl')) {
            return [];
        }

        $query = DB::table('school_grade_tbl')
            ->whereIn('census_id', $this->censusValues($censusId))
            ->select('year')
            ->distinct()
            ->orderByDesc('year');

        if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->filter(fn (int $year): bool => $year >= 2000 && $year <= 2100)
            ->values()
            ->all();
    }

    private function validateMarksSelectionAgainstScope(array $scope, int $gradeId, int $classId): ?string
    {
        $allowedGradeIds = $scope['allowed_grade_ids'] ?? [];
        if ($allowedGradeIds !== [] && !in_array($gradeId, $allowedGradeIds, true)) {
            return 'You do not have access to the selected grade.';
        }

        $allowedClassIdsByGrade = $scope['allowed_class_ids_by_grade'] ?? [];
        if (
            isset($allowedClassIdsByGrade[$gradeId])
            && is_array($allowedClassIdsByGrade[$gradeId])
            && $allowedClassIdsByGrade[$gradeId] !== []
            && !in_array($classId, $allowedClassIdsByGrade[$gradeId], true)
        ) {
            return 'You do not have access to the selected class.';
        }

        return null;
    }

    private function canManageMarksForSelection(?User $user, string $censusId, int $year, int $gradeId, int $classId): bool
    {
        if (!$this->canManageMarks($user)) {
            return false;
        }

        $scope = $this->resolveMarksScope($user, $censusId, $year);

        return $this->validateMarksSelectionAgainstScope($scope, $gradeId, $classId) === null;
    }

    private function loadClassRow(string $censusId, int $year, int $gradeId, int $classId): ?object
    {
        if (!Schema::hasTable('school_grade_class_tbl') || !Schema::hasTable('grade_tbl') || !Schema::hasTable('class_tbl')) {
            return null;
        }

        $gradeLabel = $this->resolveLookupLabelColumn('grade_tbl', ['grade_en', 'grade_si', 'grade_ta']);
        $classLabel = $this->resolveLookupLabelColumn('class_tbl', ['class_en', 'class_si', 'class_ta', 'class']);
        if ($gradeLabel === null || $classLabel === null) {
            return null;
        }

        $query = DB::table('school_grade_class_tbl as sgct')
            ->join('grade_tbl as gt', 'gt.grade_id', '=', 'sgct.grade_id')
            ->join('class_tbl as ct', 'ct.class_id', '=', 'sgct.class_id')
            ->select([
                'sgct.sch_grd_cls_id',
                'sgct.grade_id',
                'sgct.class_id',
                DB::raw("COALESCE(NULLIF(TRIM(gt.{$gradeLabel}), ''), CONCAT('Grade ', sgct.grade_id)) as grade"),
                DB::raw("COALESCE(NULLIF(TRIM(ct.{$classLabel}), ''), CONCAT('Class ', sgct.class_id)) as class_name"),
            ])
            ->where('sgct.year', $year)
            ->where('sgct.grade_id', $gradeId)
            ->where('sgct.class_id', $classId)
            ->whereIn('sgct.census_id', $this->censusValues($censusId));

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        return $query->orderByDesc('sgct.sch_grd_cls_id')->first();
    }

    /**
     * @return array<int, array{subject_id:int,subject:string,order_id:int}>
     */
    private function loadMarksSubjects(string $censusId, int $year, int $gradeId): array
    {
        if (!Schema::hasTable('subjects_grade_tbl') || !Schema::hasTable('subject_tbl')) {
            return [];
        }

        if ($this->resolveLookupLabelColumn('subject_tbl', ['subject_en', 'subject_si', 'subject_ta']) === null) {
            return [];
        }

        return DB::table('subjects_grade_tbl as sgt')
            ->join('subject_tbl as st', 'st.subject_id', '=', 'sgt.subject_id')
            ->select([
                'sgt.subject_id as subject_id',
                'sgt.order_id',
                DB::raw("COALESCE(NULLIF(TRIM(st.subject_en), ''), NULLIF(TRIM(st.subject_si), ''), NULLIF(TRIM(st.subject_ta), ''), CONCAT('Subject ', sgt.subject_id)) as subject"),
            ])
            ->where('sgt.grade_id', $gradeId)
            ->where('sgt.year', $year)
            ->whereIn('sgt.census_id', $this->censusValues($censusId))
            ->orderBy('sgt.order_id')
            ->orderBy('sgt.subject_id')
            ->get()
            ->map(fn ($row): array => [
                'subject_id' => (int) $row->subject_id,
                'subject' => (string) $row->subject,
                'order_id' => (int) $row->order_id,
            ])
            ->all();
    }

    /**
     * @return array<int, array{std_id:int,index_no:string,name_with_initials:string}>
     */
    private function loadMarksRoster(int $schoolGradeClassId): array
    {
        if (!Schema::hasTable('student_grade_class_tbl') || !Schema::hasTable('student_tbl')) {
            return [];
        }

        $query = DB::table('student_grade_class_tbl as sgc')
            ->join('student_tbl as st', 'st.std_id', '=', 'sgc.std_id')
            ->select([
                'st.std_id',
                'st.index_no',
                'st.name_with_initials',
            ])
            ->where('sgc.sch_grd_cls_id', $schoolGradeClassId);

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        return $query
            ->orderByRaw('CAST(st.index_no AS UNSIGNED) ASC')
            ->orderBy('st.index_no')
            ->get()
            ->map(fn ($row): array => [
                'std_id' => (int) $row->std_id,
                'index_no' => (string) $row->index_no,
                'name_with_initials' => trim((string) ($row->name_with_initials ?? '')),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $indexNumbers
     * @param  array<int, int>  $subjectIds
     * @return array<string, int>
     */
    private function loadExistingMarkMap(string $censusId, int $year, int $term, array $indexNumbers, array $subjectIds): array
    {
        if ($indexNumbers === [] || $subjectIds === []) {
            return [];
        }

        return TermTestMark::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->whereIn('index_no', $indexNumbers)
            ->whereIn('subj_id', $subjectIds)
            ->get()
            ->reduce(function (array $carry, TermTestMark $row): array {
                $carry["{$row->index_no}:{$row->subj_id}"] = (int) $row->marks;
                return $carry;
            }, []);
    }

    /**
     * @param  array<int, string>  $indexNumbers
     * @param  array<int, int>  $subjectIds
     * @return array<string, true>
     */
    private function loadExistingAbsentMap(string $censusId, int $year, int $term, array $indexNumbers, array $subjectIds): array
    {
        if ($indexNumbers === [] || $subjectIds === []) {
            return [];
        }

        return TermTestAbsentee::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->whereIn('index_no', $indexNumbers)
            ->whereIn('subj_id', $subjectIds)
            ->get()
            ->reduce(function (array $carry, TermTestAbsentee $row): array {
                $carry["{$row->index_no}:{$row->subj_id}"] = true;
                return $carry;
            }, []);
    }

    /**
     * @param  array<int, string>  $indexNumbers
     * @return array<string, array{total:int,average:float}>
     */
    private function loadExistingResultMap(string $censusId, int $year, int $term, array $indexNumbers): array
    {
        if ($indexNumbers === []) {
            return [];
        }

        return TermTestResult::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->whereIn('index_no', $indexNumbers)
            ->get()
            ->reduce(function (array $carry, TermTestResult $row): array {
                $carry[(string) $row->index_no] = [
                    'total' => (int) $row->total,
                    'average' => (float) $row->average,
                ];
                return $carry;
            }, []);
    }

    private function loadMarksConfirmation(string $censusId, int $year, int $term, int $gradeId, int $classId): array
    {
        $row = TermTestMarksConfirm::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->where('grade_id', $gradeId)
            ->where('class_id', $classId)
            ->first();

        return [
            'is_completed' => (bool) ($row?->is_completed ?? false),
        ];
    }

    /**
     * @param  array<int, array{std_id:int,index_no:string,name_with_initials:string}>  $roster
     * @param  array<int, array{subject_id:int,subject:string,order_id:int}>  $subjects
     */
    private function rebuildResultsAndConfirmation(string $censusId, int $year, int $term, int $gradeId, int $classId, array $roster, array $subjects): void
    {
        $indexNumbers = array_values(array_map(fn (array $row): string => $row['index_no'], $roster));
        $subjectIds = array_values(array_map(fn (array $row): int => (int) $row['subject_id'], $subjects));

        $markRows = TermTestMark::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->whereIn('index_no', $indexNumbers)
            ->whereIn('subj_id', $subjectIds)
            ->get()
            ->groupBy('index_no');

        $absentRows = TermTestAbsentee::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->whereIn('index_no', $indexNumbers)
            ->whereIn('subj_id', $subjectIds)
            ->get()
            ->groupBy('index_no');

        foreach ($indexNumbers as $indexNo) {
            $marksForStudent = $markRows->get($indexNo);
            $absentsForStudent = $absentRows->get($indexNo);

            $markCount = $marksForStudent?->count() ?? 0;
            $absentCount = $absentsForStudent?->count() ?? 0;

            if ($markCount === 0 && $absentCount === 0) {
                TermTestResult::query()
                    ->where('census_id', $censusId)
                    ->where('year', $year)
                    ->where('term', $term)
                    ->where('index_no', $indexNo)
                    ->delete();

                continue;
            }

            $total = (int) ($marksForStudent?->sum('marks') ?? 0);
            $average = $markCount > 0
                ? round($total / $markCount, 2)
                : 0.00;

            TermTestResult::query()->updateOrCreate(
                [
                    'census_id' => $censusId,
                    'year' => $year,
                    'term' => $term,
                    'index_no' => $indexNo,
                ],
                [
                    'total' => $total,
                    'average' => $average,
                ]
            );
        }

        $expectedCells = count($indexNumbers) * count($subjectIds);
        $completedCells = TermTestMark::query()
            ->where('census_id', $censusId)
            ->where('year', $year)
            ->where('term', $term)
            ->whereIn('index_no', $indexNumbers)
            ->whereIn('subj_id', $subjectIds)
            ->count()
            + TermTestAbsentee::query()
                ->where('census_id', $censusId)
                ->where('year', $year)
                ->where('term', $term)
                ->whereIn('index_no', $indexNumbers)
                ->whereIn('subj_id', $subjectIds)
                ->count();

        TermTestMarksConfirm::query()->updateOrCreate(
            [
                'census_id' => $censusId,
                'grade_id' => $gradeId,
                'class_id' => $classId,
                'year' => $year,
                'term' => $term,
            ],
            [
                'is_completed' => $expectedCells > 0 && $completedCells >= $expectedCells ? 1 : 0,
            ]
        );
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    private function loadSchoolOptions(?User $user): array
    {
        if (!Schema::hasTable('school_details_tbl')) {
            return [];
        }

        $query = SchoolDetail::query()->orderBy('sch_name');
        if (Schema::hasColumn('school_details_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if (!$this->isAdministrator($user)) {
            $censusId = $this->resolveUserCensusId($user);
            if ($censusId === null) {
                return [];
            }

            $query->whereIn('census_id', $this->censusValues($censusId));
        }

        return $query->get(['census_id', 'sch_name'])
            ->map(fn ($school): array => [
                'id' => (string) $school->census_id,
                'label' => (string) $school->sch_name,
            ])
            ->values()
            ->all();
    }

    private function schoolContextRequiredMessage(?User $user): string
    {
        return $this->isAdministrator($user)
            ? 'Select a school first.'
            : 'School context could not be resolved.';
    }

    /**
     * @return array<int, int>
     */
    private function loadGradeHeadGradeIds(?User $user, string $censusId, int $year): array
    {
        if ($user === null || !Schema::hasTable('school_grade_tbl')) {
            return [];
        }

        $staffId = $this->resolveUserStaffId($user);
        if ($staffId === null) {
            return [];
        }

        $query = DB::table('school_grade_tbl')
            ->whereIn('census_id', $this->censusValues($censusId))
            ->where('year', $year)
            ->where('stf_id', $staffId);

        if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        return $query->pluck('grade_id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();
    }

    private function loadSectionalHeadSectionId(?User $user, string $censusId): ?int
    {
        if ($user === null || !Schema::hasTable('staff_tbl')) {
            return null;
        }

        $staffId = $this->resolveUserStaffId($user);
        if ($staffId === null) {
            return null;
        }

        $query = DB::table('staff_tbl')
            ->where('stf_id', $staffId)
            ->whereIn('census_id', $this->censusValues($censusId));

        if (Schema::hasColumn('staff_tbl', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        $sectionId = $query->value('sec_id');

        return is_numeric($sectionId) && (int) $sectionId > 0 ? (int) $sectionId : null;
    }

    /**
     * @return array<int, int>
     */
    private function loadSectionGradeIds(int $sectionId): array
    {
        if (!Schema::hasTable('grade_tbl')) {
            return [];
        }

        return DB::table('grade_tbl')
            ->where('section_id', $sectionId)
            ->pluck('grade_id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();
    }

    /**
     * @return array{year:int,grade_id:int,class_id:int}|null
     */
    private function resolveStudentMarksScope(?User $user, string $censusId): ?array
    {
        if (
            $user === null
            || !Schema::hasTable('student_tbl')
            || !Schema::hasTable('student_grade_class_tbl')
            || !Schema::hasTable('school_grade_class_tbl')
        ) {
            return null;
        }

        $indexNo = $this->resolveStudentIndexNoForUser($user);
        if ($indexNo === null) {
            return null;
        }

        $query = DB::table('student_tbl as st')
            ->join('student_grade_class_tbl as sgc', 'sgc.std_id', '=', 'st.std_id')
            ->join('school_grade_class_tbl as sgct', 'sgct.sch_grd_cls_id', '=', 'sgc.sch_grd_cls_id')
            ->select([
                'sgct.year',
                'sgct.grade_id',
                'sgct.class_id',
            ])
            ->where('st.index_no', $indexNo)
            ->whereIn('st.census_id', $this->censusValues($censusId))
            ->whereIn('sgct.census_id', $this->censusValues($censusId));

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }
        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }
        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $row = $query
            ->orderByDesc('sgct.year')
            ->orderByDesc('sgct.sch_grd_cls_id')
            ->first();

        if (
            $row === null
            || !is_numeric($row->year ?? null)
            || !is_numeric($row->grade_id ?? null)
            || !is_numeric($row->class_id ?? null)
        ) {
            return null;
        }

        return [
            'year' => (int) $row->year,
            'grade_id' => (int) $row->grade_id,
            'class_id' => (int) $row->class_id,
        ];
    }

    private function resolveStudentIndexNoForUser(User $user): ?string
    {
        ['index_no' => $indexNo] = $this->resolveStudentLoginIdentityFromUsername($user->username ?? null);

        return $indexNo !== '' ? $indexNo : null;
    }

    private function normalizeMarkCellValue(mixed $value): string|int|null
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (strtoupper($text) === 'AB') {
            return 'AB';
        }

        if (!preg_match('/^\d{1,3}$/', $text)) {
            return null;
        }

        $numeric = (int) $text;

        return ($numeric >= 0 && $numeric <= 100) ? $numeric : null;
    }

    /**
     * @return array<int, string>
     */
    private function censusValues(string $censusId): array
    {
        return array_values(array_unique($this->censusCandidates($censusId)));
    }

    private function marksLogContext(?User $user): array
    {
        return [
            'user_id' => $user?->user_id ?? null,
            'role_id' => $user?->role_id ?? null,
            'role_name' => $user?->role?->role_name ?? null,
            'username' => $user?->username ?? null,
            'requested_school_census_id' => $this->resolveRequestedSchoolCensusId($user),
            'resolved_user_census_id' => $this->resolveUserCensusId($user),
        ];
    }
}
