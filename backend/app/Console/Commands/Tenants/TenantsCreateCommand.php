<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Infrastructure\Tenancy\TenantManager;
use Illuminate\Console\Command;

class TenantsCreateCommand extends Command
{
    protected $signature = 'tenants:create
                            {id : The tenant ID}
                            {--domain= : The domain for the tenant}
                            {--name= : The tenant name}
                            {--plan= : The tenant plan}
                            {--no-database : Skip database creation}
                            {--no-migrate : Skip running migrations}
                            {--seed : Run seeders after migration}';

    protected $description = 'Create a new tenant';

    public function handle(TenantManager $tenantManager): int
    {
        $id = $this->argument('id');
        $domain = $this->option('domain');
        $name = $this->option('name');
        $plan = $this->option('plan');

        $data = [];
        if ($name) {
            $data['name'] = $name;
        }
        if ($plan) {
            $data['plan'] = $plan;
        }

        $this->info("Creating tenant: {$id}");

        try {
            $tenant = $tenantManager->create(
                id: $id,
                data: $data,
                domain: $domain,
                createDatabase: !$this->option('no-database'),
                migrate: !$this->option('no-migrate'),
                seed: $this->option('seed'),
            );

            $this->info("Tenant created successfully: {$tenant->id()->value()}");

            if ($domain) {
                $this->info("Domain: {$domain}");
            }

            $this->info("Database: {$tenant->databaseName()}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
