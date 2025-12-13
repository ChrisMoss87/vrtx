<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * Base test case for tenant-aware tests.
 *
 * Runs both central and tenant migrations for a unified test database.
 * Use this for any tests that interact with tenant-specific tables
 * like workflows, blueprints, activities, etc.
 *
 * This test case bypasses the tenancy middleware since we're running
 * against a single unified test database.
 */
abstract class TenantTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Determine if the seed should run.
     */
    protected bool $seed = false;

    /**
     * The seeder to run after migration.
     */
    protected string $seeder = '';

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable tenancy middleware for testing
        $this->withoutMiddleware([
            InitializeTenancyByDomain::class,
            InitializeTenancyBySubdomain::class,
            InitializeTenancyByPath::class,
            PreventAccessFromCentralDomains::class,
        ]);
    }

    /**
     * Refresh the in-memory database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        // Run central migrations
        Artisan::call('migrate', [
            '--path' => 'database/migrations',
            '--realpath' => false,
            '--force' => true,
        ]);

        // Run tenant migrations
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        // Run roles and permissions seeder (required for user creation)
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
            '--force' => true,
        ]);

        $this->app[\Illuminate\Contracts\Console\Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh the test database.
     */
    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            $this->runMigrations();

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Run the database migrations.
     */
    protected function runMigrations(): void
    {
        // Run central migrations first
        Artisan::call('migrate:fresh', [
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        // Run tenant migrations on top
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        // Run roles and permissions seeder (required for user creation)
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
            '--force' => true,
        ]);

        // Run additional seeders if configured
        if ($this->seed && $this->seeder) {
            Artisan::call('db:seed', [
                '--class' => $this->seeder,
                '--force' => true,
            ]);
        }
    }

    /**
     * Determine if the database should be refreshed between tests.
     */
    protected function shouldRefreshDatabase(): bool
    {
        return true;
    }
}
