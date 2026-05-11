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
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', HealthController::class);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('sds.api')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
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

        Route::get('/grades', GradeController::class);
        Route::post('/grades/initialize-year', [GradeController::class, 'initializeYear']);
        Route::put('/grades/{gradeRowId}', [GradeController::class, 'update'])->whereNumber('gradeRowId');
        Route::delete('/grades/{gradeRowId}', [GradeController::class, 'destroy'])->whereNumber('gradeRowId');
        Route::get('/grades/report', GradeReportController::class);

        Route::get('/classes', [ClassController::class, 'index']);
        Route::get('/classes/options', [ClassController::class, 'options']);
        Route::post('/classes', [ClassController::class, 'store']);
        Route::put('/classes/{classRowId}', [ClassController::class, 'update'])->whereNumber('classRowId');
        Route::delete('/classes/{classRowId}', [ClassController::class, 'destroy'])->whereNumber('classRowId');
        Route::get('/classes/by-grade/{gradeId}', [ClassLookupController::class, 'byGrade'])
            ->whereNumber('gradeId');
        Route::get('/classes/report', ClassReportController::class);

        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/staff/options', [StaffController::class, 'options']);
        Route::get('/staff/report-summary', [StaffController::class, 'reportSummary']);
        Route::post('/staff', [StaffController::class, 'store']);

        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/options', [StudentController::class, 'options']);
        Route::get('/students/template', [StudentController::class, 'downloadTemplate']);
        Route::post('/students/import', [StudentController::class, 'import']);
        Route::post('/students', [StudentController::class, 'store']);
        Route::get('/students/{studentId}', [StudentController::class, 'show'])->whereNumber('studentId');
        Route::put('/students/{studentId}', [StudentController::class, 'update'])->whereNumber('studentId');
        Route::delete('/students/{studentId}', [StudentController::class, 'destroy'])->whereNumber('studentId');
    });
});



