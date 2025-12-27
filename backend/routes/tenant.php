<?php

declare(strict_types=1);

use App\Infrastructure\Tenancy\Middleware\InitializeTenancyByDomain;
use App\Infrastructure\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return 'This is your multi-tenant application. The id of the current tenant is ' . tenant('id');
    });

    Route::get('/test-isolation', function () {
        $users = \App\Models\User::all(['name', 'email']);
        return response()->json([
            'tenant_id' => tenant('id'),
            'database' => DB::connection()->getDatabaseName(),
            'users' => $users
        ]);
    });
});
