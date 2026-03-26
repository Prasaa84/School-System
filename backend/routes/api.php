<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\ClassLookupController;
use App\Http\Controllers\Api\V1\ClassReportController;
use App\Http\Controllers\Api\V1\DashboardSummaryController;
use App\Http\Controllers\Api\V1\GradeController;
use App\Http\Controllers\Api\V1\GradeReportController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ModuleCatalogController;
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

        Route::get('/grades', GradeController::class);
        Route::post('/grades/initialize-year', [GradeController::class, 'initializeYear']);
        Route::put('/grades/{gradeRowId}', [GradeController::class, 'update'])->whereNumber('gradeRowId');
        Route::delete('/grades/{gradeRowId}', [GradeController::class, 'destroy'])->whereNumber('gradeRowId');
        Route::get('/grades/report', GradeReportController::class);

        Route::get('/classes', [ClassController::class, 'index']);
        Route::put('/classes/{classRowId}', [ClassController::class, 'update'])->whereNumber('classRowId');
        Route::get('/classes/by-grade/{gradeId}', [ClassLookupController::class, 'byGrade'])
            ->whereNumber('gradeId');
        Route::get('/classes/report', ClassReportController::class);

        Route::get('/staff', [StaffController::class, 'index']);
        Route::get('/staff/options', [StaffController::class, 'options']);
        Route::get('/staff/report-summary', [StaffController::class, 'reportSummary']);

        Route::get('/students', [StudentController::class, 'index']);
    });
});
