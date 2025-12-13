# Xero Integration - Complete Implementation Plan

## Overview

Xero is the first priority accounting integration for VRTX CRM. This document provides a complete implementation plan for bi-directional sync of contacts, invoices, payments, and products.

---

## Xero API Reference

### Authentication
- **Type**: OAuth 2.0
- **Authorization URL**: `https://login.xero.com/identity/connect/authorize`
- **Token URL**: `https://identity.xero.com/connect/token`
- **Token Expiry**: 30 minutes (refresh token valid until revoked)
- **Required Scopes**:
  - `openid profile email`
  - `accounting.transactions`
  - `accounting.contacts`
  - `accounting.settings.read`
  - `offline_access`

### Rate Limits
- 60 calls/minute per app/tenant
- 5,000 calls/day per app/tenant
- Max 5 concurrent requests per tenant
- Bulk endpoints: up to 50 items per request

### Key Endpoints

| Entity | Endpoint | Methods |
|--------|----------|---------|
| Contacts | `/api.xro/2.0/Contacts` | GET, POST, PUT |
| Invoices | `/api.xro/2.0/Invoices` | GET, POST, PUT |
| Quotes | `/api.xro/2.0/Quotes` | GET, POST, PUT |
| Payments | `/api.xro/2.0/Payments` | GET, POST |
| Items | `/api.xro/2.0/Items` | GET, POST, PUT |
| TaxRates | `/api.xro/2.0/TaxRates` | GET |
| Accounts | `/api.xro/2.0/Accounts` | GET |
| Organisations | `/api.xro/2.0/Organisation` | GET |

---

## Data Mapping

### Contact Mapping

| VRTX Field | Xero Field | Direction | Notes |
|------------|------------|-----------|-------|
| `id` | - | - | Internal only |
| `external_ids.xero` | `ContactID` | ↔️ | UUID |
| `first_name` | `FirstName` | ↔️ | |
| `last_name` | `LastName` | ↔️ | |
| `email` | `EmailAddress` | ↔️ | |
| `phone` | `Phones[0].PhoneNumber` | ↔️ | |
| `company_name` | `Name` | ↔️ | Required in Xero |
| `address.line1` | `Addresses[0].AddressLine1` | ↔️ | |
| `address.city` | `Addresses[0].City` | ↔️ | |
| `address.state` | `Addresses[0].Region` | ↔️ | |
| `address.postal_code` | `Addresses[0].PostalCode` | ↔️ | |
| `address.country` | `Addresses[0].Country` | ↔️ | |
| `tax_number` | `TaxNumber` | ↔️ | VAT/GST number |

### Invoice Mapping

| VRTX Field | Xero Field | Direction | Notes |
|------------|------------|-----------|-------|
| `id` | - | - | Internal only |
| `external_ids.xero` | `InvoiceID` | ↔️ | UUID |
| `invoice_number` | `InvoiceNumber` | → | |
| `contact_id` | `Contact.ContactID` | → | Must sync contact first |
| `status` | `Status` | ↔️ | Map to Xero statuses |
| `issue_date` | `Date` | → | |
| `due_date` | `DueDate` | → | |
| `currency` | `CurrencyCode` | → | |
| `subtotal` | `SubTotal` | ← | Calculated by Xero |
| `tax_amount` | `TotalTax` | ← | Calculated by Xero |
| `total` | `Total` | ← | Calculated by Xero |
| `amount_paid` | `AmountPaid` | ← | From Xero |
| `balance_due` | `AmountDue` | ← | From Xero |
| `notes` | `Reference` | → | |
| `line_items[]` | `LineItems[]` | → | See line item mapping |

### Invoice Status Mapping

| VRTX Status | Xero Status | Direction |
|-------------|-------------|-----------|
| `draft` | `DRAFT` | ↔️ |
| `sent` | `SUBMITTED` | → |
| `sent` | `AUTHORISED` | ← |
| `partial` | `AUTHORISED` | ← (check AmountDue) |
| `paid` | `PAID` | ↔️ |
| `cancelled` | `VOIDED` | ↔️ |
| `overdue` | - | Calculated |

### Line Item Mapping

| VRTX Field | Xero Field | Notes |
|------------|------------|-------|
| `description` | `Description` | Required |
| `quantity` | `Quantity` | |
| `unit_price` | `UnitAmount` | |
| `discount_percent` | `DiscountRate` | Percentage |
| `tax_rate` | `TaxType` | Use Xero tax type code |
| `line_total` | `LineAmount` | Calculated |
| `product.sku` | `ItemCode` | Links to Xero Item |
| `account_id` | `AccountCode` | Revenue account |

### Product/Item Mapping

| VRTX Field | Xero Field | Direction |
|------------|------------|-----------|
| `id` | - | Internal |
| `external_ids.xero` | `ItemID` | ↔️ |
| `sku` | `Code` | ↔️ |
| `name` | `Name` | ↔️ |
| `description` | `Description` | ↔️ |
| `unit_price` | `SalesDetails.UnitPrice` | ↔️ |
| `tax_rate` | `SalesDetails.TaxType` | ↔️ |
| `is_active` | - | Filter on sync |

---

## Implementation Details

### Directory Structure

```
backend/app/Integrations/Providers/Xero/
├── XeroIntegration.php          # Main integration class
├── XeroClient.php               # API client wrapper
├── XeroWebhookHandler.php       # Webhook processor
├── Config/
│   └── xero.php                 # Configuration
├── Sync/
│   ├── ContactSync.php          # Contact synchronization
│   ├── InvoiceSync.php          # Invoice synchronization
│   ├── PaymentSync.php          # Payment synchronization
│   ├── ProductSync.php          # Product/Item synchronization
│   └── TaxRateSync.php          # Tax rate import
├── Mappers/
│   ├── ContactMapper.php        # VRTX ↔ Xero contact mapping
│   ├── InvoiceMapper.php        # VRTX ↔ Xero invoice mapping
│   ├── LineItemMapper.php       # Line item mapping
│   └── ProductMapper.php        # Product/Item mapping
├── DTOs/
│   ├── XeroContact.php
│   ├── XeroInvoice.php
│   ├── XeroLineItem.php
│   └── XeroPayment.php
└── Exceptions/
    ├── XeroAuthException.php
    ├── XeroRateLimitException.php
    └── XeroSyncException.php
```

### XeroIntegration Class

```php
<?php

namespace App\Integrations\Providers\Xero;

use App\Integrations\Contracts\AccountingIntegrationInterface;
use App\Integrations\Core\BaseIntegration;
use App\Models\TenantIntegration;

class XeroIntegration extends BaseIntegration implements AccountingIntegrationInterface
{
    protected string $slug = 'xero';
    protected string $name = 'Xero';
    protected string $category = 'accounting';

    protected XeroClient $client;

    public function __construct(?TenantIntegration $tenantIntegration = null)
    {
        parent::__construct($tenantIntegration);
        $this->client = new XeroClient($this);
    }

    public function getCapabilities(): array
    {
        return [
            'contacts' => ['push', 'pull', 'sync'],
            'invoices' => ['push', 'pull', 'sync'],
            'quotes' => ['push'],
            'payments' => ['push', 'pull'],
            'products' => ['pull', 'sync'],
            'tax_rates' => ['pull'],
            'accounts' => ['pull'],
            'webhooks' => ['contacts', 'invoices'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'organisation_id' => [
                    'type' => 'string',
                    'title' => 'Xero Organisation',
                    'description' => 'Select which Xero organisation to connect',
                ],
                'default_revenue_account' => [
                    'type' => 'string',
                    'title' => 'Default Revenue Account',
                    'description' => 'Account code for invoice line items',
                ],
                'sync_contacts' => [
                    'type' => 'boolean',
                    'title' => 'Sync Contacts',
                    'default' => true,
                ],
                'sync_invoices' => [
                    'type' => 'boolean',
                    'title' => 'Sync Invoices',
                    'default' => true,
                ],
                'auto_sync_interval' => [
                    'type' => 'string',
                    'title' => 'Auto Sync Interval',
                    'enum' => ['disabled', '15min', '1hour', '4hours', 'daily'],
                    'default' => '1hour',
                ],
            ],
        ];
    }

    public function getAuthType(): string
    {
        return 'oauth2';
    }

    public function getAuthUrl(?string $state = null): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => config('services.xero.client_id'),
            'redirect_uri' => route('integrations.oauth.callback', ['slug' => 'xero']),
            'scope' => implode(' ', [
                'openid', 'profile', 'email',
                'accounting.transactions',
                'accounting.contacts',
                'accounting.settings.read',
                'offline_access',
            ]),
            'state' => $state ?? csrf_token(),
        ];

        return 'https://login.xero.com/identity/connect/authorize?' . http_build_query($params);
    }

    public function handleCallback(array $params): array
    {
        $response = Http::asForm()->post('https://identity.xero.com/connect/token', [
            'grant_type' => 'authorization_code',
            'code' => $params['code'],
            'redirect_uri' => route('integrations.oauth.callback', ['slug' => 'xero']),
            'client_id' => config('services.xero.client_id'),
            'client_secret' => config('services.xero.client_secret'),
        ]);

        if (!$response->successful()) {
            throw new XeroAuthException('Failed to exchange code for token');
        }

        $tokens = $response->json();

        // Get connected organisations
        $orgsResponse = Http::withToken($tokens['access_token'])
            ->get('https://api.xero.com/connections');

        $organisations = $orgsResponse->json();

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'id_token' => $tokens['id_token'] ?? null,
            'organisations' => $organisations,
        ];
    }

    public function refreshToken(): bool
    {
        $response = Http::asForm()->post('https://identity.xero.com/connect/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->getCredential('refresh_token'),
            'client_id' => config('services.xero.client_id'),
            'client_secret' => config('services.xero.client_secret'),
        ]);

        if (!$response->successful()) {
            $this->markDisconnected('Token refresh failed');
            return false;
        }

        $tokens = $response->json();

        $this->updateCredentials([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return true;
    }

    // Sync Methods

    public function syncContacts(string $direction = 'both'): SyncResult
    {
        return (new Sync\ContactSync($this))->execute($direction);
    }

    public function syncInvoices(string $direction = 'both'): SyncResult
    {
        return (new Sync\InvoiceSync($this))->execute($direction);
    }

    public function syncProducts(string $direction = 'both'): SyncResult
    {
        return (new Sync\ProductSync($this))->execute($direction);
    }

    public function pushContact(Contact $contact): ?string
    {
        return (new Sync\ContactSync($this))->pushSingle($contact);
    }

    public function pushInvoice(Invoice $invoice): ?string
    {
        return (new Sync\InvoiceSync($this))->pushSingle($invoice);
    }

    public function pullTaxRates(): array
    {
        return (new Sync\TaxRateSync($this))->pull();
    }

    public function pullAccounts(): array
    {
        return $this->client->get('/Accounts', [
            'where' => 'Status=="ACTIVE"',
        ]);
    }
}
```

### XeroClient Class

```php
<?php

namespace App\Integrations\Providers\Xero;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class XeroClient
{
    protected XeroIntegration $integration;
    protected string $baseUrl = 'https://api.xero.com/api.xro/2.0';

    public function __construct(XeroIntegration $integration)
    {
        $this->integration = $integration;
    }

    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $params]);
    }

    public function post(string $endpoint, array $data): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    public function put(string $endpoint, array $data): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    protected function request(string $method, string $endpoint, array $options = []): array
    {
        $this->ensureValidToken();
        $this->checkRateLimit();

        $response = Http::withToken($this->integration->getCredential('access_token'))
            ->withHeaders([
                'Xero-tenant-id' => $this->integration->getSetting('organisation_id'),
                'Accept' => 'application/json',
            ])
            ->{strtolower($method)}($this->baseUrl . $endpoint, $options['json'] ?? $options['query'] ?? []);

        $this->recordRateLimit($response);

        if ($response->status() === 429) {
            throw new XeroRateLimitException(
                'Rate limit exceeded',
                $response->header('Retry-After', 60)
            );
        }

        if (!$response->successful()) {
            throw new XeroApiException(
                $response->json('Message', 'API request failed'),
                $response->status()
            );
        }

        return $response->json();
    }

    protected function ensureValidToken(): void
    {
        $expiresAt = $this->integration->getCredential('expires_at');

        if (Carbon::parse($expiresAt)->subMinutes(5)->isPast()) {
            if (!$this->integration->refreshToken()) {
                throw new XeroAuthException('Token refresh failed');
            }
        }
    }

    protected function checkRateLimit(): void
    {
        $key = 'xero_rate_limit:' . $this->integration->tenantIntegration->id;
        $count = Cache::get($key, 0);

        if ($count >= 55) { // Leave buffer before 60
            throw new XeroRateLimitException('Rate limit approaching, please wait');
        }

        Cache::put($key, $count + 1, 60);
    }

    protected function recordRateLimit($response): void
    {
        // Log remaining rate limit from headers if available
        $remaining = $response->header('X-Rate-Limit-Remaining');
        if ($remaining !== null) {
            Log::debug('Xero rate limit remaining: ' . $remaining);
        }
    }
}
```

### Contact Sync Implementation

```php
<?php

namespace App\Integrations\Providers\Xero\Sync;

use App\Integrations\Providers\Xero\XeroIntegration;
use App\Integrations\Providers\Xero\Mappers\ContactMapper;
use App\Models\Contact;
use App\Models\Company;

class ContactSync
{
    protected XeroIntegration $integration;
    protected ContactMapper $mapper;

    public function __construct(XeroIntegration $integration)
    {
        $this->integration = $integration;
        $this->mapper = new ContactMapper();
    }

    public function execute(string $direction): SyncResult
    {
        $result = new SyncResult();

        if (in_array($direction, ['pull', 'both'])) {
            $result->merge($this->pullAll());
        }

        if (in_array($direction, ['push', 'both'])) {
            $result->merge($this->pushAll());
        }

        return $result;
    }

    public function pullAll(): SyncResult
    {
        $result = new SyncResult();
        $page = 1;

        do {
            $response = $this->integration->client->get('/Contacts', [
                'page' => $page,
                'pageSize' => 100,
                'where' => 'IsCustomer==true OR IsSupplier==true',
                'order' => 'UpdatedDateUTC DESC',
            ]);

            foreach ($response['Contacts'] ?? [] as $xeroContact) {
                try {
                    $this->pullSingle($xeroContact);
                    $result->incrementSuccess();
                } catch (\Exception $e) {
                    $result->addError($xeroContact['ContactID'], $e->getMessage());
                }
            }

            $hasMore = count($response['Contacts'] ?? []) === 100;
            $page++;

        } while ($hasMore);

        return $result;
    }

    public function pullSingle(array $xeroContact): Contact|Company
    {
        // Check if this is a company or individual
        $isCompany = !empty($xeroContact['Name']) &&
                     empty($xeroContact['FirstName']) &&
                     empty($xeroContact['LastName']);

        if ($isCompany) {
            return $this->pullAsCompany($xeroContact);
        }

        return $this->pullAsContact($xeroContact);
    }

    protected function pullAsContact(array $xeroContact): Contact
    {
        $mapped = $this->mapper->fromXero($xeroContact);

        $contact = Contact::where('external_ids->xero', $xeroContact['ContactID'])
            ->first();

        if ($contact) {
            $contact->update($mapped);
        } else {
            // Try to match by email
            $contact = Contact::where('email', $mapped['email'])->first();

            if ($contact) {
                $contact->update($mapped);
            } else {
                $contact = Contact::create($mapped);
            }
        }

        $contact->setExternalId('xero', $xeroContact['ContactID']);
        $contact->markSynced('xero');

        return $contact;
    }

    public function pushAll(): SyncResult
    {
        $result = new SyncResult();

        // Get contacts modified since last sync
        $lastSync = $this->integration->tenantIntegration->last_sync_at;

        $contacts = Contact::when($lastSync, function ($q) use ($lastSync) {
            $q->where('updated_at', '>', $lastSync);
        })->get();

        foreach ($contacts as $contact) {
            try {
                $this->pushSingle($contact);
                $result->incrementSuccess();
            } catch (\Exception $e) {
                $result->addError($contact->id, $e->getMessage());
            }
        }

        return $result;
    }

    public function pushSingle(Contact $contact): string
    {
        $mapped = $this->mapper->toXero($contact);
        $externalId = $contact->getExternalId('xero');

        if ($externalId) {
            // Update existing
            $response = $this->integration->client->put(
                "/Contacts/{$externalId}",
                ['Contacts' => [$mapped]]
            );
        } else {
            // Create new
            $response = $this->integration->client->post(
                '/Contacts',
                ['Contacts' => [$mapped]]
            );
        }

        $xeroId = $response['Contacts'][0]['ContactID'];
        $contact->setExternalId('xero', $xeroId);
        $contact->markSynced('xero');

        return $xeroId;
    }
}
```

---

## Frontend Components

### XeroSettings.svelte

```svelte
<script lang="ts">
    import { onMount } from 'svelte';
    import * as Card from '$lib/components/ui/card';
    import * as Select from '$lib/components/ui/select';
    import { Switch } from '$lib/components/ui/switch';
    import { Button } from '$lib/components/ui/button';
    import { Badge } from '$lib/components/ui/badge';
    import { RefreshCw, Settings, Link2, Unlink } from 'lucide-svelte';

    export let integration: TenantIntegration;

    let settings = $state(integration.settings || {});
    let organisations = $state<XeroOrganisation[]>([]);
    let accounts = $state<XeroAccount[]>([]);
    let syncing = $state(false);
    let saving = $state(false);

    onMount(async () => {
        // Load Xero organisations and accounts
        if (integration.status === 'connected') {
            await loadXeroData();
        }
    });

    async function loadXeroData() {
        const [orgsRes, accountsRes] = await Promise.all([
            fetch(`/api/v1/integrations/xero/organisations`),
            fetch(`/api/v1/integrations/xero/accounts`),
        ]);

        organisations = await orgsRes.json();
        accounts = await accountsRes.json();
    }

    async function saveSettings() {
        saving = true;
        try {
            await fetch(`/api/v1/integrations/xero/settings`, {
                method: 'PUT',
                body: JSON.stringify(settings),
            });
            toast.success('Settings saved');
        } catch (e) {
            toast.error('Failed to save settings');
        } finally {
            saving = false;
        }
    }

    async function triggerSync() {
        syncing = true;
        try {
            await fetch(`/api/v1/integrations/xero/sync`, { method: 'POST' });
            toast.success('Sync started');
        } catch (e) {
            toast.error('Failed to start sync');
        } finally {
            syncing = false;
        }
    }
</script>

<div class="space-y-6">
    <!-- Connection Status -->
    <Card.Root>
        <Card.Header>
            <div class="flex items-center justify-between">
                <Card.Title class="flex items-center gap-2">
                    <img src="/integrations/xero-icon.svg" alt="Xero" class="h-6 w-6" />
                    Xero
                </Card.Title>
                <Badge variant={integration.status === 'connected' ? 'default' : 'secondary'}>
                    {integration.status}
                </Badge>
            </div>
        </Card.Header>
        <Card.Content>
            {#if integration.status === 'connected'}
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">{integration.metadata?.organisation_name}</p>
                        <p class="text-sm text-muted-foreground">
                            Last synced: {formatRelativeTime(integration.last_sync_at)}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" onclick={triggerSync} disabled={syncing}>
                            <RefreshCw class="mr-2 h-4 w-4 {syncing ? 'animate-spin' : ''}" />
                            Sync Now
                        </Button>
                        <Button variant="destructive" onclick={disconnect}>
                            <Unlink class="mr-2 h-4 w-4" />
                            Disconnect
                        </Button>
                    </div>
                </div>
            {:else}
                <Button onclick={connect}>
                    <Link2 class="mr-2 h-4 w-4" />
                    Connect to Xero
                </Button>
            {/if}
        </Card.Content>
    </Card.Root>

    {#if integration.status === 'connected'}
        <!-- Sync Settings -->
        <Card.Root>
            <Card.Header>
                <Card.Title>Sync Settings</Card.Title>
            </Card.Header>
            <Card.Content class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">Sync Contacts</p>
                        <p class="text-sm text-muted-foreground">
                            Keep contacts synchronized between VRTX and Xero
                        </p>
                    </div>
                    <Switch bind:checked={settings.sync_contacts} />
                </div>

                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">Sync Invoices</p>
                        <p class="text-sm text-muted-foreground">
                            Push invoices to Xero when created
                        </p>
                    </div>
                    <Switch bind:checked={settings.sync_invoices} />
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Auto Sync Interval</label>
                    <Select.Root bind:value={settings.auto_sync_interval}>
                        <Select.Trigger>
                            {settings.auto_sync_interval || 'Select interval'}
                        </Select.Trigger>
                        <Select.Content>
                            <Select.Item value="disabled">Disabled</Select.Item>
                            <Select.Item value="15min">Every 15 minutes</Select.Item>
                            <Select.Item value="1hour">Every hour</Select.Item>
                            <Select.Item value="4hours">Every 4 hours</Select.Item>
                            <Select.Item value="daily">Daily</Select.Item>
                        </Select.Content>
                    </Select.Root>
                </div>

                <div class="space-y-2">
                    <label class="text-sm font-medium">Default Revenue Account</label>
                    <Select.Root bind:value={settings.default_revenue_account}>
                        <Select.Trigger>
                            {accounts.find(a => a.Code === settings.default_revenue_account)?.Name || 'Select account'}
                        </Select.Trigger>
                        <Select.Content>
                            {#each accounts.filter(a => a.Type === 'REVENUE') as account}
                                <Select.Item value={account.Code}>
                                    {account.Code} - {account.Name}
                                </Select.Item>
                            {/each}
                        </Select.Content>
                    </Select.Root>
                </div>

                <Button onclick={saveSettings} disabled={saving}>
                    <Settings class="mr-2 h-4 w-4" />
                    {saving ? 'Saving...' : 'Save Settings'}
                </Button>
            </Card.Content>
        </Card.Root>

        <!-- Sync History -->
        <Card.Root>
            <Card.Header>
                <Card.Title>Recent Sync Activity</Card.Title>
            </Card.Header>
            <Card.Content>
                <SyncLogTable integrationId={integration.id} limit={10} />
            </Card.Content>
        </Card.Root>
    {/if}
</div>
```

---

## Webhook Handling

### Xero Webhook Events

| Event | Description | Action |
|-------|-------------|--------|
| `CONTACT.CREATED` | New contact in Xero | Pull to VRTX |
| `CONTACT.UPDATED` | Contact modified | Pull updates |
| `INVOICE.CREATED` | New invoice in Xero | Pull if not from VRTX |
| `INVOICE.UPDATED` | Invoice modified | Pull updates |
| `INVOICE.VOIDED` | Invoice cancelled | Update status |
| `PAYMENT.CREATED` | Payment received | Record payment |

### Webhook Controller

```php
<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Integrations\Providers\Xero\XeroWebhookHandler;
use Illuminate\Http\Request;

class XeroWebhookController extends Controller
{
    public function handle(Request $request, string $tenantId)
    {
        // Verify webhook signature
        $signature = $request->header('x-xero-signature');
        $payload = $request->getContent();

        if (!$this->verifySignature($signature, $payload, $tenantId)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Process webhook asynchronously
        dispatch(new ProcessXeroWebhook($tenantId, $request->all()));

        return response()->json(['status' => 'ok']);
    }

    protected function verifySignature(string $signature, string $payload, string $tenantId): bool
    {
        $webhookKey = TenantIntegration::where('tenant_id', $tenantId)
            ->whereHas('integration', fn($q) => $q->where('slug', 'xero'))
            ->value('metadata->webhook_key');

        $expected = base64_encode(hash_hmac('sha256', $payload, $webhookKey, true));

        return hash_equals($expected, $signature);
    }
}
```

---

## Testing Strategy

### Unit Tests

1. **Mapper Tests** - Test data transformation accuracy
2. **Client Tests** - Mock API responses
3. **Sync Tests** - Test sync logic with fixtures

### Integration Tests

1. **OAuth Flow** - Test complete auth cycle
2. **CRUD Operations** - Test each entity type
3. **Webhook Processing** - Test event handling
4. **Error Handling** - Test rate limits, auth failures

### E2E Tests

1. **Connect/Disconnect** - Full OAuth flow
2. **Manual Sync** - Trigger and verify sync
3. **Settings Update** - Change and verify settings

---

## Rollout Plan

### Week 1: Foundation
- [ ] Set up Xero Developer App
- [ ] Implement OAuth2 flow
- [ ] Create XeroClient with rate limiting
- [ ] Basic API endpoint structure

### Week 2: Contact Sync
- [ ] ContactMapper implementation
- [ ] Contact pull from Xero
- [ ] Contact push to Xero
- [ ] Conflict resolution logic

### Week 3: Invoice Sync
- [ ] InvoiceMapper implementation
- [ ] Invoice push to Xero
- [ ] Line item handling
- [ ] Status mapping

### Week 4: Payments & Polish
- [ ] Payment sync
- [ ] Tax rate import
- [ ] Webhook handling
- [ ] Error handling & logging
- [ ] Frontend settings UI

### Week 5: Testing & Launch
- [ ] Comprehensive testing
- [ ] Documentation
- [ ] Beta testing with selected users
- [ ] Production deployment
