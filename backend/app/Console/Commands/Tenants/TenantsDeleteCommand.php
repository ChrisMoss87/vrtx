<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Infrastructure\Tenancy\TenantManager;
use Illuminate\Console\Command;

class TenantsDeleteCommand extends Command
{
    protected $signature = 'tenants:delete
                            {id : The tenant ID to delete}
                            {--force : Skip confirmation}
                            {--keep-database : Keep the database after deletion}';

    protected $description = 'Delete a tenant';

    public function handle(TenantManager $tenantManager): int
    {
        $id = $this->argument('id');

        $tenant = $tenantManager->find($id);

        if (!$tenant) {
            $this->error("Tenant not found: {$id}");
            return self::FAILURE;
        }

        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                "Are you sure you want to delete tenant '{$id}'? This action cannot be undone."
            );

            if (!$confirmed) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info("Deleting tenant: {$id}");

        try {
            $tenantManager->delete(
                id: $id,
                deleteDatabase: !$this->option('keep-database'),
            );

            $this->info("Tenant deleted successfully: {$id}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to delete tenant: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
