<?php

declare(strict_types=1);

namespace Plugins\WhatsApp;

use App\Domain\Communication\Contracts\CommunicationChannelInterface;
use App\Domain\Plugin\Contracts\PluginManifestInterface;
use App\Infrastructure\Plugin\BasePluginServiceProvider;
use App\Infrastructure\Plugin\PluginManifest;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;
use Plugins\WhatsApp\Domain\Repositories\WhatsAppRepositoryInterface;
use Plugins\WhatsApp\Infrastructure\Adapters\WhatsAppChannelAdapter;
use Plugins\WhatsApp\Infrastructure\Repositories\EloquentWhatsAppRepository;

class WhatsAppPluginServiceProvider extends BasePluginServiceProvider
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
        return $this->app->make(WhatsAppChannelAdapter::class);
    }

    /**
     * Register plugin-specific services.
     */
    protected function registerPluginServices(): void
    {
        // Register repository
        $this->app->bind(
            WhatsAppRepositoryInterface::class,
            EloquentWhatsAppRepository::class
        );

        // Register application service
        $this->app->singleton(WhatsAppApplicationService::class);
    }

    /**
     * Boot plugin-specific services.
     */
    protected function bootPluginServices(): void
    {
        // Nothing additional to boot
    }

    /**
     * Called when plugin is activated - run migrations.
     */
    public function onActivate(): void
    {
        parent::onActivate();

        // Seed default WhatsApp templates if needed
        $this->seedDefaultTemplates();
    }

    /**
     * Called when plugin is uninstalled.
     */
    public function onUninstall(string $dataAction = 'keep'): void
    {
        parent::onUninstall($dataAction);
    }

    /**
     * Mark WhatsApp data as orphaned.
     */
    protected function markDataAsOrphaned(): void
    {
        // Update all WhatsApp connections to orphaned status
        \DB::table('whatsapp_connections')->update([
            'status' => 'orphaned',
            'updated_at' => now(),
        ]);
    }

    /**
     * Archive WhatsApp data.
     */
    protected function archiveData(): void
    {
        // Move messages to archive tables
        \DB::statement('
            INSERT INTO whatsapp_messages_archive
            SELECT * FROM whatsapp_messages
        ');
        \DB::table('whatsapp_messages')->truncate();

        // Disconnect all connections
        \DB::table('whatsapp_connections')->update([
            'status' => 'archived',
            'updated_at' => now(),
        ]);
    }

    /**
     * Delete WhatsApp data.
     */
    protected function deleteData(): void
    {
        // Delete in correct order due to foreign keys
        \DB::table('whatsapp_messages')->delete();
        \DB::table('whatsapp_conversations')->delete();
        \DB::table('whatsapp_templates')->delete();
        \DB::table('whatsapp_connections')->delete();
    }

    /**
     * Seed default message templates.
     */
    private function seedDefaultTemplates(): void
    {
        // Only seed if no templates exist
        if (\DB::table('whatsapp_templates')->exists()) {
            return;
        }

        $templates = [
            [
                'name' => 'Welcome Message',
                'slug' => 'welcome_message',
                'content' => 'Hello {{contact_name}}, welcome to {{company_name}}! How can we help you today?',
                'category' => 'marketing',
                'language' => 'en',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Follow Up',
                'slug' => 'follow_up',
                'content' => 'Hi {{contact_name}}, just following up on our recent conversation. Let us know if you have any questions!',
                'category' => 'utility',
                'language' => 'en',
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('whatsapp_templates')->insert($templates);
    }
}
