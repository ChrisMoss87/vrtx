# Multi-Tenancy Guide - VRTX CRM

## Overview

VRTX CRM uses **database-per-tenant** multi-tenancy:
- Each tenant has their own PostgreSQL database
- Tenants are identified by subdomain (e.g., `acme.vrtx.local`)
- Central database stores tenant metadata
- Complete data isolation between tenants

---

## Current Setup

### Databases

**Central Database** (`vrtx_crm`):
- Stores tenant metadata
- Manages domains and tenant configuration
- Tables: `tenants`, `domains`

**Tenant Databases** (e.g., `tenant_acme`):
- One database per tenant
- Contains tenant-specific data
- Tables: `users`, `contacts`, `deals`, etc.

### URLs

**Central Domain**: `vrtx.local` or `localhost`
- Used for landing page, marketing site
- Tenant registration

**Tenant Subdomains**: `{tenant}.vrtx.local`
- Each tenant gets their own subdomain
- Examples:
  - `acme.vrtx.local` - Acme Corp tenant
  - `techco.vrtx.local` - TechCo tenant
  - `startup.vrtx.local` - Startup Inc tenant

**API Domain**: `api.vrtx.local` or `localhost:8000`
- Central API endpoints
- Tenant API endpoints (when tenant context provided)

---

## Creating Your First Tenant

### Method 1: Using Artisan Command

```bash
cd backend

# Create tenant with ID
php artisan tinker

# Create tenant
$tenant = \App\Models\Tenant::create(['id' => 'acme']);

# Assign domain
$tenant->domains()->create(['domain' => 'acme.vrtx.local']);

# Exit tinker
exit
```

### Method 2: Using Helper Script

I'll create a helper script for you:

```bash
# Create tenant script
./scripts/create-tenant.sh acme "Acme Corporation"
```

### Method 3: Via API (Future)

Once tenant registration is implemented:

```bash
POST /api/v1/tenants
{
  "subdomain": "acme",
  "company_name": "Acme Corporation",
  "admin_email": "admin@acme.com",
  "admin_password": "secure_password"
}
```

---

## Tenant Structure

### Tenant Model

```php
id: 'acme'           // Unique tenant ID
data: [
  'name' => 'Acme Corporation',
  'plan' => 'pro',
  'status' => 'active'
]
created_at: timestamp
updated_at: timestamp
```

### Domain Model

```php
id: uuid
tenant_id: 'acme'
domain: 'acme.vrtx.local'  // or custom domain
is_primary: true
verified: true
```

---

## How Tenancy Works

### 1. Request Flow

```
User visits: acme.vrtx.local
    ↓
Nginx routes to backend/frontend
    ↓
Tenancy middleware identifies tenant from subdomain
    ↓
Database connection switches to tenant_acme
    ↓
All queries automatically scoped to tenant database
    ↓
Response sent back to user
```

### 2. Database Switching

**Before tenancy initialization:**
```php
DB_DATABASE=vrtx_crm  // Central database
```

**After tenancy initialization:**
```php
DB_DATABASE=tenant_acme  // Tenant's database
```

### 3. Tenant Isolation

- Each request is completely isolated
- No cross-tenant data access possible
- Separate database = complete security

---

## Managing Tenants

### Create Tenant

```bash
cd backend
php artisan tinker

$tenant = \App\Models\Tenant::create([
    'id' => 'acme',
    'data' => [
        'name' => 'Acme Corporation',
        'plan' => 'pro',
        'status' => 'active'
    ]
]);

$tenant->domains()->create([
    'domain' => 'acme.vrtx.local'
]);
```

### List All Tenants

```bash
php artisan tinker

\App\Models\Tenant::with('domains')->get();
```

### Run Migrations for Tenant

```bash
# For all tenants
php artisan tenants:migrate

# For specific tenant
php artisan tenants:run acme -- migrate
```

### Delete Tenant

```bash
php artisan tinker

$tenant = \App\Models\Tenant::find('acme');
$tenant->delete();  // Also deletes database if configured
```

---

## Local Development Setup

### 1. Update /etc/hosts

```bash
sudo nano /etc/hosts

# Add these lines:
127.0.0.1 vrtx.local
127.0.0.1 api.vrtx.local
127.0.0.1 app.vrtx.local
127.0.0.1 acme.vrtx.local
127.0.0.1 techco.vrtx.local
# Add more tenant domains as needed
```

### 2. Configure Nginx

The nginx config in `nginx/conf.d/vrtx.conf` already handles:
- Main domain routing
- API routing
- Tenant subdomain routing
- SSL/HTTPS

```bash
# Copy nginx config
sudo cp nginx/conf.d/vrtx.conf /etc/nginx/sites-available/vrtx
sudo ln -s /etc/nginx/sites-available/vrtx /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 3. Generate SSL Certificates

```bash
mkcert vrtx.local "*.vrtx.local"
sudo cp vrtx.local+1.pem /etc/ssl/certs/vrtx.local.pem
sudo cp vrtx.local+1-key.pem /etc/ssl/private/vrtx.local-key.pem
```

---

## User Management Per Tenant

### Create User in Tenant Database

```bash
cd backend
php artisan tinker

# Switch to tenant context
tenancy()->initialize('acme');

# Create user
$user = \App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'john@acme.com',
    'password' => bcrypt('password123')
]);

# End tenant context
tenancy()->end();
```

### Current Users

Right now, users you created via the registration flow are in the **central database**. To move to tenant-based auth:

1. User visits `acme.vrtx.local`
2. Registers/logs in
3. User record stored in `tenant_acme` database
4. Completely separate from other tenants

---

## Example: Creating Multiple Tenants

```bash
cd backend
php artisan tinker

# Tenant 1: Acme Corporation
$acme = \App\Models\Tenant::create(['id' => 'acme']);
$acme->domains()->create(['domain' => 'acme.vrtx.local']);

# Tenant 2: TechCo
$techco = \App\Models\Tenant::create(['id' => 'techco']);
$techco->domains()->create(['domain' => 'techco.vrtx.local']);

# Tenant 3: Startup Inc
$startup = \App\Models\Tenant::create(['id' => 'startup']);
$startup->domains()->create(['domain' => 'startup.vrtx.local']);
```

Then add to `/etc/hosts`:
```
127.0.0.1 acme.vrtx.local
127.0.0.1 techco.vrtx.local
127.0.0.1 startup.vrtx.local
```

---

## Testing Tenant Isolation

### 1. Create Test Tenant

```bash
php artisan tinker

$tenant = \App\Models\Tenant::create(['id' => 'test']);
$tenant->domains()->create(['domain' => 'test.vrtx.local']);
```

### 2. Add to /etc/hosts

```bash
echo "127.0.0.1 test.vrtx.local" | sudo tee -a /etc/hosts
```

### 3. Register User

Visit: http://test.vrtx.local:5173/register
- Register a user
- This user will be in the central DB (for now)

### 4. Check Database

```bash
# Check central database
docker exec -it vrtx_postgres psql -U vrtx_user -d vrtx_crm

SELECT * FROM tenants;
SELECT * FROM domains;

# Check if tenant database exists
\l tenant_*
```

---

## Tenant Credentials

### System Admin (Central Database)

These manage the platform itself:

```
Email: admin@vrtx.local
Password: (set during setup)
Access: Central admin panel
```

### Tenant Admin (Per Tenant)

Each tenant has their own admins:

```
Tenant: acme.vrtx.local
Email: admin@acme.com
Password: (set by tenant during registration)
Access: acme.vrtx.local dashboard
```

**Important**: Users in one tenant **cannot** access another tenant's data.

---

## Custom Domains (Future)

Instead of subdomains, tenants can use custom domains:

```
acme.vrtx.local  →  crm.acme.com
techco.vrtx.local  →  app.techco.io
```

Setup:
```bash
php artisan tinker

$tenant = \App\Models\Tenant::find('acme');
$tenant->domains()->create([
    'domain' => 'crm.acme.com',
    'is_primary' => true
]);
```

---

## Troubleshooting

### "Tenant not found"

1. Check tenant exists: `\App\Models\Tenant::find('acme')`
2. Check domain exists: `\App\Models\Domain::where('domain', 'acme.vrtx.local')->first()`
3. Check /etc/hosts has the domain

### "Database tenant_acme does not exist"

```bash
# Manually create tenant database
docker exec -it vrtx_postgres psql -U vrtx_user -d postgres

CREATE DATABASE tenant_acme;
\q

# Run migrations
php artisan tenants:migrate
```

### "Cross-tenant data leak"

If you see data from another tenant:
1. Check tenancy middleware is active
2. Verify tenant identification is working
3. Check database connection is switching

---

## Security Best Practices

1. **Never share tenant IDs** - They're internal identifiers
2. **Validate domains** - Ensure user can only access their domain
3. **Separate databases** - Already implemented
4. **Audit logs** - Track who accesses what
5. **Backup per tenant** - Each tenant database separately

---

## Next Steps

1. **Enable tenant middleware** on auth routes
2. **Implement tenant registration** flow
3. **Add tenant dashboard** showing usage/settings
4. **Test tenant isolation** thoroughly
5. **Add tenant switching** for super admins

---

## Quick Reference

### Create Tenant
```bash
php artisan tinker
$t = \App\Models\Tenant::create(['id' => 'acme']);
$t->domains()->create(['domain' => 'acme.vrtx.local']);
```

### List Tenants
```bash
php artisan tinker
\App\Models\Tenant::all();
```

### Run Migrations
```bash
php artisan tenants:migrate
```

### Access Tenant Site
1. Add to /etc/hosts: `127.0.0.1 acme.vrtx.local`
2. Visit: http://acme.vrtx.local:5173

---

**Current Status**: Tenancy system installed, ready for activation on auth routes.
