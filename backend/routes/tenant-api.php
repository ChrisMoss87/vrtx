<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Modules\ModuleController;
use App\Http\Controllers\Api\Modules\RecordController;
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
            Route::get('/{moduleApiName}/{recordId}', [RecordController::class, 'show']);
            Route::put('/{moduleApiName}/{recordId}', [RecordController::class, 'update']);
            Route::delete('/{moduleApiName}/{recordId}', [RecordController::class, 'destroy']);
            Route::post('/{moduleApiName}/bulk-delete', [RecordController::class, 'bulkDestroy']);
        });
    });
});
