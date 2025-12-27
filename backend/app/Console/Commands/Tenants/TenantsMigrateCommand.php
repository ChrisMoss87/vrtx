<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Tenancy\TenancyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsMigrateCommand extends Command
{
    protected $signature = 'tenants:migrate
                            {--tenants= : Comma-separated list of tenant IDs to migrate}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Run seeders after migration}
                            {--seeder= : The seeder class to use}';

    protected $description = 'Run migrations for tenant databases';

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        TenancyManager $tenancyManager,
    ): int {
        $tenantIds = $this->option('tenants')
            ? explode(',', $this->option('tenants'))
            : null;

        $tenants = $tenantRepository->all();

        if ($tenantIds) {
            $tenants = array_filter($tenants, function ($tenant) use ($tenantIds) {
                return in_array($tenant->id()->value(), $tenantIds);
            });
        }

        if (empty($tenants)) {
            $this->error('No tenants found.');
            return self::FAILURE;
        }

        $this->info('Running migrations for ' . count($tenants) . ' tenant(s)...');
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->info("Migrating tenant: {$tenant->id()->value()}");

            try {
                $tenancyManager->initialize($tenant);

                $command = $this->option('fresh') ? 'migrate:fresh' : 'migrate';

                Artisan::call($command, [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);

                $this->line(Artisan::output());

                if ($this->option('seed')) {
                    $seeder = $this->option('seeder') ?? config('tenancy.seeder_parameters.--class', 'DatabaseSeeder');

                    Artisan::call('db:seed', [
                        '--class' => $seeder,
                        '--force' => true,
                    ]);

                    $this->line(Artisan::output());
                }

                $tenancyManager->end();

                $this->info("Completed migration for tenant: {$tenant->id()->value()}");
            } catch (\Throwable $e) {
                $this->error("Failed to migrate tenant {$tenant->id()->value()}: {$e->getMessage()}");
                $tenancyManager->end();
            }

            $this->newLine();
        }

        $this->info('Tenant migrations complete.');

        return self::SUCCESS;
    }
}
