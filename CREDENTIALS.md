# VRTX Multi-Tenant CRM - Credentials & URLs

## System Information

- **Project**: VRTX Multi-Tenant CRM
- **Backend**: Laravel 12 with Sanctum Authentication
- **Frontend**: SvelteKit with Vite
- **Database**: PostgreSQL (Multi-tenant with separate databases)
- **Tenancy Package**: stancl/tenancy v4

---

## Development Servers

### Backend (Laravel)
- **URL**: http://localhost:8000
- **Command**: `./dev.sh` (from backend directory)
- **API Base**: `/api/v1`

### Frontend (SvelteKit/Vite)
- **URL**: http://localhost:5173
- **Command**: `pnpm dev --host 0.0.0.0` (from frontend directory)

### Nginx Proxy
- **Port**: 80
- **Config**: `/etc/nginx/sites-available/vrtx-tenants.conf`

---

## Database Configuration

### Central Database (PostgreSQL)
- **Host**: localhost (via Docker)
- **Port**: 5432
- **Database**: `vrtx_crm`
- **Username**: `vrtx_user`
- **Password**: `vrtx_password`
- **Docker Container**: `vrtx_postgres`

### Database Connection String
```bash
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm
```

---

## Tenant Configuration

### Tenant 1: Acme Corporation

#### URLs
- **Primary Domain**: http://acme.vrtx.local
- **Login**: http://acme.vrtx.local/login
- **Register**: http://acme.vrtx.local/register
- **API Base**: http://acme.vrtx.local/api/v1

#### Database
- **Database Name**: `tenantacme`
- **Tenant ID**: `acme`

#### Test Users
| Name | Email | Password | Role |
|------|-------|----------|------|
| John Acme | john@acme.com | password123 | Admin |
| Test Acme User | testuser@acme.com | password123 | User |

#### Tenant Data
```json
{
  "name": "Acme Corporation",
  "plan": "professional",
  "status": "active",
  "users_limit": 50,
  "storage_limit_mb": 5000
}
```

---

### Tenant 2: TechCo Solutions

#### URLs
- **Primary Domain**: http://techco.vrtx.local
- **Login**: http://techco.vrtx.local/login
- **Register**: http://techco.vrtx.local/register
- **API Base**: http://techco.vrtx.local/api/v1

#### Database
- **Database Name**: `tenanttechco`
- **Tenant ID**: `techco`

#### Test Users
| Name | Email | Password | Role |
|------|-------|----------|------|
| Bob TechCo | bob@techco.com | password123 | Admin |
| Test TechCo User | testuser@techco.com | password123 | User |

#### Tenant Data
```json
{
  "name": "TechCo Solutions",
  "plan": "enterprise",
  "status": "active",
  "users_limit": 100,
  "storage_limit_mb": 10000
}
```

---

### Tenant 3: Startup Inc

#### URLs
- **Primary Domain**: http://startup.vrtx.local
- **Alternative Domain**: http://crm.startup.com
- **Login**: http://startup.vrtx.local/login
- **Register**: http://startup.vrtx.local/register
- **API Base**: http://startup.vrtx.local/api/v1

#### Database
- **Database Name**: `tenantstartup`
- **Tenant ID**: `startup`

#### Test Users
| Name | Email | Password | Role |
|------|-------|----------|------|
| Alice Startup | alice@startup.com | password123 | Admin |

#### Tenant Data
```json
{
  "name": "Startup Inc",
  "plan": "starter",
  "status": "active",
  "users_limit": 10,
  "storage_limit_mb": 1000
}
```

---

## API Endpoints

### Authentication Endpoints (Tenant-Scoped)

#### Register
```bash
POST http://{tenant}.vrtx.local/api/v1/auth/register
Content-Type: application/json

{
  "name": "User Name",
  "email": "user@{tenant}.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### Login
```bash
POST http://{tenant}.vrtx.local/api/v1/auth/login
Content-Type: application/json

{
  "email": "user@{tenant}.com",
  "password": "password123"
}
```

#### Get Current User
```bash
GET http://{tenant}.vrtx.local/api/v1/auth/me
Authorization: Bearer {token}
```

#### Logout
```bash
POST http://{tenant}.vrtx.local/api/v1/auth/logout
Authorization: Bearer {token}
```

### Test Endpoints

#### Test Tenant Isolation
```bash
GET http://{tenant}.vrtx.local/test-isolation
```

---

## /etc/hosts Configuration

Add these entries to `/etc/hosts`:

```
127.0.0.1 acme.vrtx.local
127.0.0.1 techco.vrtx.local
127.0.0.1 startup.vrtx.local
127.0.0.1 crm.startup.com
```

---

## Database Seeding Commands

### Fresh Migration with Seeding
```bash
# From backend directory
cd /home/chris/PersonalProjects/vrtx/backend

# Reset central database and seed tenants
php artisan migrate:fresh --seed

# The seeder will automatically:
# 1. Create tenant records in central database
# 2. Create tenant databases (tenantacme, tenanttechco, tenantstartup)
# 3. Run migrations on each tenant database
# 4. Seed test users in each tenant database
```

### Manual Tenant Database Management
```bash
# Create tenant databases manually (if needed)
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantacme OWNER vrtx_user;"
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenanttechco OWNER vrtx_user;"
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantstartup OWNER vrtx_user;"

# Run tenant migrations
php artisan tenants:migrate

# Seed tenant databases
php artisan tenants:seed
```

---

## Testing Credentials

### Test Scenario 1: Register New User on Acme
```bash
curl -X POST http://acme.vrtx.local/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "New Acme User",
    "email": "newuser@acme.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### Test Scenario 2: Login as Existing User
```bash
curl -X POST http://techco.vrtx.local/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "testuser@techco.com",
    "password": "password123"
  }'
```

### Test Scenario 3: Verify Tenant Isolation
```bash
# This should fail (user from acme trying to login to techco)
curl -X POST http://techco.vrtx.local/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "testuser@acme.com",
    "password": "password123"
  }'
```

---

## Default Password

**All test users use the same password for easy testing**: `password123`

⚠️ **IMPORTANT**: Change all passwords before deploying to production!

---

## Quick Start Guide

1. **Start Docker services**:
   ```bash
   cd /home/chris/PersonalProjects/vrtx
   docker-compose up -d
   ```

2. **Start Backend**:
   ```bash
   cd backend
   ./dev.sh
   ```

3. **Start Frontend**:
   ```bash
   cd frontend
   pnpm dev --host 0.0.0.0
   ```

4. **Seed Database** (first time or after reset):
   ```bash
   cd backend
   php artisan migrate:fresh --seed
   ```

5. **Access Application**:
   - Open browser to http://acme.vrtx.local/login
   - Login with: `testuser@acme.com` / `password123`

---

## Troubleshooting

### Reset Everything
```bash
# Stop all services
docker-compose down

# Remove all data
docker-compose down -v

# Start fresh
docker-compose up -d
cd backend && php artisan migrate:fresh --seed
```

### Check Tenant Isolation
```bash
# Should return different users for each tenant
curl http://acme.vrtx.local/test-isolation | jq
curl http://techco.vrtx.local/test-isolation | jq
curl http://startup.vrtx.local/test-isolation | jq
```

### Verify Databases Exist
```bash
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "\l" | grep tenant
```

---

## Notes

- All tenant databases use the prefix `tenant` + tenant_id (e.g., `tenantacme`)
- Tenant identification is done via domain using `InitializeTenancyByDomain` middleware
- Each tenant has completely isolated data - users, sessions, tokens, etc.
- CORS is configured to allow all `.vrtx.local` subdomains
- Frontend automatically detects current tenant domain for API calls
