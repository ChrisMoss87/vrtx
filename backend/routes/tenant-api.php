<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\Modules\ModuleController;
use App\Http\Controllers\Api\Modules\RecordController;
use App\Http\Controllers\Api\Modules\ViewsController;
use App\Http\Controllers\Api\Pipelines\PipelineController;
use App\Http\Controllers\Api\UserSearchController;
use App\Http\Controllers\Api\WizardDraftController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Here you can register API routes for your tenant application.
| These routes are automatically scoped to the current tenant.
|
*/

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->prefix('api/v1')->group(function () {
    // Public authentication routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Module Management Routes
        Route::prefix('modules')->group(function () {
            Route::get('/', [ModuleController::class, 'index']);
            Route::get('/active', [ModuleController::class, 'active']);
            Route::get('/by-api-name/{apiName}', [ModuleController::class, 'showByApiName']);
            Route::post('/', [ModuleController::class, 'store']);
            Route::get('/{id}', [ModuleController::class, 'show']);
            Route::put('/{id}', [ModuleController::class, 'update']);
            Route::delete('/{id}', [ModuleController::class, 'destroy']);
            Route::post('/{id}/toggle-status', [ModuleController::class, 'toggleStatus']);
        });

        // Dynamic Module Records Routes
        Route::prefix('records')->group(function () {
            Route::get('/{moduleApiName}', [RecordController::class, 'index']);
            Route::post('/{moduleApiName}', [RecordController::class, 'store']);
            Route::post('/{moduleApiName}/bulk-delete', [RecordController::class, 'bulkDestroy']);
            Route::post('/{moduleApiName}/bulk-update', [RecordController::class, 'bulkUpdate']);
            Route::get('/{moduleApiName}/lookup', [RecordController::class, 'lookup']);
            Route::get('/{moduleApiName}/{recordId}', [RecordController::class, 'show']);
            Route::put('/{moduleApiName}/{recordId}', [RecordController::class, 'update']);
            Route::patch('/{moduleApiName}/{recordId}', [RecordController::class, 'patch']);
            Route::delete('/{moduleApiName}/{recordId}', [RecordController::class, 'destroy']);
        });

        // Module Views Routes
        Route::prefix('views')->group(function () {
            Route::get('/{moduleApiName}', [ViewsController::class, 'index']);
            Route::get('/{moduleApiName}/default', [ViewsController::class, 'getDefaultView']);
            Route::post('/{moduleApiName}', [ViewsController::class, 'store']);
            Route::get('/{moduleApiName}/{viewId}', [ViewsController::class, 'show']);
            Route::put('/{moduleApiName}/{viewId}', [ViewsController::class, 'update']);
            Route::delete('/{moduleApiName}/{viewId}', [ViewsController::class, 'destroy']);
        });

        // Wizard Draft Routes
        Route::prefix('wizard-drafts')->group(function () {
            Route::get('/', [WizardDraftController::class, 'index']);
            Route::post('/', [WizardDraftController::class, 'store']);
            Route::post('/auto-save', [WizardDraftController::class, 'autoSave']);
            Route::post('/bulk-delete', [WizardDraftController::class, 'bulkDestroy']);
            Route::get('/{id}', [WizardDraftController::class, 'show']);
            Route::delete('/{id}', [WizardDraftController::class, 'destroy']);
            Route::patch('/{id}/rename', [WizardDraftController::class, 'rename']);
            Route::post('/{id}/make-permanent', [WizardDraftController::class, 'makePermanent']);
            Route::post('/{id}/extend', [WizardDraftController::class, 'extendExpiration']);
        });

        // File Upload Routes
        Route::prefix('files')->group(function () {
            Route::post('/upload', [FileUploadController::class, 'upload']);
            Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);
            Route::post('/delete', [FileUploadController::class, 'delete']);
            Route::post('/info', [FileUploadController::class, 'info']);
        });

        // User Search (for mentions)
        Route::get('/users/search', [UserSearchController::class, 'search']);

        // Simple upload endpoint at root level
        Route::post('/upload', [FileUploadController::class, 'upload']);
        Route::post('/upload-multiple', [FileUploadController::class, 'uploadMultiple']);

        // Pipeline Routes
        Route::prefix('pipelines')->group(function () {
            Route::get('/', [PipelineController::class, 'index']);
            Route::get('/module/{moduleApiName}', [PipelineController::class, 'forModule']);
            Route::post('/', [PipelineController::class, 'store']);
            Route::get('/{id}', [PipelineController::class, 'show']);
            Route::put('/{id}', [PipelineController::class, 'update']);
            Route::delete('/{id}', [PipelineController::class, 'destroy']);
            Route::get('/{id}/kanban', [PipelineController::class, 'kanbanData']);
            Route::post('/{id}/move-record', [PipelineController::class, 'moveRecord']);
            Route::get('/{id}/record/{recordId}/history', [PipelineController::class, 'recordHistory']);
            Route::post('/{id}/reorder-stages', [PipelineController::class, 'reorderStages']);
        });
    });
});
