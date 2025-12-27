<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Portal\Entities\PortalActivityLog;
use App\Domain\Portal\Entities\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Portal\Entities\PortalActivityLog>
 */
class PortalActivityLogFactory extends Factory
{
    protected $model = PortalActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $action = $this->faker->randomElement(array_keys(PortalActivityLog::getActionLabels()));
        $resourceType = $this->getResourceTypeForAction($action);

        return [
            'portal_user_id' => PortalUser::factory(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceType ? $this->faker->numberBetween(1, 1000) : null,
            'metadata' => $this->getMetadataForAction($action),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    /**
     * Get resource type based on action.
     */
    private function getResourceTypeForAction(string $action): ?string
    {
        return match ($action) {
            'view_deal' => 'deals',
            'view_invoice' => 'invoices',
            'view_quote' => 'quotes',
            'download_document', 'sign_document' => 'documents',
            'submit_ticket', 'reply_ticket' => 'tickets',
            default => null,
        };
    }

    /**
     * Get metadata based on action.
     */
    private function getMetadataForAction(string $action): array
    {
        return match ($action) {
            'login' => ['ip' => $this->faker->ipv4()],
            'download_document' => ['document_name' => $this->faker->words(3, true) . '.pdf'],
            'sign_document' => ['signature_request_id' => $this->faker->numberBetween(1, 100)],
            default => [],
        };
    }

    /**
     * Login activity.
     */
    public function login(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'login',
            'resource_type' => null,
            'resource_id' => null,
            'metadata' => ['ip' => $this->faker->ipv4()],
        ]);
    }

    /**
     * Logout activity.
     */
    public function logout(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'logout',
            'resource_type' => null,
            'resource_id' => null,
            'metadata' => [],
        ]);
    }

    /**
     * View deal activity.
     */
    public function viewDeal(int $dealId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'view_deal',
            'resource_type' => 'deals',
            'resource_id' => $dealId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * View invoice activity.
     */
    public function viewInvoice(int $invoiceId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'view_invoice',
            'resource_type' => 'invoices',
            'resource_id' => $invoiceId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * View quote activity.
     */
    public function viewQuote(int $quoteId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'view_quote',
            'resource_type' => 'quotes',
            'resource_id' => $quoteId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * Download document activity.
     */
    public function downloadDocument(int $documentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'download_document',
            'resource_type' => 'documents',
            'resource_id' => $documentId ?? $this->faker->numberBetween(1, 1000),
            'metadata' => ['document_name' => $this->faker->words(3, true) . '.pdf'],
        ]);
    }

    /**
     * Sign document activity.
     */
    public function signDocument(int $documentId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'sign_document',
            'resource_type' => 'documents',
            'resource_id' => $documentId ?? $this->faker->numberBetween(1, 1000),
            'metadata' => ['signature_request_id' => $this->faker->numberBetween(1, 100)],
        ]);
    }

    /**
     * Submit ticket activity.
     */
    public function submitTicket(int $ticketId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'submit_ticket',
            'resource_type' => 'tickets',
            'resource_id' => $ticketId ?? $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * Recent activity.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }
}
