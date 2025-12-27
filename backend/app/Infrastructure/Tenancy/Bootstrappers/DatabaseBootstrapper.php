<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Bootstrappers;

use App\Domain\Tenancy\Entities\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Switches the default database connection to the tenant's database.
 */
final class DatabaseBootstrapper implements TenancyBootstrapperInterface
{
    private ?string $originalDatabase = null;

    public function bootstrap(Tenant $tenant): void
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        // Store original database for reverting
        $this->originalDatabase = $config['database'] ?? null;

        // Update the database name to the tenant's database
        $tenantDatabase = $tenant->databaseName();

        config(["database.connections.{$connection}.database" => $tenantDatabase]);

        // Purge the connection to force reconnection with new config
        DB::purge($connection);
        DB::reconnect($connection);

        // Set the connection for the default connection
        DB::setDefaultConnection($connection);
    }

    public function revert(): void
    {
        if ($this->originalDatabase === null) {
            return;
        }

        $connection = config('database.default');

        config(["database.connections.{$connection}.database" => $this->originalDatabase]);

        DB::purge($connection);
        DB::reconnect($connection);

        $this->originalDatabase = null;
    }
}
