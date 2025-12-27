<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Jobs;

use App\Domain\Tenancy\Entities\Tenant;
use App\Infrastructure\Tenancy\TenancyManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class SeedTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $seeder = 'Database\\Seeders\\DatabaseSeeder',
    ) {}

    public function handle(TenancyManager $manager): void
    {
        $manager->run($this->tenant, function () {
            Artisan::call('db:seed', [
                '--class' => $this->seeder,
                '--force' => true,
            ]);
        });
    }
}
