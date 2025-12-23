<?php

declare(strict_types=1);

namespace Plugins\SMS;

use App\Domain\Communication\Contracts\CommunicationChannelInterface;
use App\Domain\Plugin\Contracts\PluginManifestInterface;
use App\Infrastructure\Plugin\BasePluginServiceProvider;
use App\Infrastructure\Plugin\PluginManifest;
use Plugins\SMS\Application\Services\SMSApplicationService;
use Plugins\SMS\Domain\Repositories\SMSRepositoryInterface;
use Plugins\SMS\Infrastructure\Adapters\SMSChannelAdapter;
use Plugins\SMS\Infrastructure\Repositories\EloquentSMSRepository;

class SMSPluginServiceProvider extends BasePluginServiceProvider
{
    /**
     * Get the plugin manifest.
     */
    public function getManifest(): PluginManifestInterface
    {
        if (!$this->manifest) {
            $this->manifest = PluginManifest::fromJson(__DIR__ . '/../manifest.json');
        }

        return $this->manifest;
    }

    /**
     * Get the communication channel adapter.
     */
    protected function getChannelAdapter(): ?CommunicationChannelInterface
    {
        return $this->app->make(SMSChannelAdapter::class);
    }

    /**
     * Register plugin-specific services.
     */
    protected function registerPluginServices(): void
    {
        // Register repository
        $this->app->bind(
            SMSRepositoryInterface::class,
            EloquentSMSRepository::class
        );

        // Register application service
        $this->app->singleton(SMSApplicationService::class);
    }

    /**
     * Boot plugin-specific services.
     */
    protected function bootPluginServices(): void
    {
        // Nothing additional to boot
    }

    /**
     * Called when plugin is activated.
     */
    public function onActivate(): void
    {
        parent::onActivate();
    }

    /**
     * Called when plugin is uninstalled.
     */
    public function onUninstall(string $dataAction = 'keep'): void
    {
        parent::onUninstall($dataAction);
    }

    /**
     * Mark SMS data as orphaned.
     */
    protected function markDataAsOrphaned(): void
    {
        \DB::table('sms_connections')->update([
            'status' => 'orphaned',
            'updated_at' => now(),
        ]);
    }

    /**
     * Archive SMS data.
     */
    protected function archiveData(): void
    {
        \DB::statement('
            INSERT INTO sms_messages_archive
            SELECT * FROM sms_messages
        ');
        \DB::table('sms_messages')->truncate();

        \DB::table('sms_connections')->update([
            'status' => 'archived',
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete SMS data.
     */
    protected function deleteData(): void
    {
        \DB::table('sms_messages')->delete();
        \DB::table('sms_opt_outs')->delete();
        \DB::table('sms_campaigns')->delete();
        \DB::table('sms_templates')->delete();
        \DB::table('sms_connections')->delete();
    }
}
