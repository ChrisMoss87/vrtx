# VRTX CRM Integrations Architecture

## Overview

This document outlines the plugin/integration architecture for VRTX CRM, designed to support native integrations with third-party services while maintaining a clean, extensible codebase.

---

## Architecture Principles

1. **Native First** - Embedded integrations with white-labeled UI
2. **Tenant Isolation** - Each tenant has separate credentials and settings
3. **Event-Driven** - Use events for loose coupling
4. **Resilient** - Queue-based with retry logic
5. **Auditable** - Log all sync operations

---

## Core Integration Framework

### Database Schema

```sql
-- Integration definitions (system-wide)
CREATE TABLE integrations (
    id SERIAL PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,       -- 'xero', 'quickbooks', 'stripe'
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,          -- 'accounting', 'payment', 'email', 'communication'
    icon_url VARCHAR(255),
    auth_type VARCHAR(20) NOT NULL,         -- 'oauth2', 'api_key', 'basic'
    config_schema JSONB NOT NULL,           -- JSON Schema for settings
    capabilities JSONB NOT NULL,            -- What this integration can do
    is_active BOOLEAN DEFAULT true,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Tenant-specific integration connections (per-tenant)
CREATE TABLE tenant_integrations (
    id SERIAL PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL,
    integration_id INT REFERENCES integrations(id),
    status VARCHAR(20) DEFAULT 'disconnected', -- 'connected', 'disconnected', 'error', 'expired'
    credentials JSONB,                      -- Encrypted: tokens, api keys
    settings JSONB,                         -- User preferences
    metadata JSONB,                         -- Integration-specific data
    last_sync_at TIMESTAMP,
    last_error TEXT,
    connected_at TIMESTAMP,
    connected_by INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(tenant_id, integration_id)
);

-- Sync logs for auditing
CREATE TABLE integration_sync_logs (
    id SERIAL PRIMARY KEY,
    tenant_integration_id INT REFERENCES tenant_integrations(id),
    sync_type VARCHAR(50) NOT NULL,         -- 'full', 'incremental', 'push', 'pull'
    entity_type VARCHAR(50),                -- 'invoice', 'contact', 'payment'
    entity_id INT,
    external_id VARCHAR(100),
    direction VARCHAR(10) NOT NULL,         -- 'push', 'pull'
    status VARCHAR(20) NOT NULL,            -- 'pending', 'success', 'failed', 'skipped'
    request_data JSONB,
    response_data JSONB,
    error_message TEXT,
    duration_ms INT,
    created_at TIMESTAMP
);

-- Field mappings for data transformation
CREATE TABLE integration_field_mappings (
    id SERIAL PRIMARY KEY,
    tenant_integration_id INT REFERENCES tenant_integrations(id),
    entity_type VARCHAR(50) NOT NULL,
    vrtx_field VARCHAR(100) NOT NULL,
    external_field VARCHAR(100) NOT NULL,
    transform_type VARCHAR(20),             -- 'direct', 'lookup', 'formula'
    transform_config JSONB,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Webhook endpoints for receiving data
CREATE TABLE integration_webhooks (
    id SERIAL PRIMARY KEY,
    tenant_integration_id INT REFERENCES tenant_integrations(id),
    webhook_id VARCHAR(100),
    endpoint_url VARCHAR(255),
    secret_key VARCHAR(255),
    events JSONB,                           -- Events subscribed to
    is_active BOOLEAN DEFAULT true,
    last_received_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## PHP Integration Framework

### Directory Structure

```
backend/
├── app/
│   ├── Integrations/
│   │   ├── Contracts/
│   │   │   ├── IntegrationInterface.php
│   │   │   ├── AccountingIntegrationInterface.php
│   │   │   ├── PaymentIntegrationInterface.php
│   │   │   ├── EmailIntegrationInterface.php
│   │   │   └── SyncableInterface.php
│   │   ├── Core/
│   │   │   ├── IntegrationManager.php
│   │   │   ├── IntegrationRegistry.php
│   │   │   ├── OAuth2Handler.php
│   │   │   ├── WebhookHandler.php
│   │   │   ├── SyncEngine.php
│   │   │   └── FieldMapper.php
│   │   ├── Providers/
│   │   │   ├── Xero/
│   │   │   │   ├── XeroIntegration.php
│   │   │   │   ├── XeroClient.php
│   │   │   │   ├── XeroWebhookHandler.php
│   │   │   │   ├── Sync/
│   │   │   │   │   ├── ContactSync.php
│   │   │   │   │   ├── InvoiceSync.php
│   │   │   │   │   └── PaymentSync.php
│   │   │   │   └── Mappers/
│   │   │   │       ├── ContactMapper.php
│   │   │   │       └── InvoiceMapper.php
│   │   │   ├── QuickBooks/
│   │   │   ├── Stripe/
│   │   │   ├── Slack/
│   │   │   └── Mailchimp/
│   │   ├── Events/
│   │   │   ├── IntegrationConnected.php
│   │   │   ├── IntegrationDisconnected.php
│   │   │   ├── SyncCompleted.php
│   │   │   └── SyncFailed.php
│   │   └── Jobs/
│   │       ├── ProcessIntegrationSync.php
│   │       ├── ProcessWebhook.php
│   │       └── RefreshOAuthToken.php
│   ├── Http/Controllers/Api/
│   │   └── Integrations/
│   │       ├── IntegrationController.php
│   │       ├── OAuthController.php
│   │       └── WebhookController.php
│   └── Models/
│       ├── Integration.php
│       ├── TenantIntegration.php
│       ├── IntegrationSyncLog.php
│       ├── IntegrationFieldMapping.php
│       └── IntegrationWebhook.php
```

### Core Interfaces

```php
<?php

namespace App\Integrations\Contracts;

interface IntegrationInterface
{
    public function getSlug(): string;
    public function getName(): string;
    public function getCategory(): string;
    public function getCapabilities(): array;
    public function getConfigSchema(): array;
    public function getAuthType(): string;

    public function connect(array $credentials): bool;
    public function disconnect(): bool;
    public function isConnected(): bool;
    public function testConnection(): bool;

    public function getAuthUrl(?string $state = null): string;
    public function handleCallback(array $params): array;
    public function refreshToken(): bool;
}

interface AccountingIntegrationInterface extends IntegrationInterface
{
    // Contacts
    public function syncContacts(string $direction = 'both'): SyncResult;
    public function pushContact(Contact $contact): ?string;
    public function pullContact(string $externalId): ?Contact;

    // Invoices
    public function syncInvoices(string $direction = 'both'): SyncResult;
    public function pushInvoice(Invoice $invoice): ?string;
    public function pullInvoice(string $externalId): ?Invoice;

    // Payments
    public function pushPayment(InvoicePayment $payment): ?string;
    public function pullPayments(string $invoiceExternalId): array;

    // Products
    public function syncProducts(string $direction = 'both'): SyncResult;

    // Tax Rates
    public function pullTaxRates(): array;

    // Accounts (Chart of Accounts)
    public function pullAccounts(): array;
}

interface SyncableInterface
{
    public function getExternalId(string $integration): ?string;
    public function setExternalId(string $integration, string $id): void;
    public function getLastSyncedAt(string $integration): ?Carbon;
    public function markSynced(string $integration): void;
}
```

### Integration Manager

```php
<?php

namespace App\Integrations\Core;

class IntegrationManager
{
    protected IntegrationRegistry $registry;
    protected array $instances = [];

    public function __construct(IntegrationRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function get(string $slug): IntegrationInterface
    {
        if (!isset($this->instances[$slug])) {
            $class = $this->registry->getIntegrationClass($slug);
            $tenantIntegration = TenantIntegration::where('integration_id',
                Integration::where('slug', $slug)->value('id')
            )->first();

            $this->instances[$slug] = new $class($tenantIntegration);
        }

        return $this->instances[$slug];
    }

    public function getConnected(): Collection
    {
        return TenantIntegration::with('integration')
            ->where('status', 'connected')
            ->get()
            ->map(fn($ti) => $this->get($ti->integration->slug));
    }

    public function sync(string $slug, string $entityType, string $direction = 'both'): SyncResult
    {
        $integration = $this->get($slug);

        return match($entityType) {
            'contacts' => $integration->syncContacts($direction),
            'invoices' => $integration->syncInvoices($direction),
            'products' => $integration->syncProducts($direction),
            default => throw new InvalidArgumentException("Unknown entity type: {$entityType}")
        };
    }
}
```

---

## Key Integrations Roadmap

### Tier 1: Essential (MVP)

| Integration | Category | Priority | Capabilities |
|-------------|----------|----------|--------------|
| **Xero** | Accounting | 🔴 High | Invoices, Contacts, Payments, Tax Rates |
| **QuickBooks Online** | Accounting | 🔴 High | Invoices, Contacts, Payments, Products |
| **Stripe** | Payments | 🔴 High | Payment processing, Subscriptions |
| **Gmail/Google Workspace** | Email | 🔴 High | Email sync, Calendar |
| **Microsoft 365** | Email | 🔴 High | Outlook sync, Calendar |

### Tier 2: Growth

| Integration | Category | Priority | Capabilities |
|-------------|----------|----------|--------------|
| **Slack** | Communication | 🟡 Medium | Notifications, Commands |
| **Mailchimp** | Marketing | 🟡 Medium | Contact sync, Campaigns |
| **HubSpot** | CRM | 🟡 Medium | Contact sync, Deals |
| **Calendly** | Scheduling | 🟡 Medium | Meeting booking |
| **Twilio** | Communication | 🟡 Medium | SMS, WhatsApp |
| **DocuSign** | Documents | 🟡 Medium | E-signatures |

### Tier 3: Enterprise

| Integration | Category | Priority | Capabilities |
|-------------|----------|----------|--------------|
| **Salesforce** | CRM | 🟢 Low | Full sync |
| **NetSuite** | ERP | 🟢 Low | Accounting, Inventory |
| **Sage** | Accounting | 🟢 Low | Invoices, Contacts |
| **Freshbooks** | Accounting | 🟢 Low | Invoices, Time tracking |
| **Zapier** | Automation | 🟢 Low | Webhook triggers |
| **Make (Integromat)** | Automation | 🟢 Low | Webhook triggers |

---

## Frontend Integration UI

### Components

```
frontend/src/lib/components/integrations/
├── IntegrationsMarketplace.svelte    # Browse available integrations
├── IntegrationCard.svelte            # Individual integration tile
├── IntegrationDetails.svelte         # Setup/config modal
├── ConnectedIntegrations.svelte      # Manage connected integrations
├── IntegrationSettings.svelte        # Per-integration settings
├── SyncStatus.svelte                 # Show sync status/logs
├── FieldMappingEditor.svelte         # Custom field mappings
└── OAuthCallback.svelte              # Handle OAuth redirects
```

### Marketplace UI

```
┌────────────────────────────────────────────────────────────────────────┐
│ 🔌 Integrations                                          [Search...] 🔍│
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│  Connected (2)                                                         │
│  ┌──────────────┐  ┌──────────────┐                                   │
│  │ 🔷 Xero      │  │ 📧 Gmail     │                                   │
│  │ ✅ Connected │  │ ✅ Connected │                                   │
│  │ Synced 2m ago│  │ Synced just  │                                   │
│  │ [Settings]   │  │ [Settings]   │                                   │
│  └──────────────┘  └──────────────┘                                   │
│                                                                        │
│  Accounting                                                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                │
│  │ 📗 QuickBooks│  │ 🟣 Sage      │  │ 🔵 FreshBooks│                │
│  │ [Connect]    │  │ [Connect]    │  │ [Connect]    │                │
│  └──────────────┘  └──────────────┘  └──────────────┘                │
│                                                                        │
│  Communication                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐                │
│  │ 💬 Slack     │  │ 📱 Twilio    │  │ 💚 WhatsApp  │                │
│  │ [Connect]    │  │ [Connect]    │  │ [Connect]    │                │
│  └──────────────┘  └──────────────┘  └──────────────┘                │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoints

```
# Integration Management
GET    /api/v1/integrations                    # List available integrations
GET    /api/v1/integrations/{slug}             # Get integration details
GET    /api/v1/integrations/connected          # List connected integrations

# Connection Management
POST   /api/v1/integrations/{slug}/connect     # Start OAuth or API key connection
DELETE /api/v1/integrations/{slug}/disconnect  # Disconnect integration
POST   /api/v1/integrations/{slug}/test        # Test connection
GET    /api/v1/integrations/{slug}/status      # Get connection status

# OAuth Flow
GET    /api/v1/integrations/{slug}/oauth/url   # Get OAuth authorization URL
GET    /api/v1/integrations/{slug}/oauth/callback # Handle OAuth callback

# Settings & Mappings
GET    /api/v1/integrations/{slug}/settings    # Get integration settings
PUT    /api/v1/integrations/{slug}/settings    # Update settings
GET    /api/v1/integrations/{slug}/mappings    # Get field mappings
PUT    /api/v1/integrations/{slug}/mappings    # Update field mappings

# Sync Operations
POST   /api/v1/integrations/{slug}/sync        # Trigger full sync
POST   /api/v1/integrations/{slug}/sync/{entity} # Sync specific entity type
GET    /api/v1/integrations/{slug}/sync/status # Get sync status
GET    /api/v1/integrations/{slug}/sync/logs   # Get sync logs

# Webhooks
POST   /api/v1/webhooks/{slug}/{tenant}        # Receive webhook from integration
```

---

## Security Considerations

1. **Credential Encryption** - All OAuth tokens and API keys encrypted at rest
2. **Tenant Isolation** - Strict separation of integration data per tenant
3. **Scope Limitation** - Request minimum necessary OAuth scopes
4. **Token Rotation** - Automatic refresh of expiring tokens
5. **Audit Logging** - Log all integration activities
6. **Rate Limiting** - Respect third-party API limits
7. **Webhook Verification** - Validate webhook signatures

---

## Implementation Timeline

### Phase 1: Core Framework (Week 1-2)
- Integration base classes and interfaces
- Database migrations
- OAuth2 handler
- Integration manager
- Basic API endpoints
- Frontend marketplace UI

### Phase 2: Xero Integration (Week 3-4)
- OAuth2 connection
- Contact sync (bi-directional)
- Invoice push (VRTX → Xero)
- Payment sync
- Tax rates pull
- Webhook handling

### Phase 3: Additional Integrations (Week 5+)
- QuickBooks Online
- Stripe
- Gmail/Outlook
- Slack

---

## Success Metrics

- Connection success rate > 95%
- Sync error rate < 2%
- Average sync time < 30 seconds
- Webhook processing < 5 seconds
- User satisfaction > 4.5/5
