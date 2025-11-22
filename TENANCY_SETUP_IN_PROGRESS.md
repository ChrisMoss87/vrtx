# Multi-Tenancy Configuration - In Progress

## What's Been Configured

### 1. Database Configuration ✓
- **Central database connection**: `central` - stores tenant metadata
- **Tenant database template**: `pgsql` - template for tenant databases
- **Database naming**: `tenant_{tenant_id}` (e.g., `tenant_acme`)

### 2. Tenancy Config ✓
- Central connection: `central`
- Template connection: `pgsql`
- Bootstrappers enabled (Database, Cache, Filesystem, Queue)

### 3. Tenant Seeder Created ✓
Creates 3 example tenants:
- **Acme Corporation** (acme.vrtx.local) - Professional plan
- **TechCo Inc** (techco.vrtx.local) - Enterprise plan
- **Startup Inc** (startup.vrtx.local + crm.startup.com) - Starter plan with custom domain

---

## Next Steps to Complete Setup

### Step 1: Run Tenant Seeder
```bash
cd backend
php artisan db:seed --class=TenantSeeder
```

### Step 2: Add Domains to /etc/hosts
```bash
sudo tee -a /etc/hosts << 'EOF'
127.0.0.1 acme.vrtx.local
127.0.0.1 techco.vrtx.local
127.0.0.1 startup.vrtx.local
127.0.0.1 crm.startup.com
EOF
```

### Step 3: Run Tenant Migrations
```bash
cd backend
php artisan tenants:migrate
```

This will create a separate database for each tenant and run migrations.

### Step 4: Update API Routes for Tenancy

The `/api/v1/auth/*` routes need to be tenant-aware. Options:

**Option A: Tenant-specific auth (recommended)**
Move auth routes to `routes/tenant.php` so each tenant has separate users.

**Option B: Central auth with tenant access**
Keep auth in central, add tenant switching after login.

### Step 5: Test Tenant Isolation

Visit each tenant domain and register:
- http://acme.vrtx.local:5173/register
- http://techco.vrtx.local:5173/register
- http://startup.vrtx.local:5173/register

Each should create users in separate databases.

---

## Current Tenant URLs

### Subdomain-based:
- `acme.vrtx.local` → Acme Corporation (Professional)
- `techco.vrtx.local` → TechCo Inc (Enterprise)
- `startup.vrtx.local` → Startup Inc (Starter)

### Custom domain:
- `crm.startup.com` → Startup Inc (primary domain)

---

## Tenant Credentials

Currently, each tenant will have its OWN set of users:

### Central Admin (Platform Management)
- URL: http://localhost:5173 or https://vrtx.local
- Users: Platform administrators only
- Purpose: Manage tenants, view analytics

### Acme Corporation
- URL: http://acme.vrtx.local:5173
- Database: `tenant_acme`
- Users: Acme's employees/users only
- Completely isolated from other tenants

### TechCo Inc
- URL: http://techco.vrtx.local:5173
- Database: `tenant_techco`
- Users: TechCo's employees/users only

### Startup Inc
- URL: http://startup.vrtx.local:5173 or http://crm.startup.com:5173
- Database: `tenant_startup`
- Users: Startup's employees/users only

---

## Authentication Strategy

### Recommended: Per-Tenant Authentication

Each tenant has completely separate users:

```
acme.vrtx.local
  ├── Database: tenant_acme
  └── Users table:
      ├── john@acme.com
      ├── jane@acme.com
      └── admin@acme.com

techco.vrtx.local
  ├── Database: tenant_techco
  └── Users table:
      ├── bob@techco.com
      └── alice@techco.com
```

**Benefits:**
- Complete data isolation
- Each tenant manages their own users
- No risk of cross-tenant access
- Simpler permission model

---

## Files Modified

### Backend:
1. `config/database.php` - Added `central` and `tenant` connections
2. `config/tenancy.php` - Configured central connection
3. `.env` - Changed DB_CONNECTION to `central`
4. `database/seeders/TenantSeeder.php` - Created tenant seeder

### Routes (Next Step):
- Need to decide: tenant-specific auth or central auth

---

## How Tenancy Works

### Request Flow:
```
1. User visits: acme.vrtx.local
2. Nginx extracts subdomain: "acme"
3. Laravel InitializeTenancyByDomain middleware activates
4. Database connection switches to: tenant_acme
5. All User::all() queries now return acme's users only
6. Response sent back
7. Next request resets to central database
```

### Database Switching:
```php
// Before tenancy
DB::connection()->getDatabaseName(); // "vrtx_crm"

// Tenant identified
tenancy()->initialize('acme');

// After tenancy
DB::connection()->getDatabaseName(); // "tenant_acme"
```

---

## Testing Tenant Isolation

### Create User in Acme Tenant:
```bash
cd backend
php artisan tinker

tenancy()->initialize('acme');
\App\Models\User::create([
    'name' => 'John Acme',
    'email' => 'john@acme.com',
    'password' => bcrypt('password')
]);
tenancy()->end();
```

### Create User in TechCo Tenant:
```bash
php artisan tinker

tenancy()->initialize('techco');
\App\Models\User::create([
    'name' => 'Bob TechCo',
    'email' => 'bob@techco.com',
    'password' => bcrypt('password')
]);
tenancy()->end();
```

### Verify Isolation:
```bash
# Check Acme's users
tenancy()->initialize('acme');
\App\Models\User::all(); // Only John
tenancy()->end();

# Check TechCo's users
tenancy()->initialize('techco');
\App\Models\User::all(); // Only Bob
tenancy()->end();
```

---

## Scripts Available

### Create Tenant:
```bash
./scripts/create-tenant.sh <id> [name]
./scripts/create-tenant.sh demo "Demo Company"
```

### List Tenants:
```bash
./scripts/list-tenants.sh
```

---

## What Still Needs To Be Done

1. **Run the seeder** to create example tenants
2. **Run tenant migrations** to create tenant databases
3. **Update auth routes** to be tenant-aware
4. **Test registration** on each tenant domain
5. **Verify isolation** between tenants
6. **Update frontend** to detect tenant from URL
7. **Add tenant branding** (logo, colors) support

---

## Important Notes

- **All future development** must be tenant-aware
- **Every database query** will automatically scope to current tenant
- **Migrations** need to go in both central and tenant folders
- **Seeds** need to specify which database
- **Tests** need to test tenant isolation

---

## Quick Commands

```bash
# Seed tenants
cd backend && php artisan db:seed --class=TenantSeeder

# Run tenant migrations
php artisan tenants:migrate

# List tenants
php artisan tinker
\Stancl\Tenancy\Database\Models\Tenant::with('domains')->get();

# Check current tenant
php artisan tinker
tenant(); // Returns current tenant or null
```

---

**Status**: Configuration complete, ready to seed and test tenants.

**Next**: Run seeder, add to /etc/hosts, run tenant migrations, test isolation.
