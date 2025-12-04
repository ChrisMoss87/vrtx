# VRTX CRM - Architecture & Implementation Plan

## Overview
Multi-tenancy CRM system with domain-driven design (DDD) and clean architecture principles.

## Technology Stack

### Backend (Laravel)
- **Framework**: Laravel 12
- **Database**: PostgreSQL 17 with PostGIS
- **Cache/Queue**: Redis
- **Multi-tenancy**: stancl/tenancy
- **Architecture**: DDD + Clean Architecture

### Frontend (SvelteKit)
- **Framework**: SvelteKit with TypeScript
- **Styling**: Tailwind CSS v4
- **Components**: shadcn-svelte
- **Validation**: arktype
- **Forms**: sveltekit-superforms with formsnap
- **State**: Svelte 5 runes

### Infrastructure
- **Web Server**: Nginx (host machine)
- **Containers**: Docker Compose (PostgreSQL, Redis, Mailhog only)
- **Deployment**: Single server with domain/subdomain routing

## Domain-Driven Design Structure

### Core Domains

#### 1. Tenant Management (Core Domain)
**Purpose**: Manage multi-tenancy, subscriptions, and tenant lifecycle

**Entities**:
- `Tenant` - Main tenant aggregate root
- `TenantSubscription` - Subscription plans and billing
- `TenantSettings` - Tenant-specific configuration
- `Domain` - Custom domains and subdomains

**Value Objects**:
- `TenantId`
- `DomainName`
- `SubscriptionPlan`
- `BillingCycle`

**Repositories**:
- `TenantRepository`
- `DomainRepository`

**Domain Events**:
- `TenantCreated`
- `TenantSuspended`
- `TenantActivated`
- `DomainVerified`

---

#### 2. Identity & Access Management (Supporting Domain)
**Purpose**: User authentication, authorization, and role management

**Entities**:
- `User` - Aggregate root
- `Role`
- `Permission`
- `Team`

**Value Objects**:
- `UserId`
- `Email`
- `Password` (hashed)
- `RoleName`

**Repositories**:
- `UserRepository`
- `RoleRepository`

**Domain Events**:
- `UserRegistered`
- `UserLoggedIn`
- `RoleAssigned`
- `PermissionGranted`

---

#### 3. Contact Management (Core Domain)
**Purpose**: Manage contacts, leads, and relationships

**Entities**:
- `Contact` - Aggregate root
- `Company`
- `ContactNote`
- `ContactActivity`
- `Tag`

**Value Objects**:
- `ContactId`
- `PhoneNumber`
- `Address`
- `Email`
- `ContactType`

**Repositories**:
- `ContactRepository`
- `CompanyRepository`

**Domain Events**:
- `ContactCreated`
- `ContactUpdated`
- `ContactAssigned`
- `ActivityLogged`

---

#### 4. Sales Pipeline (Core Domain)
**Purpose**: Manage deals, opportunities, and sales process

**Entities**:
- `Deal` - Aggregate root
- `Pipeline`
- `Stage`
- `DealProduct`

**Value Objects**:
- `DealId`
- `Money`
- `Probability`
- `ExpectedCloseDate`

**Repositories**:
- `DealRepository`
- `PipelineRepository`

**Domain Events**:
- `DealCreated`
- `DealStageChanged`
- `DealWon`
- `DealLost`

---

#### 5. Communication (Supporting Domain)
**Purpose**: Email, SMS, and internal communication

**Entities**:
- `Email`
- `EmailThread`
- `Template`

**Value Objects**:
- `EmailAddress`
- `Subject`
- `MessageBody`

**Domain Events**:
- `EmailSent`
- `EmailReceived`
- `EmailOpened`

---

## Clean Architecture Layers

### 1. Domain Layer (`src/Domain`)
```
src/Domain/
├── TenantManagement/
│   ├── Entities/
│   │   ├── Tenant.php
│   │   └── Domain.php
│   ├── ValueObjects/
│   │   ├── TenantId.php
│   │   └── DomainName.php
│   ├── Repositories/
│   │   └── TenantRepositoryInterface.php
│   ├── Events/
│   │   └── TenantCreated.php
│   └── Exceptions/
│       └── TenantNotFoundException.php
├── ContactManagement/
├── SalesPipeline/
└── Shared/
    ├── ValueObjects/
    │   ├── Email.php
    │   └── Money.php
    └── Interfaces/
```

### 2. Application Layer (`src/Application`)
```
src/Application/
├── TenantManagement/
│   ├── UseCases/
│   │   ├── CreateTenant/
│   │   │   ├── CreateTenantCommand.php
│   │   │   ├── CreateTenantHandler.php
│   │   │   └── CreateTenantValidator.php
│   │   └── GetTenant/
│   ├── DTOs/
│   │   └── TenantDTO.php
│   └── Services/
│       └── TenantApplicationService.php
├── ContactManagement/
└── SalesPipeline/
```

### 3. Infrastructure Layer (`src/Infrastructure`)
```
src/Infrastructure/
├── Persistence/
│   ├── Eloquent/
│   │   ├── Models/
│   │   └── Repositories/
│   │       └── EloquentTenantRepository.php
│   └── Migrations/
├── External/
│   ├── Email/
│   └── SMS/
└── Events/
    └── EventServiceProvider.php
```

### 4. Presentation Layer (`src/Presentation`)
```
src/Presentation/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── TenantController.php
│   │       ├── ContactController.php
│   │       └── DealController.php
│   ├── Requests/
│   │   └── CreateTenantRequest.php
│   ├── Resources/
│   │   └── TenantResource.php
│   └── Middleware/
│       └── TenantMiddleware.php
└── Console/
    └── Commands/
```

---

## Frontend Architecture (SvelteKit)

### Directory Structure
```
frontend/src/
├── lib/
│   ├── components/
│   │   ├── ui/               # shadcn-svelte components
│   │   ├── domain/           # Domain-specific components
│   │   │   ├── contacts/
│   │   │   ├── deals/
│   │   │   └── tenants/
│   │   └── shared/           # Shared components
│   ├── api/
│   │   ├── client.ts         # API client
│   │   └── endpoints/
│   │       ├── contacts.ts
│   │       ├── deals.ts
│   │       └── tenants.ts
│   ├── stores/
│   │   ├── tenant.svelte.ts  # Tenant state
│   │   ├── auth.svelte.ts    # Auth state
│   │   └── ui.svelte.ts      # UI state
│   ├── types/
│   │   └── schemas.ts        # arktype schemas
│   ├── utils/
│   └── hooks/
├── routes/
│   ├── (auth)/
│   │   ├── login/
│   │   └── register/
│   ├── (app)/
│   │   ├── dashboard/
│   │   ├── contacts/
│   │   ├── deals/
│   │   └── settings/
│   └── (public)/
└── app.css
```

---

## Implementation Roadmap

### Phase 1: Foundation (Week 1-2)
- [x] Project structure setup
- [x] Docker Compose configuration
- [x] Laravel multi-tenancy installation
- [x] SvelteKit with TypeScript and Tailwind
- [x] Nginx configuration for multi-tenancy
- [ ] Database schema design
- [ ] Authentication system
- [ ] Base DDD structure

### Phase 2: Tenant Management (Week 3-4)
- [ ] Tenant domain implementation
- [ ] Tenant registration flow
- [ ] Subdomain provisioning
- [ ] Tenant dashboard
- [ ] Subscription management (basic)

### Phase 3: Contact Management (Week 5-6)
- [ ] Contact domain implementation
- [ ] Contact CRUD operations
- [ ] Company management
- [ ] Contact import/export
- [ ] Activity tracking
- [ ] Tags and custom fields

### Phase 4: Sales Pipeline (Week 7-8)
- [ ] Deal domain implementation
- [ ] Pipeline configuration
- [ ] Deal stages and workflow
- [ ] Kanban board view
- [ ] Deal analytics

### Phase 5: Communication (Week 9-10)
- [ ] Email integration
- [ ] Email tracking
- [ ] Templates
- [ ] Email sequences (basic)

### Phase 6: Polish & Native Prep (Week 11-12)
- [ ] API optimization for mobile
- [ ] Real-time features (WebSockets)
- [ ] Performance optimization
- [ ] API documentation
- [ ] Mobile app preparation

---

## Database Design

### Central Database (Tenant Management)
```sql
tenants
- id (uuid, PK)
- name (string)
- subdomain (string, unique)
- status (enum: active, suspended, trial)
- created_at
- updated_at

domains
- id (uuid, PK)
- tenant_id (uuid, FK)
- domain (string, unique)
- verified (boolean)
- is_primary (boolean)

tenant_subscriptions
- id (uuid, PK)
- tenant_id (uuid, FK)
- plan (enum)
- status (enum)
- trial_ends_at
- expires_at
```

### Tenant Database (Per Tenant)
```sql
users
- id (uuid, PK)
- email (string, unique)
- name (string)
- role_id (uuid, FK)
- created_at

contacts
- id (uuid, PK)
- first_name
- last_name
- email
- phone
- company_id (uuid, FK, nullable)
- assigned_to (uuid, FK)
- created_at

companies
- id (uuid, PK)
- name
- industry
- size
- created_at

deals
- id (uuid, PK)
- title
- value (decimal)
- currency
- probability (int)
- stage_id (uuid, FK)
- contact_id (uuid, FK)
- expected_close_date
- created_at

pipelines
- id (uuid, PK)
- name
- is_default (boolean)

stages
- id (uuid, PK)
- pipeline_id (uuid, FK)
- name
- probability (int)
- order (int)
```

---

## API Design Principles

### RESTful Conventions
```
GET    /api/v1/contacts           # List contacts
POST   /api/v1/contacts           # Create contact
GET    /api/v1/contacts/{id}      # Get contact
PUT    /api/v1/contacts/{id}      # Update contact
DELETE /api/v1/contacts/{id}      # Delete contact

# Nested resources
GET    /api/v1/contacts/{id}/activities
POST   /api/v1/contacts/{id}/notes

# Deals
GET    /api/v1/deals
POST   /api/v1/deals
GET    /api/v1/pipelines/{id}/deals
```

### Response Format
```json
{
  "data": {
    "id": "uuid",
    "type": "contact",
    "attributes": {},
    "relationships": {}
  },
  "meta": {
    "timestamp": "2025-11-20T12:00:00Z"
  }
}
```

---

## Multi-Tenancy Strategy

### Tenant Identification
1. **Subdomain-based**: `{tenant}.vrtx.local`
2. **Custom domain**: `crm.company.com`
3. **Nginx passes `X-Tenant` header** to backend

### Database Strategy
- **Database per tenant** (highest isolation)
- Central database for tenant management
- Connection switching via stancl/tenancy

### Tenant Context
```php
// Automatic tenant context via middleware
Route::middleware(['tenant'])->group(function () {
    Route::get('/contacts', [ContactController::class, 'index']);
});
```

---

## Security Considerations

1. **Tenant Isolation**: Strict database separation
2. **CORS**: Proper configuration for API
3. **CSRF Protection**: Laravel Sanctum
4. **SQL Injection**: Eloquent ORM + prepared statements
5. **XSS Protection**: Input sanitization
6. **Rate Limiting**: API throttling
7. **Authentication**: JWT tokens via Laravel Sanctum
8. **Authorization**: Role-based access control (RBAC)

---

## Testing Strategy

### Backend
- **Unit Tests**: Domain logic, value objects
- **Integration Tests**: Application services, repositories
- **Feature Tests**: API endpoints
- **Tenant Tests**: Multi-tenancy isolation

### Frontend
- **Unit Tests**: Utilities, helpers (Vitest)
- **Component Tests**: UI components (Vitest + Playwright)
- **E2E Tests**: User flows (Playwright)

---

## Next Steps

1. **Set up base Laravel DDD structure** in `backend/src/`
2. **Configure tenant database migrations**
3. **Implement authentication with Laravel Sanctum**
4. **Create first domain: Tenant Management**
5. **Build tenant registration flow**
6. **Create SvelteKit auth pages**
7. **Implement API client with tenant context**

---

## Development Commands

### Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate        # Central database
php artisan tenants:migrate # Tenant databases
php artisan serve --port=8000
```

### Frontend
```bash
cd frontend
pnpm install
cp .env.example .env
pnpm dev
```

### Infrastructure
```bash
docker-compose up -d       # Start PostgreSQL, Redis, Mailhog
docker-compose down        # Stop services
```

### Nginx
```bash
sudo cp nginx/conf.d/vrtx.conf /etc/nginx/sites-available/vrtx
sudo ln -s /etc/nginx/sites-available/vrtx /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```
