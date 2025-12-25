<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Integration\Xero;

use App\Domain\Integration\DTOs\ExternalContactDTO;
use App\Domain\Integration\DTOs\ExternalInvoiceDTO;
use App\Domain\Integration\DTOs\SyncResultDTO;
use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domain\Integration\ValueObjects\SyncDirection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XeroSyncService
{
    private const PROVIDER = 'xero';
    private const PAGE_SIZE = 100;

    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $connectionRepository,
    ) {}

    /**
     * Sync contacts between CRM and Xero
     */
    public function syncContacts(
        IntegrationConnection $connection,
        SyncDirection $direction = SyncDirection::BOTH,
        bool $fullSync = false
    ): SyncResultDTO {
        $startTime = microtime(true);
        $client = $this->createClient($connection);

        $syncLog = $this->createSyncLog($connection, 'contacts', $direction->value);

        try {
            $result = match ($direction) {
                SyncDirection::PULL => $this->pullContacts($client, $connection, $fullSync),
                SyncDirection::PUSH => $this->pushContacts($client, $connection, $fullSync),
                SyncDirection::BOTH => $this->bidirectionalContactSync($client, $connection, $fullSync),
            };

            $this->completeSyncLog($syncLog, $result, $startTime);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Xero contact sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $result = SyncResultDTO::failure([$e->getMessage()]);
            $this->completeSyncLog($syncLog, $result, $startTime);

            return $result;
        }
    }

    /**
     * Sync invoices between CRM and Xero
     */
    public function syncInvoices(
        IntegrationConnection $connection,
        SyncDirection $direction = SyncDirection::BOTH,
        bool $fullSync = false
    ): SyncResultDTO {
        $startTime = microtime(true);
        $client = $this->createClient($connection);

        $syncLog = $this->createSyncLog($connection, 'invoices', $direction->value);

        try {
            $result = match ($direction) {
                SyncDirection::PULL => $this->pullInvoices($client, $connection, $fullSync),
                SyncDirection::PUSH => $this->pushInvoices($client, $connection, $fullSync),
                SyncDirection::BOTH => $this->bidirectionalInvoiceSync($client, $connection, $fullSync),
            };

            $this->completeSyncLog($syncLog, $result, $startTime);

            return $result;
        } catch (\Throwable $e) {
            Log::error('Xero invoice sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            $result = SyncResultDTO::failure([$e->getMessage()]);
            $this->completeSyncLog($syncLog, $result, $startTime);

            return $result;
        }
    }

    /**
     * Full sync of all entities
     */
    public function fullSync(IntegrationConnection $connection): array
    {
        return [
            'contacts' => $this->syncContacts($connection, SyncDirection::BOTH, true),
            'invoices' => $this->syncInvoices($connection, SyncDirection::BOTH, true),
        ];
    }

    /**
     * Incremental sync since last sync time
     */
    public function incrementalSync(IntegrationConnection $connection): array
    {
        return [
            'contacts' => $this->syncContacts($connection, SyncDirection::BOTH, false),
            'invoices' => $this->syncInvoices($connection, SyncDirection::BOTH, false),
        ];
    }

    // ========================================
    // Contact Sync Operations
    // ========================================

    private function pullContacts(XeroClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at?->format('Y-m-d\TH:i:s');
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $contacts = $client->listContacts($page, $lastSync);

            if (empty($contacts)) {
                $hasMore = false;
                continue;
            }

            foreach ($contacts as $contactDTO) {
                $processed++;

                try {
                    $result = $this->upsertCrmContact($connection, $contactDTO);
                    if ($result === 'created') {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Contact {$contactDTO->externalId}: {$e->getMessage()}";
                }
            }

            $page++;
            $hasMore = count($contacts) === self::PAGE_SIZE;
        }

        return SyncResultDTO::success(
            processed: $processed,
            created: $created,
            updated: $updated,
            warnings: $errors,
        );
    }

    private function pushContacts(XeroClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at;
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        $contacts = $this->getCrmContactsForSync($connection, $lastSync);

        foreach ($contacts as $contact) {
            $processed++;

            try {
                $mapping = $this->getEntityMapping($connection, 'contacts', $contact['id']);

                $contactData = $this->mapCrmContactToExternal($contact, $connection);

                if ($mapping) {
                    $result = $client->updateContact($mapping->external_id, $contactData);
                    if ($result) {
                        $updated++;
                        $this->updateEntityMapping($mapping, $result);
                    }
                } else {
                    $result = $client->createContact($contactData);
                    if ($result) {
                        $created++;
                        $this->createEntityMapping($connection, 'contacts', $contact['id'], $result->externalId);
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Contact {$contact['id']}: {$e->getMessage()}";
            }
        }

        return SyncResultDTO::success(
            processed: $processed,
            created: $created,
            updated: $updated,
            warnings: $errors,
        );
    }

    private function bidirectionalContactSync(XeroClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $pullResult = $this->pullContacts($client, $connection, $fullSync);
        $pushResult = $this->pushContacts($client, $connection, $fullSync);

        return SyncResultDTO::success(
            processed: $pullResult->recordsProcessed + $pushResult->recordsProcessed,
            created: $pullResult->recordsCreated + $pushResult->recordsCreated,
            updated: $pullResult->recordsUpdated + $pushResult->recordsUpdated,
            warnings: array_merge($pullResult->warnings, $pushResult->warnings),
        );
    }

    // ========================================
    // Invoice Sync Operations
    // ========================================

    private function pullInvoices(XeroClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at?->format('Y-m-d\TH:i:s');
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $invoices = $client->listInvoices($page, $lastSync);

            if (empty($invoices)) {
                $hasMore = false;
                continue;
            }

            foreach ($invoices as $invoiceDTO) {
                $processed++;

                try {
                    $result = $this->upsertCrmInvoice($connection, $invoiceDTO);
                    if ($result === 'created') {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Invoice {$invoiceDTO->externalId}: {$e->getMessage()}";
                }
            }

            $page++;
            $hasMore = count($invoices) === self::PAGE_SIZE;
        }

        return SyncResultDTO::success(
            processed: $processed,
            created: $created,
            updated: $updated,
            warnings: $errors,
        );
    }

    private function pushInvoices(XeroClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at;
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        $invoices = $this->getCrmInvoicesForSync($connection, $lastSync);

        foreach ($invoices as $invoice) {
            $processed++;

            try {
                $mapping = $this->getEntityMapping($connection, 'invoices', $invoice['id']);

                $customerMapping = $this->getEntityMapping($connection, 'contacts', $invoice['contact_id'] ?? $invoice['customer_id']);
                if (!$customerMapping) {
                    $errors[] = "Invoice {$invoice['id']}: No linked Xero contact found";
                    continue;
                }

                $invoiceData = $this->mapCrmInvoiceToExternal($invoice, $customerMapping->external_id, $connection);

                if ($mapping) {
                    $result = $client->updateInvoice($mapping->external_id, $invoiceData);
                    if ($result) {
                        $updated++;
                        $this->updateEntityMapping($mapping, $result);
                    }
                } else {
                    $result = $client->createInvoice($invoiceData);
                    if ($result) {
                        $created++;
                        $this->createEntityMapping($connection, 'invoices', $invoice['id'], $result->externalId);
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "Invoice {$invoice['id']}: {$e->getMessage()}";
            }
        }

        return SyncResultDTO::success(
            processed: $processed,
            created: $created,
            updated: $updated,
            warnings: $errors,
        );
    }

    private function bidirectionalInvoiceSync(XeroClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $pullResult = $this->pullInvoices($client, $connection, $fullSync);
        $pushResult = $this->pushInvoices($client, $connection, $fullSync);

        return SyncResultDTO::success(
            processed: $pullResult->recordsProcessed + $pushResult->recordsProcessed,
            created: $pullResult->recordsCreated + $pushResult->recordsCreated,
            updated: $pullResult->recordsUpdated + $pushResult->recordsUpdated,
            warnings: array_merge($pullResult->warnings, $pushResult->warnings),
        );
    }

    // ========================================
    // CRM Data Operations
    // ========================================

    private function upsertCrmContact(IntegrationConnection $connection, ExternalContactDTO $dto): string
    {
        $mapping = DB::table('integration_entity_mappings')->where('integration_connection_id', $connection->id)
            ->where('entity_type', 'contacts')
            ->where('external_id', $dto->externalId)
            ->first();

        if ($mapping) {
            DB::table('module_records')
                ->where('id', $mapping->local_id)
                ->update([
                    'data' => json_encode(array_merge(
                        json_decode(DB::table('module_records')->where('id', $mapping->local_id)->value('data') ?? '{}', true),
                        $this->mapExternalToFieldData($dto, $connection)
                    )),
                    'updated_at' => now(),
                ]);

            $mapping->update([
                'external_data' => $dto->toArray(),
                'last_synced_at' => now(),
            ]);

            return 'updated';
        }

        // Check if contact exists by email
        $existingContact = null;
        if ($dto->email) {
            $existingContact = DB::table('module_records')
                ->where('module_id', $this->getModuleId('contacts'))
                ->whereRaw("JSON_EXTRACT(data, '$.email') = ?", [$dto->email])
                ->first();
        }

        if ($existingContact) {
            DB::table('integration_entity_mappings')->insertGetId([
                'integration_connection_id' => $connection->id,
                'entity_type' => 'contacts',
                'local_id' => $existingContact->id,
                'external_id' => $dto->externalId,
                'external_data' => $dto->toArray(),
                'last_synced_at' => now(),
            ]);

            return 'updated';
        }

        // Create new CRM contact
        $contactId = DB::table('module_records')->insertGetId([
            'module_id' => $this->getModuleId('contacts'),
            'data' => json_encode($this->mapExternalToFieldData($dto, $connection)),
            'created_by' => $connection->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('integration_entity_mappings')->insertGetId([
            'integration_connection_id' => $connection->id,
            'entity_type' => 'contacts',
            'local_id' => $contactId,
            'external_id' => $dto->externalId,
            'external_data' => $dto->toArray(),
            'last_synced_at' => now(),
        ]);

        return 'created';
    }

    private function upsertCrmInvoice(IntegrationConnection $connection, ExternalInvoiceDTO $dto): string
    {
        $mapping = DB::table('integration_entity_mappings')->where('integration_connection_id', $connection->id)
            ->where('entity_type', 'invoices')
            ->where('external_id', $dto->externalId)
            ->first();

        $contactMapping = DB::table('integration_entity_mappings')->where('integration_connection_id', $connection->id)
            ->where('entity_type', 'contacts')
            ->where('external_id', $dto->externalCustomerId)
            ->first();

        $invoiceData = $this->mapExternalInvoiceToFieldData($dto, $contactMapping?->local_id, $connection);

        if ($mapping) {
            DB::table('module_records')
                ->where('id', $mapping->local_id)
                ->update([
                    'data' => json_encode(array_merge(
                        json_decode(DB::table('module_records')->where('id', $mapping->local_id)->value('data') ?? '{}', true),
                        $invoiceData
                    )),
                    'updated_at' => now(),
                ]);

            $mapping->update([
                'external_data' => $dto->toArray(),
                'last_synced_at' => now(),
            ]);

            return 'updated';
        }

        $invoiceId = DB::table('module_records')->insertGetId([
            'module_id' => $this->getModuleId('invoices'),
            'data' => json_encode($invoiceData),
            'created_by' => $connection->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('integration_entity_mappings')->insertGetId([
            'integration_connection_id' => $connection->id,
            'entity_type' => 'invoices',
            'local_id' => $invoiceId,
            'external_id' => $dto->externalId,
            'external_data' => $dto->toArray(),
            'last_synced_at' => now(),
        ]);

        return 'created';
    }

    private function getCrmContactsForSync(IntegrationConnection $connection, ?\DateTimeInterface $since): array
    {
        $query = DB::table('module_records')
            ->where('module_id', $this->getModuleId('contacts'));

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(fn($r) => array_merge(
            ['id' => $r->id],
            json_decode($r->data, true)
        ))->toArray();
    }

    private function getCrmInvoicesForSync(IntegrationConnection $connection, ?\DateTimeInterface $since): array
    {
        $query = DB::table('module_records')
            ->where('module_id', $this->getModuleId('invoices'));

        if ($since) {
            $query->where('updated_at', '>', $since);
        }

        return $query->get()->map(fn($r) => array_merge(
            ['id' => $r->id],
            json_decode($r->data, true)
        ))->toArray();
    }

    // ========================================
    // Field Mapping
    // ========================================

    private function mapExternalToFieldData(ExternalContactDTO $dto, IntegrationConnection $connection): array
    {
        $fieldMappings = $connection->fieldMappings()
            ->where('entity_type', 'contacts')
            ->get()
            ->keyBy('external_field');

        $data = [
            'first_name' => $dto->firstName,
            'last_name' => $dto->lastName,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'mobile' => $dto->mobile,
            'company' => $dto->companyName ?? $dto->displayName,
            'website' => $dto->website,
            'billing_street' => trim(($dto->billingAddressLine1 ?? '') . ' ' . ($dto->billingAddressLine2 ?? '')),
            'billing_city' => $dto->billingCity,
            'billing_state' => $dto->billingState,
            'billing_zip' => $dto->billingPostalCode,
            'billing_country' => $dto->billingCountry,
            'shipping_street' => trim(($dto->shippingAddressLine1 ?? '') . ' ' . ($dto->shippingAddressLine2 ?? '')),
            'shipping_city' => $dto->shippingCity,
            'shipping_state' => $dto->shippingState,
            'shipping_zip' => $dto->shippingPostalCode,
            'shipping_country' => $dto->shippingCountry,
            'tax_number' => $dto->taxNumber,
            'xero_id' => $dto->externalId,
            'xero_sync' => true,
        ];

        foreach ($fieldMappings as $externalField => $mapping) {
            $value = $dto->metadata[$externalField] ?? null;
            if ($value !== null && $mapping->local_field) {
                $data[$mapping->local_field] = $this->transformValue($value, $mapping->transform_rules);
            }
        }

        return array_filter($data, fn($v) => $v !== null && $v !== '');
    }

    private function mapExternalInvoiceToFieldData(ExternalInvoiceDTO $dto, ?int $contactId, IntegrationConnection $connection): array
    {
        return array_filter([
            'contact_id' => $contactId,
            'invoice_number' => $dto->invoiceNumber,
            'reference' => $dto->reference,
            'status' => $dto->status,
            'currency' => $dto->currency,
            'subtotal' => $dto->subtotal,
            'tax_amount' => $dto->taxAmount,
            'total' => $dto->total,
            'amount_due' => $dto->amountDue,
            'amount_paid' => $dto->amountPaid,
            'invoice_date' => $dto->invoiceDate?->format('Y-m-d'),
            'due_date' => $dto->dueDate?->format('Y-m-d'),
            'line_items' => $dto->lineItems,
            'xero_id' => $dto->externalId,
            'xero_sync' => true,
        ], fn($v) => $v !== null);
    }

    private function mapCrmContactToExternal(array $contact, IntegrationConnection $connection): array
    {
        $name = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
        if (empty($name)) {
            $name = $contact['company'] ?? $contact['email'] ?? 'Unknown';
        }

        return [
            'display_name' => $name,
            'company_name' => $contact['company'] ?? null,
            'first_name' => $contact['first_name'] ?? null,
            'last_name' => $contact['last_name'] ?? null,
            'email' => $contact['email'] ?? null,
            'phone' => $contact['phone'] ?? null,
            'mobile' => $contact['mobile'] ?? null,
            'website' => $contact['website'] ?? null,
            'billing_address_line1' => $contact['billing_street'] ?? null,
            'billing_city' => $contact['billing_city'] ?? null,
            'billing_state' => $contact['billing_state'] ?? null,
            'billing_postal_code' => $contact['billing_zip'] ?? null,
            'billing_country' => $contact['billing_country'] ?? null,
            'tax_number' => $contact['tax_number'] ?? null,
        ];
    }

    private function mapCrmInvoiceToExternal(array $invoice, string $externalCustomerId, IntegrationConnection $connection): array
    {
        return [
            'external_customer_id' => $externalCustomerId,
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'reference' => $invoice['reference'] ?? null,
            'invoice_date' => $invoice['invoice_date'] ?? null,
            'due_date' => $invoice['due_date'] ?? null,
            'currency' => $invoice['currency'] ?? 'USD',
            'line_items' => $invoice['line_items'] ?? [],
        ];
    }

    private function transformValue(mixed $value, ?array $rules): mixed
    {
        if (!$rules || empty($rules)) {
            return $value;
        }

        foreach ($rules as $rule) {
            $value = match ($rule['type'] ?? null) {
                'uppercase' => strtoupper((string) $value),
                'lowercase' => strtolower((string) $value),
                'trim' => trim((string) $value),
                'prefix' => ($rule['value'] ?? '') . $value,
                'suffix' => $value . ($rule['value'] ?? ''),
                'map' => $rule['mapping'][$value] ?? $value,
                default => $value,
            };
        }

        return $value;
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createClient(IntegrationConnection $connection): XeroClient
    {
        $credentials = $connection->getDecryptedCredentials();
        $settings = $connection->settings ?? [];

        return new XeroClient(
            accessToken: $credentials['access_token'],
            tenantId: $credentials['tenant_id'] ?? $settings['tenant_id'] ?? '',
        );
    }

    private function getModuleId(string $slug): int
    {
        static $cache = [];

        if (!isset($cache[$slug])) {
            $cache[$slug] = DB::table('modules')->where('slug', $slug)->value('id');
        }

        return $cache[$slug];
    }

    private function getEntityMapping(IntegrationConnection $connection, string $entityType, int $localId): ?IntegrationEntityMapping
    {
        return DB::table('integration_entity_mappings')->where('integration_connection_id', $connection->id)
            ->where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->first();
    }

    private function createEntityMapping(IntegrationConnection $connection, string $entityType, int $localId, string $externalId): IntegrationEntityMapping
    {
        return DB::table('integration_entity_mappings')->insertGetId([
            'integration_connection_id' => $connection->id,
            'entity_type' => $entityType,
            'local_id' => $localId,
            'external_id' => $externalId,
            'last_synced_at' => now(),
        ]);
    }

    private function updateEntityMapping(IntegrationEntityMapping $mapping, ExternalContactDTO|ExternalInvoiceDTO $dto): void
    {
        $mapping->update([
            'external_data' => $dto->toArray(),
            'last_synced_at' => now(),
        ]);
    }

    private function createSyncLog(IntegrationConnection $connection, string $entityType, string $direction): IntegrationSyncLog
    {
        return DB::table('integration_sync_logs')->insertGetId([
            'integration_connection_id' => $connection->id,
            'entity_type' => $entityType,
            'direction' => $direction,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    private function completeSyncLog(IntegrationSyncLog $log, SyncResultDTO $result, float $startTime): void
    {
        $log->update([
            'status' => $result->success ? 'completed' : 'failed',
            'records_processed' => $result->recordsProcessed,
            'records_created' => $result->recordsCreated,
            'records_updated' => $result->recordsUpdated,
            'records_failed' => $result->recordsFailed,
            'error_message' => $result->success ? null : implode('; ', $result->errors),
            'completed_at' => now(),
            'metadata' => [
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'warnings' => $result->warnings,
            ],
        ]);
    }
}
