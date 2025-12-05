# Phase 13: API & Integrations - Complete

## Overview
Phase 13 implements a comprehensive API and integrations system, enabling external applications to interact with VRTX CRM through API keys and webhooks.

## Features Implemented

### 1. API Keys Management
- **Full CRUD operations** for API key management
- **Scoped permissions** - Fine-grained access control with scopes:
  - `read` - Read access to records
  - `write` - Create/update records
  - `delete` - Delete records
  - `modules:read` - Read module definitions
  - `modules:write` - Modify module definitions
  - `webhooks:manage` - Manage webhooks
- **IP whitelisting** - Restrict API key usage to specific IPs
- **Rate limiting** - Configurable requests per hour
- **Expiration dates** - Optional key expiration
- **Usage tracking** - Request counts and last used timestamps
- **Request logging** - Full audit trail of API requests

### 2. Outgoing Webhooks
- **Event-based triggers** - Fire webhooks on record events:
  - `record.created` - When a record is created
  - `record.updated` - When a record is updated
  - `record.deleted` - When a record is deleted
  - `module.created` - When a module is created
  - `module.updated` - When a module is updated
  - `module.deleted` - When a module is deleted
- **HMAC signature verification** - Secure webhook payloads
- **SSL verification** - Configurable SSL validation
- **Retry mechanism** - Exponential backoff with configurable retry count
- **Delivery tracking** - Track delivery status, response codes, timing
- **Test functionality** - Send test webhooks to verify endpoints

### 3. Incoming Webhooks
- **Token-based authentication** - Secure incoming webhook URLs
- **Field mapping** - Map incoming payload fields to module fields
- **Multiple actions**:
  - `create` - Create new records
  - `update` - Update existing records
  - `upsert` - Create or update based on upsert field
- **Request logging** - Track all incoming webhook calls
- **Error handling** - Detailed error messages for debugging

### 4. API Authentication Middleware
- Supports multiple authentication methods:
  - Bearer token (Authorization header)
  - X-API-Key header
  - Query parameter (api_key)
- Scope-based authorization
- IP whitelist checking
- Rate limit enforcement
- Full request logging

## Files Created

### Backend - Migrations
- `database/migrations/tenant/2025_12_05_130000_create_api_keys_table.php`
  - `api_keys` table
  - `api_request_logs` table
- `database/migrations/tenant/2025_12_05_130001_create_webhooks_table.php`
  - `webhooks` table
  - `webhook_deliveries` table
  - `incoming_webhooks` table
  - `incoming_webhook_logs` table

### Backend - Models
- `app/Models/ApiKey.php` - API key with scopes, verification, rate limiting
- `app/Models/ApiRequestLog.php` - API request audit logging
- `app/Models/Webhook.php` - Outgoing webhook with HMAC signing
- `app/Models/WebhookDelivery.php` - Delivery tracking with retry logic
- `app/Models/IncomingWebhook.php` - Incoming webhook with field mapping
- `app/Models/IncomingWebhookLog.php` - Incoming webhook logging

### Backend - Controllers
- `app/Http/Controllers/Api/Integration/ApiKeyController.php`
  - `index` - List API keys
  - `store` - Create API key
  - `show` - Get API key with usage stats
  - `update` - Update API key
  - `revoke` - Revoke API key
  - `regenerate` - Generate new secret
  - `destroy` - Delete API key
  - `logs` - View request logs

- `app/Http/Controllers/Api/Integration/WebhookController.php`
  - `index` - List webhooks
  - `store` - Create webhook
  - `show` - Get webhook with delivery stats
  - `update` - Update webhook
  - `destroy` - Delete webhook
  - `rotateSecret` - Generate new secret
  - `test` - Send test webhook
  - `deliveries` - List deliveries
  - `getDelivery` - Get delivery details
  - `retryDelivery` - Retry failed delivery

- `app/Http/Controllers/Api/Integration/IncomingWebhookController.php`
  - `index` - List incoming webhooks
  - `store` - Create incoming webhook
  - `show` - Get webhook with logs
  - `update` - Update webhook
  - `destroy` - Delete webhook
  - `regenerateToken` - Generate new token
  - `logs` - View webhook logs
  - `receive` - Public endpoint to receive data

### Backend - Services & Jobs
- `app/Jobs/SendWebhookJob.php` - Async webhook delivery
- `app/Services/WebhookService.php` - Webhook triggering service

### Backend - Middleware
- `app/Http/Middleware/AuthenticateApiKey.php` - API key authentication

### Frontend
- `src/lib/api/integrations.ts` - API client for integrations
- `src/routes/(app)/settings/integrations/+page.svelte` - Settings UI

## API Endpoints

### API Keys
```
GET    /api/v1/api-keys                    - List API keys
POST   /api/v1/api-keys                    - Create API key
GET    /api/v1/api-keys/{id}               - Get API key details
PUT    /api/v1/api-keys/{id}               - Update API key
DELETE /api/v1/api-keys/{id}               - Delete API key
POST   /api/v1/api-keys/{id}/revoke        - Revoke API key
POST   /api/v1/api-keys/{id}/regenerate    - Regenerate secret
GET    /api/v1/api-keys/{id}/logs          - View request logs
```

### Outgoing Webhooks
```
GET    /api/v1/webhooks                              - List webhooks
POST   /api/v1/webhooks                              - Create webhook
GET    /api/v1/webhooks/{id}                         - Get webhook details
PUT    /api/v1/webhooks/{id}                         - Update webhook
DELETE /api/v1/webhooks/{id}                         - Delete webhook
POST   /api/v1/webhooks/{id}/rotate-secret           - Rotate secret
POST   /api/v1/webhooks/{id}/test                    - Send test
GET    /api/v1/webhooks/{id}/deliveries              - List deliveries
GET    /api/v1/webhooks/{id}/deliveries/{deliveryId} - Get delivery
POST   /api/v1/webhooks/{id}/deliveries/{deliveryId}/retry - Retry delivery
```

### Incoming Webhooks
```
GET    /api/v1/incoming-webhooks                     - List incoming webhooks
POST   /api/v1/incoming-webhooks                     - Create incoming webhook
GET    /api/v1/incoming-webhooks/{id}                - Get webhook details
PUT    /api/v1/incoming-webhooks/{id}                - Update webhook
DELETE /api/v1/incoming-webhooks/{id}                - Delete webhook
POST   /api/v1/incoming-webhooks/{id}/regenerate-token - Regenerate token
GET    /api/v1/incoming-webhooks/{id}/logs           - View logs
POST   /api/v1/webhooks/incoming/{token}             - Receive data (public)
```

## Usage Examples

### Creating an API Key
```javascript
const response = await fetch('/api/v1/api-keys', {
  method: 'POST',
  headers: { 'Authorization': 'Bearer your-token' },
  body: JSON.stringify({
    name: 'My Integration',
    scopes: ['read', 'write'],
    rate_limit: 1000
  })
});
const { secret, api_key } = await response.json();
// Store secret securely - only shown once!
```

### Using an API Key
```javascript
// Via Authorization header (recommended)
fetch('/api/v1/records/contacts', {
  headers: { 'Authorization': 'Bearer your-api-key' }
});

// Via X-API-Key header
fetch('/api/v1/records/contacts', {
  headers: { 'X-API-Key': 'your-api-key' }
});
```

### Creating a Webhook
```javascript
const response = await fetch('/api/v1/webhooks', {
  method: 'POST',
  body: JSON.stringify({
    name: 'Slack Notification',
    url: 'https://hooks.slack.com/...',
    events: ['record.created', 'record.updated'],
    verify_ssl: true
  })
});
const { secret } = await response.json();
// Use secret to verify webhook signatures
```

### Verifying Webhook Signatures
```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$expected = hash_hmac('sha256', $payload, $webhookSecret);

if (hash_equals($expected, $signature)) {
    // Signature valid
}
```

### Creating an Incoming Webhook
```javascript
const response = await fetch('/api/v1/incoming-webhooks', {
  method: 'POST',
  body: JSON.stringify({
    name: 'Form Submissions',
    module_id: 1,
    action: 'create',
    field_mapping: {
      'email': 'email',
      'name': 'full_name',
      'company': 'company_name'
    }
  })
});
const { url, token } = await response.json();
// Use url to send data from external systems
```

## Security Considerations

1. **API Keys**
   - Keys are hashed before storage (SHA-256)
   - Original key only shown once on creation
   - IP whitelisting for additional security
   - Rate limiting prevents abuse

2. **Webhooks**
   - HMAC-SHA256 signatures for payload verification
   - Configurable SSL verification
   - Secrets rotatable without deleting webhook

3. **Incoming Webhooks**
   - Token-based authentication
   - Tokens regeneratable
   - Request logging for audit

## Testing
Navigate to `/settings/integrations` to:
- Create and manage API keys
- Configure outgoing webhooks
- Set up incoming webhooks
- View delivery logs and statistics

## Next Steps (Phase 14)
Consider implementing:
- OAuth2 support for API authentication
- GraphQL API endpoint
- Webhook filtering/conditions
- Bulk webhook operations
- API key rotation schedule
