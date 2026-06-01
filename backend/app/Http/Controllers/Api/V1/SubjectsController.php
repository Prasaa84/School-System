<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AppliesSchoolScope;
use App\Http\Controllers\Api\V1\Concerns\ResolvesLocalizedLookupLabels;
use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\GradeSubject;
use App\Models\SchoolDetail;
use App\Models\User;
use App\Services\ExcelReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubjectsController extends Controller
{
    use AppliesSchoolScope;
    use ResolvesLocalizedLookupLabels;

    public function options(): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewSubjects($user)) {
            Log::warning('Subjects options forbidden.', $this->subjectLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $selectedCensusId = $this->resolveTargetSchoolCensusId($user);
        $scope = $this->resolveRestrictedSubjectScope($user);

        Log::info('Subjects options requested.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $selectedCensusId,
                'restricted_scope' => $scope,
                'can_manage' => $this->canManageSubjects($user),
            ]
        ));

        if ($this->isRestrictedSubjectViewer($user) && $scope === null) {
            Log::warning('Subjects options returned empty: restricted viewer has no class scope.', array_merge(
                $this->subjectLogContext($user),
                ['resolved_school_census_id' => $selectedCensusId]
            ));

            return response()->json([
                'schools' => $this->loadSchoolOptions($user),
                'grades' => [],
                'years' => [],
                'selected_school_census_id' => $selectedCensusId,
                'can_manage' => false,
                'locked_grade_id' => null,
                'locked_year' => null,
            ]);
        }

        return response()->json([
            'schools' => $this->loadSchoolOptions($user),
            'grades' => $this->loadGradeOptions($selectedCensusId, $scope),
            'years' => $this->loadYearOptions($selectedCensusId, $scope),
            'selected_school_census_id' => $selectedCensusId,
            'can_manage' => $this->canManageSubjects($user),
            'can_report' => $this->canViewSubjectReport($user),
            'locked_grade_id' => $scope['grade_id'] ?? null,
            'locked_year' => $scope['year'] ?? null,
        ]);
    }

    public function subjects(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewSubjects($user)) {
            Log::warning('Subjects list forbidden.', $this->subjectLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Subjects list blocked: school context missing.', $this->subjectLogContext($user));
            return response()->json([
                'message' => $this->schoolContextRequiredMessage($user),
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $scope = $this->resolveRestrictedSubjectScope($user);
        if ($this->isRestrictedSubjectViewer($user) && $scope === null) {
            Log::warning('Subjects list blocked: restricted viewer has no class scope.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'requested_year' => (int) $validated['year'],
                    'requested_grade_id' => (int) $validated['grade_id'],
                ]
            ));

            return response()->json(['message' => 'No class assignment found.'], 422);
        }

        Log::info('Subjects list requested.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'requested_year' => (int) $validated['year'],
                'requested_grade_id' => (int) $validated['grade_id'],
                'restricted_scope' => $scope,
            ]
        ));

        if ($scope !== null) {
            $validated['grade_id'] = (int) $scope['grade_id'];
            $validated['year'] = (int) $scope['year'];
        }

        $grade = Grade::query()->find((int) $validated['grade_id']);
        if ($grade === null) {
            Log::warning('Subjects list failed: grade not found.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                ]
            ));
            return response()->json(['message' => 'Grade not found.'], 404);
        }

        $sectionId = is_numeric($grade->section_id ?? null) ? (int) $grade->section_id : 0;
        if ($sectionId <= 0) {
            Log::warning('Subjects list failed: grade section missing.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                ]
            ));
            return response()->json(['message' => 'This grade does not have a section.'], 422);
        }

        $selectedIds = GradeSubject::query()
            ->whereIn('census_id', $this->censusValues($censusId))
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('year', (int) $validated['year'])
            ->orderBy('order_id')
            ->pluck('subject_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $selectedLookup = array_fill_keys($selectedIds, true);
        $subjects = $this->loadSectionSubjects($sectionId);

        $rows = array_map(
            fn (array $row): array => [
                'subject_id' => (int) $row['subject_id'],
                'subject' => (string) $row['subject'],
                'category' => (string) $row['category'],
                'selected' => isset($selectedLookup[(int) $row['subject_id']]),
                'year' => (int) $validated['year'],
                'grade_id' => (int) $validated['grade_id'],
                'grade' => $this->gradeLabel($grade),
            ],
            $subjects,
        );

        if ($scope !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => (bool) ($row['selected'] ?? false)
            ));
        }

        Log::info('Subjects list completed.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'grade_id' => (int) $validated['grade_id'],
                'year' => (int) $validated['year'],
                'section_id' => $sectionId,
                'selected_subject_count' => count($selectedIds),
                'returned_subject_count' => count($rows),
                'can_manage' => $this->canManageSubjects($user),
            ]
        ));

        return response()->json([
            'filters' => [
                'year' => (int) $validated['year'],
                'grade_id' => (int) $validated['grade_id'],
            ],
            'grade' => [
                'grade_id' => (int) $grade->grade_id,
                'label' => $this->gradeLabel($grade),
                'section_id' => $sectionId,
            ],
            'data' => $rows,
            'can_manage' => $this->canManageSubjects($user),
        ]);
    }

    public function downloadSubjects(Request $request, ExcelReportService $excelReportService): StreamedResponse|JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canExportSubjects($user)) {
            Log::warning('Subjects export forbidden.', $this->subjectLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Subjects export blocked: school context missing.', $this->subjectLogContext($user));
            return response()->json([
                'message' => $this->schoolContextRequiredMessage($user),
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $scope = $this->resolveRestrictedSubjectScope($user);
        if ($this->isRestrictedSubjectViewer($user) && $scope === null) {
            Log::warning('Subjects export blocked: restricted viewer has no class scope.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'requested_year' => (int) $validated['year'],
                    'requested_grade_id' => (int) $validated['grade_id'],
                ]
            ));

            return response()->json(['message' => 'No class assignment found.'], 422);
        }

        Log::info('Subjects export requested.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'requested_year' => (int) $validated['year'],
                'requested_grade_id' => (int) $validated['grade_id'],
                'restricted_scope' => $scope,
            ]
        ));

        if ($scope !== null) {
            $validated['grade_id'] = (int) $scope['grade_id'];
            $validated['year'] = (int) $scope['year'];
        }

        $grade = Grade::query()->find((int) $validated['grade_id']);
        if ($grade === null) {
            Log::warning('Subjects export failed: grade not found.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                ]
            ));

            return response()->json(['message' => 'Grade not found.'], 404);
        }

        $sectionId = is_numeric($grade->section_id ?? null) ? (int) $grade->section_id : 0;
        if ($sectionId <= 0) {
            Log::warning('Subjects export failed: grade section missing.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                ]
            ));

            return response()->json(['message' => 'This grade does not have a section.'], 422);
        }

        $selectedIds = GradeSubject::query()
            ->whereIn('census_id', $this->censusValues($censusId))
            ->where('grade_id', (int) $validated['grade_id'])
            ->where('year', (int) $validated['year'])
            ->orderBy('order_id')
            ->pluck('subject_id')
            ->map(fn ($value): int => (int) $value)
            ->all();

        $selectedLookup = array_fill_keys($selectedIds, true);
        $subjects = $this->loadSectionSubjects($sectionId);
        $rows = array_map(
            fn (array $row): array => [
                'subject_id' => (int) $row['subject_id'],
                'subject' => (string) $row['subject'],
                'category' => (string) $row['category'],
                'selected' => isset($selectedLookup[(int) $row['subject_id']]),
                'year' => (int) $validated['year'],
                'grade_id' => (int) $validated['grade_id'],
                'grade' => $this->gradeLabel($grade),
            ],
            $subjects,
        );

        if ($scope !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => (bool) ($row['selected'] ?? false)
            ));
        }

        $schoolName = $this->loadSchoolName($censusId);
        $mainHeading = trim($schoolName) !== '' ? ($schoolName . ' - Subjects') : 'Subjects';
        $reportHeading = trim($this->gradeLabel($grade) . ' | Year ' . (int) $validated['year']);
        $yearSuffix = (string) (int) $validated['year'];
        $gradeSuffix = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->gradeLabel($grade)) ?: ('grade-' . (int) $validated['grade_id']);
        $filename = 'subjects-' . strtolower($gradeSuffix) . '-' . $yearSuffix . '.xlsx';
        $printedBy = trim((string) ($user->username ?? ''));
        $printedByRole = trim((string) ($user->role_name ?? $user->role?->role_name ?? ''));
        $printedByLabel = trim($printedBy . ($printedByRole !== '' ? ' - ' . $printedByRole : ''));

        $headers = [
            'No.',
            'Subject',
            'Selected',
            'Category',
            'Year',
            'Grade',
        ];

        $excelRows = array_map(fn (array $row, int $index): array => [
            ['value' => (string) ($index + 1), 'type' => 'string'],
            ['value' => (string) ($row['subject'] ?? ''), 'type' => 'string'],
            ['value' => ($row['selected'] ?? false) ? 'Yes' : 'No', 'type' => 'string'],
            ['value' => (string) ($row['category'] ?? ''), 'type' => 'string'],
            ['value' => (string) ($row['year'] ?? ''), 'type' => 'string'],
            ['value' => (string) ($row['grade'] ?? ''), 'type' => 'string'],
        ], array_values($rows), array_keys(array_values($rows)));

        $summaryRow = [
            ['value' => '', 'type' => 'string'],
            ['value' => '', 'type' => 'string'],
            ['value' => 'Selected Count', 'type' => 'string', 'bold' => true],
            ['value' => (string) count(array_filter($rows, fn (array $row): bool => (bool) ($row['selected'] ?? false))), 'type' => 'string', 'bold' => true],
            ['value' => '', 'type' => 'string'],
            ['value' => '', 'type' => 'string'],
        ];

        Log::info('Subjects export completed.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'grade_id' => (int) $validated['grade_id'],
                'year' => (int) $validated['year'],
                'exported_subject_count' => count($rows),
                'selected_subject_count' => count(array_filter($rows, fn (array $row): bool => (bool) ($row['selected'] ?? false))),
            ]
        ));

        return $excelReportService->streamTableReport(
            $filename,
            'Subjects',
            $mainHeading,
            $reportHeading,
            $headers,
            $excelRows,
            $summaryRow,
            [
                'Printed By: ' . $printedByLabel . ' | Printed At: ' . now()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function report(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canViewSubjectReport($user)) {
            Log::warning('Subjects report forbidden.', $this->subjectLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Subjects report blocked: school context missing.', $this->subjectLogContext($user));
            return response()->json([
                'message' => $this->schoolContextRequiredMessage($user),
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $gradeId = (int) $validated['grade_id'];

        if (Grade::query()->find($gradeId) === null) {
            Log::warning('Subjects report failed: grade not found.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => $gradeId,
                    'year' => (int) $validated['year'],
                ]
            ));

            return response()->json(['message' => 'Grade not found.'], 404);
        }

        Log::info('Subjects report requested.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'requested_year' => (int) $validated['year'],
                'requested_grade_id' => $gradeId,
            ]
        ));

        $rows = $this->loadAssignedSubjectRows($censusId, (int) $validated['year'], $gradeId);

        Log::info('Subjects report completed.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'grade_id' => $gradeId,
                'year' => (int) $validated['year'],
                'returned_subject_count' => count($rows),
            ]
        ));

        return response()->json([
            'filters' => [
                'year' => (int) $validated['year'],
                'grade_id' => (int) $gradeId,
            ],
            'data' => $rows,
            'can_export' => $this->canExportSubjectReport($user),
        ]);
    }

    public function downloadReport(Request $request, ExcelReportService $excelReportService): StreamedResponse|JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canExportSubjectReport($user)) {
            Log::warning('Subjects report export forbidden.', $this->subjectLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Subjects report export blocked: school context missing.', $this->subjectLogContext($user));
            return response()->json([
                'message' => $this->schoolContextRequiredMessage($user),
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'min:1'],
        ])->validate();

        $gradeId = (int) $validated['grade_id'];
        $grade = Grade::query()->find($gradeId);

        if ($grade === null) {
            Log::warning('Subjects report export failed: grade not found.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => $gradeId,
                    'year' => (int) $validated['year'],
                ]
            ));

            return response()->json(['message' => 'Grade not found.'], 404);
        }

        $rows = $this->loadAssignedSubjectRows($censusId, (int) $validated['year'], $gradeId);
        $schoolName = $this->loadSchoolName($censusId);
        $mainHeading = trim($schoolName) !== '' ? ($schoolName . ' - Subjects Report') : 'Subjects Report';
        $reportHeading = trim($this->gradeLabel($grade) . ' | Year ' . (int) $validated['year']);
        $gradeSuffix = preg_replace('/[^A-Za-z0-9_-]+/', '-', $this->gradeLabel($grade)) ?: ('grade-' . $gradeId);
        $filename = 'subjects-report-' . strtolower($gradeSuffix) . '-' . (int) $validated['year'] . '.xlsx';
        $printedBy = trim((string) ($user->username ?? ''));
        $printedByRole = trim((string) ($user->role_name ?? $user->role?->role_name ?? ''));
        $printedByLabel = trim($printedBy . ($printedByRole !== '' ? ' - ' . $printedByRole : ''));
        $headers = ['No.', 'Subject', 'Category'];

        $excelRows = array_map(function (array $row, int $index): array {
            return [
                ['value' => (string) ($index + 1), 'type' => 'string'],
                ['value' => (string) ($row['subject'] ?? ''), 'type' => 'string'],
                ['value' => (string) ($row['category'] ?? ''), 'type' => 'string'],
            ];
        }, array_values($rows), array_keys(array_values($rows)));

        $summaryRow = [
            ['value' => '', 'type' => 'string'],
            ['value' => 'Assigned Subject Count', 'type' => 'string', 'bold' => true],
            ['value' => (string) count($rows), 'type' => 'string', 'bold' => true],
        ];

        Log::info('Subjects report export completed.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'grade_id' => $gradeId,
                'year' => (int) $validated['year'],
                'exported_subject_count' => count($rows),
            ]
        ));

        return $excelReportService->streamTableReport(
            $filename,
            'Subjects Report',
            $mainHeading,
            $reportHeading,
            $headers,
            $excelRows,
            $summaryRow,
            [
                'Printed By: ' . $printedByLabel . ' | Printed At: ' . now()->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function saveSubjects(Request $request): JsonResponse
    {
        $user = $this->authUser();
        if ($user === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (!$this->canManageSubjects($user)) {
            Log::warning('Subjects save forbidden.', $this->subjectLogContext($user));
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $censusId = $this->resolveTargetSchoolCensusId($user);
        if ($censusId === null) {
            Log::warning('Subjects save blocked: school context missing.', $this->subjectLogContext($user));
            return response()->json([
                'message' => $this->schoolContextRequiredMessage($user),
            ], 422);
        }

        $validated = Validator::make($request->all(), [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'grade_id' => ['required', 'integer', 'min:1'],
            'subject_ids' => ['required', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'min:1'],
        ], [
            'subject_ids.required' => 'Please select subjects.',
            'subject_ids.min' => 'Please select subjects.',
        ])->validate();

        Log::info('Subjects save requested.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'grade_id' => (int) $validated['grade_id'],
                'year' => (int) $validated['year'],
                'submitted_subject_count' => count($validated['subject_ids'] ?? []),
            ]
        ));

        $grade = Grade::query()->find((int) $validated['grade_id']);
        if ($grade === null) {
            Log::warning('Subjects save failed: grade not found.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                ]
            ));
            return response()->json(['message' => 'Grade not found.'], 404);
        }

        $sectionId = is_numeric($grade->section_id ?? null) ? (int) $grade->section_id : 0;
        if ($sectionId <= 0) {
            Log::warning('Subjects save failed: grade section missing.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                ]
            ));
            return response()->json(['message' => 'This grade does not have a section.'], 422);
        }

        $availableSubjects = collect($this->loadSectionSubjects($sectionId))->keyBy('subject_id');
        $subjectIds = collect($validated['subject_ids'] ?? [])
            ->map(fn ($value): int => (int) $value)
            ->filter(fn (int $value): bool => $value > 0 && $availableSubjects->has($value))
            ->unique()
            ->values()
            ->all();

        if ($subjectIds === []) {
            Log::warning('Subjects save failed: no valid subjects selected after filtering.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                    'submitted_subject_ids' => array_values($validated['subject_ids'] ?? []),
                ]
            ));
            return response()->json(['message' => 'Please select subjects.'], 422);
        }

        $ruleError = $this->validateGradeSubjectRules((int) $validated['grade_id'], $subjectIds, $availableSubjects->all());
        if ($ruleError !== null) {
            Log::warning('Subjects save failed: rule validation blocked request.', array_merge(
                $this->subjectLogContext($user),
                [
                    'resolved_school_census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'year' => (int) $validated['year'],
                    'subject_count' => count($subjectIds),
                    'rule_error' => $ruleError,
                ]
            ));
            return response()->json(['message' => $ruleError], 422);
        }

        $now = now();
        DB::transaction(function () use ($censusId, $validated, $subjectIds, $now): void {
            GradeSubject::query()
                ->whereIn('census_id', $this->censusValues($censusId))
                ->where('grade_id', (int) $validated['grade_id'])
                ->where('year', (int) $validated['year'])
                ->delete();

            foreach (array_values($subjectIds) as $index => $subjectId) {
                GradeSubject::query()->create([
                    'census_id' => $censusId,
                    'grade_id' => (int) $validated['grade_id'],
                    'subject_id' => (int) $subjectId,
                    'year' => (int) $validated['year'],
                    'order_id' => $index + 1,
                    'date_added' => $now,
                    'date_updated' => $now,
                ]);
            }
        });

        Log::info('Subjects save completed.', array_merge(
            $this->subjectLogContext($user),
            [
                'resolved_school_census_id' => $censusId,
                'grade_id' => (int) $validated['grade_id'],
                'year' => (int) $validated['year'],
                'saved_subject_count' => count($subjectIds),
                'saved_subject_ids' => $subjectIds,
            ]
        ));

        return response()->json([
            'message' => 'Subjects saved successfully.',
        ]);
    }

    private function canViewSubjects(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2, 5, 6, 7], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal', 'class teacher', 'class_teacher', 'classteacher', 'clerk', 'student'], true);
    }

    private function canManageSubjects(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal'], true);
    }

    private function canViewSubjectReport(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [1, 2, 6], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['admin', 'administrator', 'principal', 'clerk'], true);
    }

    private function canExportSubjectReport(?User $user): bool
    {
        return $this->canViewSubjectReport($user);
    }

    private function canExportSubjects(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $roleId = (int) ($user->role_id ?? 0);
        if (in_array($roleId, [2, 5, 6], true)) {
            return true;
        }

        $roleName = strtolower(trim((string) ($user->role?->role_name ?? '')));

        return in_array($roleName, ['principal', 'class teacher', 'class_teacher', 'classteacher', 'clerk'], true);
    }

    private function isRestrictedSubjectViewer(?User $user): bool
    {
        return $this->isClassTeacher($user) || $this->isStudentUser($user);
    }

    private function resolveTargetSchoolCensusId(?User $user): ?string
    {
        if ($this->isAdministrator($user)) {
            return $this->resolveRequestedSchoolCensusId($user);
        }

        return $this->resolveUserCensusId($user);
    }

    private function schoolContextRequiredMessage(?User $user): string
    {
        return $this->isAdministrator($user)
            ? 'Select a school first.'
            : 'School is not assigned for this user.';
    }

    /**
     * @return array<int, array{id:string,label:string}>
     */
    private function loadSchoolOptions(?User $user): array
    {
        if (!$this->isAdministrator($user) || !Schema::hasTable((new SchoolDetail())->getTable())) {
            return [];
        }

        $availableColumns = array_values(array_filter(
            ['school_name_en', 'school_name_si', 'school_name_ta', 'school_name'],
            fn (string $column): bool => Schema::hasColumn('school_details_tbl', $column),
        ));

        if ($availableColumns === []) {
            return [];
        }

        return SchoolDetail::query()
            ->when(Schema::hasColumn('school_details_tbl', 'is_deleted'), fn ($query) => $query->where('is_deleted', 0))
            ->orderBy('census_id')
            ->get(['census_id', ...$availableColumns])
            ->map(function (SchoolDetail $row) use ($availableColumns): array {
                foreach ($availableColumns as $column) {
                    $label = trim((string) ($row->{$column} ?? ''));
                    if ($label !== '') {
                        return [
                            'id' => (string) $row->census_id,
                            'label' => $label,
                        ];
                    }
                }

                return [
                    'id' => (string) $row->census_id,
                    'label' => (string) $row->census_id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{grade_id:int,label:string}>
     */
    private function loadGradeOptions(?string $censusId, ?array $scope = null): array
    {
        if (!Schema::hasTable('grade_tbl')) {
            return [];
        }

        $query = DB::table('grade_tbl as gt')
            ->select([
                'gt.grade_id',
                DB::raw($this->buildLocalizedLabelSelect('gt', ['grade_en', 'grade_si', 'grade_ta'], 'label')),
            ]);

        if ($censusId !== null && Schema::hasTable('school_grade_tbl')) {
            $query
                ->join('school_grade_tbl as sgt', 'sgt.grade_id', '=', 'gt.grade_id')
                ->whereIn('sgt.census_id', $this->censusValues($censusId));

            if (Schema::hasColumn('school_grade_tbl', 'is_deleted')) {
                $query->where('sgt.is_deleted', 0);
            }
        }

        return $query
            ->when(Schema::hasColumn('grade_tbl', 'is_deleted'), fn ($builder) => $builder->where('gt.is_deleted', 0))
            ->when($scope !== null, fn ($builder) => $builder->where('gt.grade_id', (int) $scope['grade_id']))
            ->distinct()
            ->orderBy('gt.grade_id')
            ->get()
            ->map(fn (object $row): array => [
                'grade_id' => (int) $row->grade_id,
                'label' => trim((string) ($row->label ?? '')) !== '' ? (string) $row->label : ('Grade ' . (int) $row->grade_id),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function loadYearOptions(?string $censusId, ?array $scope = null): array
    {
        if ($scope !== null && isset($scope['year'])) {
            return [(int) $scope['year']];
        }

        $currentYear = (int) now()->year;
        $minYear = $currentYear - 4;
        $years = collect(range($minYear, $currentYear));

        if (Schema::hasTable('school_grade_class_tbl')) {
            $query = DB::table('school_grade_class_tbl')->select('year');
            if ($censusId !== null && Schema::hasColumn('school_grade_class_tbl', 'census_id')) {
                $query->whereIn('census_id', $this->censusValues($censusId));
            }
            if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
                $query->where('is_deleted', 0);
            }

            $years = $years->merge(
                $query->pluck('year')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): int => (int) $value)
            );
        }

        if (Schema::hasTable('subjects_grade_tbl')) {
            $query = DB::table('subjects_grade_tbl')->select('year');
            if ($censusId !== null) {
                $query->whereIn('census_id', $this->censusValues($censusId));
            }

            $years = $years->merge(
                $query->pluck('year')->filter(fn ($value): bool => is_numeric($value))->map(fn ($value): int => (int) $value)
            );
        }

        return $years
            ->filter(fn (int $year): bool => $year >= $minYear && $year <= $currentYear)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{subject_id:int,subject:string,category:string,sub_cat_id:int}>
     */
    private function loadSectionSubjects(int $sectionId): array
    {
        if ($sectionId <= 0 || !Schema::hasTable('subject_tbl')) {
            return [];
        }

        return DB::table('subject_tbl as st')
            ->leftJoin('subject_category_tbl as sct', 'sct.sub_cat_id', '=', 'st.sub_cat_id')
            ->select([
                'st.subject_id',
                'st.sub_cat_id',
                DB::raw($this->buildLocalizedLabelSelect('st', ['subject_en', 'subject_si', 'subject_ta'], 'subject')),
                DB::raw("COALESCE(NULLIF(TRIM(sct.sub_cat_name), ''), 'Other') as category"),
            ])
            ->where('st.section_id', $sectionId)
            ->when(Schema::hasColumn('subject_tbl', 'is_deleted'), fn ($query) => $query->where('st.is_deleted', 0))
            ->orderBy('st.sub_cat_id')
            ->orderBy('st.subject_id')
            ->get()
            ->map(function (object $row): array {
                $categoryId = (int) ($row->sub_cat_id ?? 0);
                $category = trim((string) ($row->category ?? ''));

                if ($categoryId === 5) {
                    $category = 'Other';
                } elseif ($categoryId === 0) {
                    $category = '';
                } elseif ($category === '') {
                    $category = 'Other';
                }

                return [
                    'subject_id' => (int) $row->subject_id,
                    'subject' => trim((string) ($row->subject ?? '')) !== '' ? (string) $row->subject : ('Subject ' . (int) $row->subject_id),
                    'category' => $category,
                    'sub_cat_id' => $categoryId,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{subject_id:int,subject:string,category:string,year:int,grade_id:int,grade:string}>
     */
    private function loadAssignedSubjectRows(string $censusId, int $year, ?int $gradeId = null): array
    {
        if (!Schema::hasTable('subjects_grade_tbl') || !Schema::hasTable('subject_tbl') || !Schema::hasTable('grade_tbl')) {
            return [];
        }

        return DB::table('subjects_grade_tbl as sgt')
            ->join('subject_tbl as st', 'st.subject_id', '=', 'sgt.subject_id')
            ->join('grade_tbl as gt', 'gt.grade_id', '=', 'sgt.grade_id')
            ->leftJoin('subject_category_tbl as sct', 'sct.sub_cat_id', '=', 'st.sub_cat_id')
            ->select([
                'sgt.subject_id',
                'sgt.grade_id',
                'sgt.year',
                'st.sub_cat_id',
                DB::raw($this->buildLocalizedLabelSelect('st', ['subject_en', 'subject_si', 'subject_ta'], 'subject')),
                DB::raw($this->buildLocalizedLabelSelect('gt', ['grade_en', 'grade_si', 'grade_ta'], 'grade')),
                DB::raw("COALESCE(NULLIF(TRIM(sct.sub_cat_name), ''), '') as category"),
            ])
            ->whereIn('sgt.census_id', $this->censusValues($censusId))
            ->where('sgt.year', $year)
            ->when($gradeId !== null, fn ($builder) => $builder->where('sgt.grade_id', $gradeId))
            ->when(Schema::hasColumn('subjects_grade_tbl', 'is_deleted'), fn ($builder) => $builder->where('sgt.is_deleted', 0))
            ->when(Schema::hasColumn('subject_tbl', 'is_deleted'), fn ($builder) => $builder->where('st.is_deleted', 0))
            ->when(Schema::hasColumn('grade_tbl', 'is_deleted'), fn ($builder) => $builder->where('gt.is_deleted', 0))
            ->orderBy('sgt.grade_id')
            ->orderBy('sgt.order_id')
            ->orderBy('st.sub_cat_id')
            ->orderBy('st.subject_id')
            ->get()
            ->map(function (object $row): array {
                $categoryId = (int) ($row->sub_cat_id ?? 0);
                $category = trim((string) ($row->category ?? ''));

                if ($categoryId === 5) {
                    $category = 'Other';
                } elseif ($categoryId === 0) {
                    $category = '';
                } elseif ($category === '') {
                    $category = 'Other';
                }

                return [
                    'subject_id' => (int) $row->subject_id,
                    'subject' => trim((string) ($row->subject ?? '')) !== '' ? (string) $row->subject : ('Subject ' . (int) $row->subject_id),
                    'category' => $category,
                    'year' => (int) $row->year,
                    'grade_id' => (int) $row->grade_id,
                    'grade' => trim((string) ($row->grade ?? '')) !== '' ? (string) $row->grade : ('Grade ' . (int) $row->grade_id),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $subjectIds
     * @param  array<int, array{subject_id:int,subject:string,category:string,sub_cat_id:int}>  $availableSubjects
     */
    private function validateGradeSubjectRules(int $gradeId, array $subjectIds, array $availableSubjects): ?string
    {
        if ($gradeId >= 12) {
            return count($subjectIds) >= 3
                ? null
                : 'Please select at least 3 subjects.';
        }

        $subjectMap = collect($availableSubjects)->keyBy('subject_id');

        $mainCount = 0;
        $op1Count = 0;
        $op2Count = 0;
        $op3Count = 0;
        $religionCount = 0;
        $requiredMainCount = 0;

        foreach ($subjectIds as $subjectId) {
            $subject = $subjectMap->get($subjectId);
            if (!is_array($subject)) {
                continue;
            }

            $categoryId = (int) ($subject['sub_cat_id'] ?? 0);
            if ($categoryId === 1) {
                $mainCount++;
            } elseif ($categoryId === 2) {
                $op1Count++;
            } elseif ($categoryId === 3) {
                $op2Count++;
            } elseif ($categoryId === 4) {
                $op3Count++;
            }

            if (in_array($subjectId, [5, 6, 7, 8, 9], true)) {
                $religionCount++;
            }

            if (in_array($subjectId, [10, 12, 13, 14, 15], true)) {
                $requiredMainCount++;
            }
        }

        [$minMain, $minOp1, $minOp2, $minOp3] = match (true) {
            $gradeId >= 1 && $gradeId <= 5 => [4, 0, 0, 0],
            $gradeId >= 6 && $gradeId <= 9 => [6, 0, 1, 0],
            $gradeId >= 10 && $gradeId <= 11 => [6, 1, 1, 1],
            default => [3, 0, 0, 0],
        };

        if ($mainCount < $minMain) {
            return sprintf('Minimum %d main subjects needed.', $minMain);
        }

        if ($op1Count < $minOp1) {
            return sprintf('Minimum %d OP1 subject needed.', $minOp1);
        }

        if ($op2Count < $minOp2) {
            return sprintf('Minimum %d OP2 subjects needed.', $minOp2);
        }

        if ($op3Count < $minOp3) {
            return sprintf('Minimum %d OP3 subjects needed.', $minOp3);
        }

        if ($gradeId >= 6 && $gradeId <= 11 && $religionCount < 1) {
            return 'Please select at least one religion subject.';
        }

        if ($gradeId >= 6 && $gradeId <= 11 && $requiredMainCount < 5) {
            return 'Please include the required main subjects for this grade.';
        }

        return null;
    }

    private function gradeLabel(Grade $grade): string
    {
        foreach (['grade_en', 'grade_si', 'grade_ta'] as $column) {
            $value = trim((string) ($grade->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Grade ' . (int) $grade->grade_id;
    }

    /**
     * @return array<int, string>
     */
    private function censusValues(string $censusId): array
    {
        $values = [$censusId];

        if (is_numeric($censusId)) {
            $plain = (string) ((int) $censusId);
            $values[] = $plain;
            $values[] = str_pad($plain, 5, '0', STR_PAD_LEFT);
            $values[] = str_pad($plain, 7, '0', STR_PAD_LEFT);
        }

        return array_values(array_unique(array_filter($values, fn ($value): bool => trim((string) $value) !== '')));
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectLogContext(?User $user): array
    {
        return [
            'user_id' => $user?->user_id ?? null,
            'role_id' => $user?->role_id ?? null,
            'role_name' => trim((string) ($user?->role_name ?? $user?->role?->role_name ?? '')) ?: null,
            'username' => trim((string) ($user?->username ?? '')) ?: null,
            'requested_school_census_id' => $this->resolveRequestedSchoolCensusId($user),
        ];
    }

    private function loadSchoolName(string $censusId): string
    {
        if (!Schema::hasTable('school_details_tbl')) {
            return '';
        }

        $columns = array_values(array_filter(
            ['school_name_en', 'school_name_si', 'school_name_ta', 'school_name'],
            fn (string $column): bool => Schema::hasColumn('school_details_tbl', $column),
        ));

        if ($columns === []) {
            return '';
        }

        $row = DB::table('school_details_tbl')
            ->select($columns)
            ->whereIn('census_id', $this->censusValues($censusId))
            ->when(Schema::hasColumn('school_details_tbl', 'is_deleted'), fn ($query) => $query->where('is_deleted', 0))
            ->first();

        if ($row === null) {
            return '';
        }

        foreach ($columns as $column) {
            $value = trim((string) ($row->{$column} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{grade_id:int,year:int,class_id?:int}|null
     */
    private function resolveRestrictedSubjectScope(?User $user): ?array
    {
        if ($this->isClassTeacher($user)) {
            $assignment = $this->resolveClassTeacherAssignment($user);
            if ($assignment === null) {
                return null;
            }

            return [
                'grade_id' => (int) $assignment['grade_id'],
                'year' => (int) $assignment['year'],
                'class_id' => (int) $assignment['class_id'],
            ];
        }

        if ($this->isStudentUser($user)) {
            return $this->resolveStudentSubjectScope($user);
        }

        return null;
    }

    private function isStudentUser(?User $user): bool
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

    /**
     * @return array{grade_id:int,year:int,class_id?:int}|null
     */
    private function resolveStudentSubjectScope(?User $user): ?array
    {
        if ($user === null || !Schema::hasTable('student_tbl') || !Schema::hasTable('student_grade_class_tbl') || !Schema::hasTable('school_grade_class_tbl')) {
            return null;
        }

        ['index_no' => $indexNo, 'census_id' => $encodedCensusId] = $this->resolveStudentLoginIdentityFromUsername($user->username ?? null);
        if ($indexNo === '') {
            return null;
        }

        $query = DB::table('student_tbl as st')
            ->join('student_grade_class_tbl as sgc', 'sgc.std_id', '=', 'st.std_id')
            ->join('school_grade_class_tbl as sgct', 'sgct.sch_grd_cls_id', '=', 'sgc.sch_grd_cls_id')
            ->select([
                'sgct.grade_id',
                'sgct.year',
                'sgct.class_id',
            ])
            ->where('st.index_no', $indexNo);

        if (Schema::hasColumn('student_tbl', 'is_deleted')) {
            $query->where('st.is_deleted', 0);
        }

        if (Schema::hasColumn('student_grade_class_tbl', 'is_deleted')) {
            $query->where('sgc.is_deleted', 0);
        }

        if (Schema::hasColumn('school_grade_class_tbl', 'is_deleted')) {
            $query->where('sgct.is_deleted', 0);
        }

        $censusId = $encodedCensusId ?? $this->resolveUserCensusId($user);
        if ($censusId !== null) {
            $query->whereIn('st.census_id', $this->censusValues($censusId));
            $query->whereIn('sgct.census_id', $this->censusValues($censusId));
        }

        $row = $query
            ->orderByDesc('sgct.year')
            ->orderByDesc('sgct.sch_grd_cls_id')
            ->first();

        if (
            $row === null
            || !is_numeric($row->grade_id ?? null)
            || !is_numeric($row->year ?? null)
        ) {
            return null;
        }

        return [
            'grade_id' => (int) $row->grade_id,
            'year' => (int) $row->year,
            'class_id' => is_numeric($row->class_id ?? null) ? (int) $row->class_id : 0,
        ];
    }
}
