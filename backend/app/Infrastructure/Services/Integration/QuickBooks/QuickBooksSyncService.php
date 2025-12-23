<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Integration\QuickBooks;

use App\Domain\Integration\DTOs\ExternalContactDTO;
use App\Domain\Integration\DTOs\ExternalInvoiceDTO;
use App\Domain\Integration\DTOs\SyncResultDTO;
use App\Domain\Integration\Repositories\IntegrationConnectionRepositoryInterface;
use App\Domain\Integration\ValueObjects\SyncDirection;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEntityMapping;
use App\Models\IntegrationSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuickBooksSyncService
{
    private const PROVIDER = 'quickbooks';
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $connectionRepository,
    ) {}

    /**
     * Sync contacts/customers between CRM and QuickBooks
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
            Log::error('QuickBooks contact sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            $result = SyncResultDTO::failure([$e->getMessage()]);
            $this->completeSyncLog($syncLog, $result, $startTime);

            return $result;
        }
    }

    /**
     * Sync invoices between CRM and QuickBooks
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
            Log::error('QuickBooks invoice sync failed', [
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

    private function pullContacts(QuickBooksClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at?->toIso8601String();
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        $startPosition = 1;
        $hasMore = true;

        while ($hasMore) {
            $customers = $lastSync
                ? $client->getChangedCustomers($lastSync)
                : $client->listCustomers($startPosition, self::BATCH_SIZE);

            if (empty($customers)) {
                $hasMore = false;
                continue;
            }

            foreach ($customers as $customerDTO) {
                $processed++;

                try {
                    $result = $this->upsertCrmContact($connection, $customerDTO);
                    if ($result === 'created') {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = "Customer {$customerDTO->externalId}: {$e->getMessage()}";
                }
            }

            // If using CDC (Change Data Capture), we get all changes at once
            if ($lastSync) {
                $hasMore = false;
            } else {
                $startPosition += self::BATCH_SIZE;
                $hasMore = count($customers) === self::BATCH_SIZE;
            }
        }

        return SyncResultDTO::success(
            processed: $processed,
            created: $created,
            updated: $updated,
            warnings: $errors,
        );
    }

    private function pushContacts(QuickBooksClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at;
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        // Get CRM contacts that need to be synced
        $contacts = $this->getCrmContactsForSync($connection, $lastSync);

        foreach ($contacts as $contact) {
            $processed++;

            try {
                $mapping = $this->getEntityMapping($connection, 'contacts', $contact['id']);

                $contactData = $this->mapCrmContactToExternal($contact, $connection);

                if ($mapping) {
                    // Update existing QuickBooks customer
                    $result = $client->updateCustomer($mapping->external_id, $contactData);
                    if ($result) {
                        $updated++;
                        $this->updateEntityMapping($mapping, $result);
                    }
                } else {
                    // Create new QuickBooks customer
                    $result = $client->createCustomer($contactData);
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

    private function bidirectionalContactSync(QuickBooksClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        // Pull first (external is source of truth for accounting data)
        $pullResult = $this->pullContacts($client, $connection, $fullSync);

        // Then push CRM changes
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

    private function pullInvoices(QuickBooksClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at?->toIso8601String();
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        $startPosition = 1;
        $hasMore = true;

        while ($hasMore) {
            $invoices = $lastSync
                ? $client->getChangedInvoices($lastSync)
                : $client->listInvoices($startPosition, self::BATCH_SIZE);

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

            if ($lastSync) {
                $hasMore = false;
            } else {
                $startPosition += self::BATCH_SIZE;
                $hasMore = count($invoices) === self::BATCH_SIZE;
            }
        }

        return SyncResultDTO::success(
            processed: $processed,
            created: $created,
            updated: $updated,
            warnings: $errors,
        );
    }

    private function pushInvoices(QuickBooksClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
    {
        $lastSync = $fullSync ? null : $connection->last_synced_at;
        $created = 0;
        $updated = 0;
        $processed = 0;
        $errors = [];

        // Get CRM invoices that need to be synced
        $invoices = $this->getCrmInvoicesForSync($connection, $lastSync);

        foreach ($invoices as $invoice) {
            $processed++;

            try {
                $mapping = $this->getEntityMapping($connection, 'invoices', $invoice['id']);

                // Get the customer mapping first
                $customerMapping = $this->getEntityMapping($connection, 'contacts', $invoice['contact_id'] ?? $invoice['customer_id']);
                if (!$customerMapping) {
                    $errors[] = "Invoice {$invoice['id']}: No linked QuickBooks customer found";
                    continue;
                }

                $invoiceData = $this->mapCrmInvoiceToExternal($invoice, $customerMapping->external_id, $connection);

                if ($mapping) {
                    // QuickBooks invoices are generally not updated after creation
                    // Only new invoices are pushed
                    $updated++;
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

    private function bidirectionalInvoiceSync(QuickBooksClient $client, IntegrationConnection $connection, bool $fullSync): SyncResultDTO
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
        $mapping = IntegrationEntityMapping::where('integration_connection_id', $connection->id)
            ->where('entity_type', 'contacts')
            ->where('external_id', $dto->externalId)
            ->first();

        if ($mapping) {
            // Update existing CRM contact
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
            // Link existing contact
            IntegrationEntityMapping::create([
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

        IntegrationEntityMapping::create([
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
        $mapping = IntegrationEntityMapping::where('integration_connection_id', $connection->id)
            ->where('entity_type', 'invoices')
            ->where('external_id', $dto->externalId)
            ->first();

        // Find the linked CRM contact
        $contactMapping = IntegrationEntityMapping::where('integration_connection_id', $connection->id)
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

        // Create new CRM invoice
        $invoiceId = DB::table('module_records')->insertGetId([
            'module_id' => $this->getModuleId('invoices'),
            'data' => json_encode($invoiceData),
            'created_by' => $connection->user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        IntegrationEntityMapping::create([
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
        // Get custom field mappings
        $fieldMappings = $connection->fieldMappings()
            ->where('entity_type', 'contacts')
            ->get()
            ->keyBy('external_field');

        // Default mapping
        $data = [
            'first_name' => $dto->firstName,
            'last_name' => $dto->lastName,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'mobile' => $dto->mobile,
            'company' => $dto->companyName,
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
            'description' => $dto->notes,
            'quickbooks_id' => $dto->externalId,
            'quickbooks_sync' => true,
        ];

        // Apply custom mappings
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
            'status' => $dto->status,
            'currency' => $dto->currency,
            'subtotal' => $dto->subtotal,
            'tax_amount' => $dto->taxAmount,
            'total' => $dto->total,
            'amount_due' => $dto->amountDue,
            'amount_paid' => $dto->amountPaid,
            'invoice_date' => $dto->invoiceDate?->format('Y-m-d'),
            'due_date' => $dto->dueDate?->format('Y-m-d'),
            'terms' => $dto->terms,
            'notes' => $dto->notes,
            'line_items' => $dto->lineItems,
            'quickbooks_id' => $dto->externalId,
            'quickbooks_sync' => true,
        ], fn($v) => $v !== null);
    }

    private function mapCrmContactToExternal(array $contact, IntegrationConnection $connection): array
    {
        return [
            'display_name' => trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? '')) ?: ($contact['company'] ?? 'Unknown'),
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
            'notes' => $contact['description'] ?? null,
        ];
    }

    private function mapCrmInvoiceToExternal(array $invoice, string $externalCustomerId, IntegrationConnection $connection): array
    {
        return [
            'external_customer_id' => $externalCustomerId,
            'invoice_number' => $invoice['invoice_number'] ?? null,
            'invoice_date' => $invoice['invoice_date'] ?? null,
            'due_date' => $invoice['due_date'] ?? null,
            'notes' => $invoice['notes'] ?? null,
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

    private function createClient(IntegrationConnection $connection): QuickBooksClient
    {
        $credentials = $connection->getDecryptedCredentials();
        $settings = $connection->settings ?? [];

        return new QuickBooksClient(
            accessToken: $credentials['access_token'],
            realmId: $credentials['realm_id'] ?? $settings['realm_id'] ?? '',
            sandbox: $settings['sandbox'] ?? config('services.quickbooks.sandbox', false),
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
        return IntegrationEntityMapping::where('integration_connection_id', $connection->id)
            ->where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->first();
    }

    private function createEntityMapping(IntegrationConnection $connection, string $entityType, int $localId, string $externalId): IntegrationEntityMapping
    {
        return IntegrationEntityMapping::create([
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
        return IntegrationSyncLog::create([
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
