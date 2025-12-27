<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Jobs;

use App\Domain\Tenancy\Entities\Tenant;
use App\Domain\Tenancy\Repositories\TenantRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}

    public function handle(TenantRepositoryInterface $repository): void
    {
        $repository->deleteDatabase($this->tenant->id());
    }
}
