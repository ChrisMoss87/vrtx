# Quick Start Guide - VRTX CRM

## Initial Setup (One Time)

### 1. Start Infrastructure
```bash
docker-compose up -d
```

### 2. Setup Backend
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Edit .env with database credentials
php artisan migrate
```

### 3. Setup Frontend
```bash
cd frontend
pnpm install
cp .env.example .env
```

### 4. Setup Nginx & SSL

**Generate SSL Certificate:**
```bash
mkcert vrtx.local "*.vrtx.local"
sudo cp vrtx.local+1.pem /etc/ssl/certs/vrtx.local.pem
sudo cp vrtx.local+1-key.pem /etc/ssl/private/vrtx.local-key.pem
sudo chmod 644 /etc/ssl/certs/vrtx.local.pem
sudo chmod 600 /etc/ssl/private/vrtx.local-key.pem
```

**Configure Nginx:**
```bash
sudo cp nginx/conf.d/vrtx.conf /etc/nginx/sites-available/vrtx
sudo ln -s /etc/nginx/sites-available/vrtx /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

**Update /etc/hosts:**
```bash
echo "127.0.0.1 vrtx.local api.vrtx.local app.vrtx.local" | sudo tee -a /etc/hosts
```

---

## Daily Development

### Start Services (3 terminals)

**Terminal 1 - Infrastructure:**
```bash
docker-compose up
```

**Terminal 2 - Backend:**
```bash
cd backend
php artisan serve --host=0.0.0.0 --port=8000
```

**Terminal 3 - Frontend:**
```bash
cd frontend
pnpm dev
```

### Access URLs
- Frontend: https://app.vrtx.local
- API: https://api.vrtx.local
- Mailhog: http://localhost:8025

---

## Creating Your First Tenant

Once the system is running, you'll need to create a tenant programmatically:

```bash
cd backend
php artisan tinker
```

Then in tinker:
```php
$tenant = \App\Models\Tenant::create([
    'id' => 'tenant1',
    'data' => [
        'name' => 'Test Company',
    ]
]);
$tenant->domains()->create([
    'domain' => 'tenant1.vrtx.local'
]);
```

Don't forget to add to /etc/hosts:
```bash
echo "127.0.0.1 tenant1.vrtx.local" | sudo tee -a /etc/hosts
```

Then access your tenant at: https://tenant1.vrtx.local

---

## Common Commands

### Backend
```bash
# Run migrations (central)
php artisan migrate

# Run tenant migrations
php artisan tenants:migrate

# Create new migration
php artisan make:migration create_contacts_table

# Create tenant migration
php artisan make:migration create_contacts_table --path=database/migrations/tenant

# Clear cache
php artisan cache:clear
php artisan config:clear

# Run tests
php artisan test
```

### Frontend
```bash
# Install dependencies
pnpm install

# Run dev server
pnpm dev

# Build for production
pnpm build

# Preview production build
pnpm preview

# Run tests
pnpm test:unit
pnpm test:e2e

# Lint and format
pnpm lint
pnpm format

# Add shadcn component
npx shadcn-svelte@latest add button
```

### Docker
```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f

# Rebuild containers
docker-compose up -d --build

# Access PostgreSQL
docker-compose exec postgres psql -U vrtx_user -d vrtx_crm

# Access Redis CLI
docker-compose exec redis redis-cli
```

---

## Troubleshooting

### Laravel Can't Connect to Database
1. Check Docker containers: `docker-compose ps`
2. Check .env DB credentials
3. Wait a few seconds after docker-compose up

### Frontend Can't Connect to API
1. Check backend is running on port 8000
2. Check Nginx is running: `sudo systemctl status nginx`
3. Check SSL certificates exist
4. Check /etc/hosts has entries

### Nginx 502 Bad Gateway
1. Backend not running - start with `php artisan serve`
2. Frontend not running - start with `pnpm dev`
3. Check nginx error logs: `sudo tail -f /var/log/nginx/vrtx-*-error.log`

### SSL Certificate Issues
1. Trust mkcert CA: `mkcert -install`
2. Regenerate certificates
3. Check certificate permissions

---

## Next Steps After Setup

See `ARCHITECTURE.md` for:
- Detailed domain design
- Implementation roadmap
- Database schemas
- API documentation

Start implementing:
1. Authentication system (Laravel Sanctum)
2. Tenant registration flow
3. First domain: Contact Management
4. Frontend auth pages
5. Contact CRUD interface
