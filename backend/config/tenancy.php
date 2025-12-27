<?php

declare(strict_types=1);

use App\Infrastructure\Tenancy\Bootstrappers\CacheBootstrapper;
use App\Infrastructure\Tenancy\Bootstrappers\DatabaseBootstrapper;
use App\Infrastructure\Tenancy\Bootstrappers\FilesystemBootstrapper;
use App\Infrastructure\Tenancy\Bootstrappers\QueueBootstrapper;

return [
    /*
    |--------------------------------------------------------------------------
    | Central Domains
    |--------------------------------------------------------------------------
    |
    | The list of domains hosting your central app. Requests to these domains
    | will not initialize tenancy. Only relevant if you're using the domain
    | or subdomain identification middleware.
    |
    */
    'central_domains' => [
        '127.0.0.1',
        'localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenancy Bootstrappers
    |--------------------------------------------------------------------------
    |
    | Bootstrappers are executed when tenancy is initialized. Their
    | responsibility is making Laravel features tenant-aware.
    |
    */
    'bootstrappers' => [
        DatabaseBootstrapper::class,
        CacheBootstrapper::class,
        FilesystemBootstrapper::class,
        QueueBootstrapper::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant database management.
    |
    */
    'database' => [
        // Connection used for central/system data (tenants table, domains table)
        'central_connection' => 'central',

        // Template connection used for tenant databases
        'template_connection' => 'pgsql',

        // Tenant database naming: prefix + tenant_id + suffix
        'prefix' => 'tenant',
        'suffix' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filesystem Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for tenant-aware filesystem.
    |
    */
    'filesystem' => [
        // Disks that should be tenant-aware
        'disks' => [
            'local',
            'public',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Parameters
    |--------------------------------------------------------------------------
    |
    | Parameters used when running tenant migrations.
    |
    */
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeder Parameters
    |--------------------------------------------------------------------------
    |
    | Parameters used when seeding tenant databases.
    |
    */
    'seeder_parameters' => [
        '--class' => 'TenantUserSeeder',
        '--force' => true,
    ],
];
