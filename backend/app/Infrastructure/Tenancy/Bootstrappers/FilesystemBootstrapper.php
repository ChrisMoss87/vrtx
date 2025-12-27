<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy\Bootstrappers;

use App\Domain\Tenancy\Entities\Tenant;

/**
 * Suffixes storage paths with tenant ID to ensure tenant isolation.
 */
final class FilesystemBootstrapper implements TenancyBootstrapperInterface
{
    private array $originalRoots = [];

    public function bootstrap(Tenant $tenant): void
    {
        $disks = config('tenancy.filesystem.disks', ['local', 'public']);
        $suffix = '/tenant_' . $tenant->id()->value();

        foreach ($disks as $disk) {
            $originalRoot = config("filesystems.disks.{$disk}.root");

            if ($originalRoot) {
                $this->originalRoots[$disk] = $originalRoot;
                config(["filesystems.disks.{$disk}.root" => $originalRoot . $suffix]);
            }
        }

        // Force filesystem manager to reinitialize
        app()->forgetInstance('filesystem');
    }

    public function revert(): void
    {
        foreach ($this->originalRoots as $disk => $root) {
            config(["filesystems.disks.{$disk}.root" => $root]);
        }

        app()->forgetInstance('filesystem');

        $this->originalRoots = [];
    }
}
