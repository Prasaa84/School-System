<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\ClassLookupController;
use App\Http\Controllers\Api\V1\ClassReportController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\FeaturePermissionController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\GradeReportController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ModuleCatalogController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\StudentAttendanceController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TermTestMarksController;
use App\Http\Controllers\Api\V1\SubjectsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('sds.api')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/auth/account', [AuthController::class, 'account']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/modules', ModuleCatalogController::class);
        Route::get('/dashboard/summary', DashboardSummaryController::class);

        Route::get('/admin/feature-permissions', [FeaturePermissionController::class, 'index']);
        Route::put('/admin/feature-permissions/roles/{roleId}', [FeaturePermissionController::class, 'updateRole'])
            ->whereNumber('roleId');

        Route::get('/school/details', [SchoolController::class, 'show']);
        Route::get('/school/details/options', [SchoolController::class, 'options']);
        Route::post('/school/details', [SchoolController::class, 'store']);
        Route::put('/school/details', [SchoolController::class, 'update']);
        Route::delete('/school/details', [SchoolController::class, 'destroy']);

        Route::get('/payments/options', [PaymentController::class, 'options']);
        Route::get('/payments/student', [PaymentController::class, 'student']);
        Route::get('/payments/report', [PaymentController::class, 'report']);
        Route::get('/payments/report/export', [PaymentController::class, 'downloadReport']);
        Route::get('/payments/fee', [PaymentController::class, 'fee']);
        Route::get('/payments/fee-types', [PaymentController::class, 'feeTypes']);
        Route::post('/payments/fee-types', [PaymentController::class, 'storeFeeType']);
        Route::put('/payments/fee-types/{feeTypeId}', [PaymentController::class, 'updateFeeType'])->whereNumber('feeTypeId');
        Route::post('/payments', [PaymentController::class, 'store']);

        Route::get('/subjects/options', [SubjectsController::class, 'options']);
        Route::get('/subjects/grade-subjects', [SubjectsController::class, 'subjects']);
        Route::get('/subjects/grade-subjects/export', [SubjectsController::class, 'downloadSubjects']);
        Route::get('/subjects/report', [SubjectsController::class, 'report']);
        Route::get('/subjects/report/export', [SubjectsController::class, 'downloadReport']);
        Route::post('/subjects/grade-subjects', [SubjectsController::class, 'saveSubjects']);

        Route::get('/marks/options', [TermTestMarksController::class, 'options']);
        Route::get('/marks', [TermTestMarksController::class, 'index']);
        Route::get('/marks/export', [TermTestMarksController::class, 'download']);
        Route::get('/marks/template', [TermTestMarksController::class, 'downloadTemplate']);
        Route::post('/marks/import', [TermTestMarksController::class, 'import']);
        Route::post('/marks/draft', [TermTestMarksController::class, 'saveDraft']);
        Route::post('/marks', [TermTestMarksController::class, 'save']);
        Route::post('/marks/confirmation', [TermTestMarksController::class, 'updateConfirmation']);
        Route::delete('/marks', [TermTestMarksController::class, 'clear']);

        Route::get('/grades', GradeController::class);
        Route::post('/grades/initialize-year', [GradeController::class, 'initializeYear']);
        Route::put('/grades/{gradeRowId}', [GradeController::class, 'update'])->whereNumber('gradeRowId');
        Route::delete('/grades/{gradeRowId}', [GradeController::class, 'destroy'])->whereNumber('gradeRowId');
        Route::get('/grades/report', GradeReportController::class);

        Route::get('/classes', [ClassController::class, 'index']);
        Route::get('/classes/options', [ClassController::class, 'options']);
        Route::post('/classes', [ClassController::class, 'store']);
        Route::put('/classes/{classRowId}', [ClassController::class, 'update'])->whereNumber('classRowId');
        Route::put('/classes/{classRowId}/attendance-override', [ClassController::class, 'updateAttendanceOverride'])->whereNumber('classRowId');
        Route::delete('/classes/{classRowId}', [ClassController::class, 'destroy'])->whereNumber('classRowId');
        Route::get('/classes/by-grade/{gradeId}', [ClassLookupController::class, 'byGrade'])
            ->whereNumber('gradeId');
        Route::get('/classes/report', ClassReportController::class);

        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/staff/options', [StaffController::class, 'options']);
        Route::get('/staff/report', [StaffController::class, 'report']);
        Route::get('/staff/report-summary', [StaffController::class, 'reportSummary']);
        Route::get('/staff/{staff}', [StaffController::class, 'show']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::put('/staff/{staff}', [StaffController::class, 'update']);

        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/daily-attendance', [StudentAttendanceController::class, 'index']);
        Route::get('/students/daily-attendance/export', [StudentAttendanceController::class, 'downloadReport']);
        Route::post('/students/daily-attendance/toggle', [StudentAttendanceController::class, 'toggle']);
        Route::get('/students/report', [StudentController::class, 'report']);
        Route::get('/students/report/download', [StudentController::class, 'downloadReport']);
        Route::get('/students/options', [StudentController::class, 'options']);
        Route::get('/students/in-classes/roster', [StudentController::class, 'studentsInClassesRoster']);
        Route::post('/students/in-classes/save', [StudentController::class, 'saveStudentsInClasses']);
        Route::get('/students/in-classes/overview', [StudentController::class, 'studentsInClassesOverview']);
        Route::get('/students/in-classes/download', [StudentController::class, 'downloadStudentsInClass']);
        Route::get('/students/in-classes/template', [StudentController::class, 'downloadStudentsInClassesTemplate']);
        Route::post('/students/in-classes/upload', [StudentController::class, 'uploadStudentsInClass']);
        Route::post('/students/in-classes/clear', [StudentController::class, 'clearStudentsInClass']);
        Route::post('/students/in-classes/remove-student', [StudentController::class, 'removeStudentFromClass']);
        Route::get('/students/template', [StudentController::class, 'downloadTemplate']);
        Route::post('/students/import', [StudentController::class, 'import']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::get('/students/me', [StudentController::class, 'current']);
        Route::get('/students/{studentId}/profile-export', [StudentController::class, 'downloadProfileExport'])->whereNumber('studentId');
        Route::get('/students/{studentId}', [StudentController::class, 'show'])->whereNumber('studentId');
        Route::put('/students/{studentId}', [StudentController::class, 'update'])->whereNumber('studentId');
        Route::delete('/students/{studentId}', [StudentController::class, 'destroy'])->whereNumber('studentId');
    });
});
