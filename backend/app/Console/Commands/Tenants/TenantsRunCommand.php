<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use App\Infrastructure\Tenancy\TenancyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TenantsRunCommand extends Command
{
    protected $signature = 'tenants:run
                            {artisan_command : The artisan command to run}
                            {--tenants= : Comma-separated list of tenant IDs}';

    protected $description = 'Run an artisan command for tenant(s)';

    public function handle(
        TenantRepositoryInterface $tenantRepository,
        TenancyManager $tenancyManager,
    ): int {
        $command = $this->argument('artisan_command');
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

        $this->info("Running '{$command}' for " . count($tenants) . " tenant(s)...");
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->id()->value()}");

            try {
                $tenancyManager->initialize($tenant);

                Artisan::call($command);
                $this->line(Artisan::output());

                $tenancyManager->end();
            } catch (\Throwable $e) {
                $this->error("Failed for tenant {$tenant->id()->value()}: {$e->getMessage()}");
                $tenancyManager->end();
            }

            $this->newLine();
        }

        $this->info('Command execution complete.');

        return self::SUCCESS;
    }
}
