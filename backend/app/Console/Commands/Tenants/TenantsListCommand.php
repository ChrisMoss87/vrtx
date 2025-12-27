<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use Illuminate\Console\Command;

class TenantsListCommand extends Command
{
    protected $signature = 'tenants:list';

    protected $description = 'List all tenants';

    public function handle(TenantRepositoryInterface $tenantRepository): int
    {
        $tenants = $tenantRepository->all();

        if (empty($tenants)) {
            $this->info('No tenants found.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($tenants as $tenant) {
            $domains = array_map(
                fn($domain) => $domain->domain(),
                $tenant->domains()
            );

            $rows[] = [
                $tenant->id()->value(),
                $tenant->get('name', '-'),
                $tenant->get('plan', '-'),
                implode(', ', $domains),
                $tenant->createdAt()->format('Y-m-d H:i:s'),
            ];
        }

        $this->table(
            ['ID', 'Name', 'Plan', 'Domains', 'Created At'],
            $rows
        );

        return self::SUCCESS;
    }
}
