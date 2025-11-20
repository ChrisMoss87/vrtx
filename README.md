# VRTX CRM - Multi-Tenancy Customer Relationship Management System

A modern, multi-tenant CRM system built with Laravel, SvelteKit, and clean architecture principles.

## Features

- Multi-tenancy with subdomain and custom domain support
- Domain-Driven Design (DDD) architecture
- Clean separation of concerns
- Contact and company management
- Sales pipeline and deal tracking
- Built for future mobile app integration
- Single-server deployment ready

## Technology Stack

### Backend
- Laravel 12 with PHP 8.4
- PostgreSQL 17 with PostGIS
- Redis for caching and queues
- Laravel Tenancy (stancl/tenancy)

### Frontend
- SvelteKit with TypeScript
- Tailwind CSS v4
- shadcn-svelte components
- arktype for runtime validation
- Svelte 5 with runes

### Infrastructure
- Docker Compose (PostgreSQL, Redis, Mailhog)
- Nginx (native on host)
- Single server deployment

## Prerequisites

- PHP 8.4 with extensions: pdo_pgsql, redis, mbstring, xml
- Composer
- Node.js 20+
- pnpm
- Docker and Docker Compose
- Nginx
- PostgreSQL client tools (for migrations)
- mkcert (for local SSL certificates)

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository>
cd vrtx
cp .env.example .env
```

Edit `.env` and set your database credentials.

### 2. Start Infrastructure Services

```bash
docker-compose up -d
```

This starts PostgreSQL, Redis, and Mailhog.

### 3. Setup Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# Update .env with your database credentials
# DB_DATABASE=vrtx_crm
# DB_USERNAME=vrtx_user
# DB_PASSWORD=vrtx_password

# Run migrations
php artisan migrate
```

### 4. Setup Frontend

```bash
cd frontend
pnpm install
cp .env.example .env

# Edit .env if needed
# PUBLIC_API_URL=https://api.vrtx.local
```

### 5. Generate SSL Certificates

```bash
# Install mkcert if you haven't
# Linux: sudo apt install mkcert
# Or follow: https://github.com/FiloSottile/mkcert

mkcert -install
mkcert vrtx.local "*.vrtx.local"

sudo mkdir -p /etc/ssl/certs /etc/ssl/private
sudo cp vrtx.local+1.pem /etc/ssl/certs/vrtx.local.pem
sudo cp vrtx.local+1-key.pem /etc/ssl/private/vrtx.local-key.pem
sudo chmod 644 /etc/ssl/certs/vrtx.local.pem
sudo chmod 600 /etc/ssl/private/vrtx.local-key.pem
```

### 6. Configure Nginx

```bash
sudo cp nginx/conf.d/vrtx.conf /etc/nginx/sites-available/vrtx
sudo ln -s /etc/nginx/sites-available/vrtx /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 7. Update /etc/hosts

```bash
echo "127.0.0.1 vrtx.local api.vrtx.local app.vrtx.local tenant1.vrtx.local" | sudo tee -a /etc/hosts
```

### 8. Start Development Servers

**Terminal 1 - Backend:**
```bash
cd backend
php artisan serve --host=0.0.0.0 --port=8000

# Or use php-fpm if configured
# sudo systemctl start php8.4-fpm
```

**Terminal 2 - Frontend:**
```bash
cd frontend
pnpm dev -- --host 0.0.0.0
```

### 9. Access the Application

- **API**: https://api.vrtx.local
- **Frontend App**: https://app.vrtx.local
- **Mailhog**: http://localhost:8025
- **PostgreSQL**: localhost:5432
- **Redis**: localhost:6379

## Project Structure

```
vrtx/
├── backend/                 # Laravel API
│   ├── src/                # DDD structure (to be created)
│   │   ├── Domain/
│   │   ├── Application/
│   │   ├── Infrastructure/
│   │   └── Presentation/
│   ├── app/                # Laravel default structure
│   ├── config/
│   ├── database/
│   │   ├── migrations/     # Central migrations
│   │   └── migrations/tenant/ # Tenant migrations
│   └── routes/
│       ├── api.php
│       └── tenant.php
│
├── frontend/               # SvelteKit app
│   ├── src/
│   │   ├── lib/
│   │   │   ├── components/
│   │   │   │   ├── ui/    # shadcn-svelte
│   │   │   │   └── domain/ # Domain components
│   │   │   ├── api/
│   │   │   ├── stores/
│   │   │   ├── types/
│   │   │   └── utils/
│   │   └── routes/
│   └── static/
│
├── nginx/                  # Nginx configurations
│   └── conf.d/
│       └── vrtx.conf
│
├── docker-compose.yml
├── ARCHITECTURE.md         # Detailed architecture docs
└── README.md
```

## Development Workflow

### Creating a New Tenant

```bash
php artisan tenants:create {tenant_name}
```

### Running Tenant Migrations

```bash
php artisan tenants:migrate
```

### Rollback Tenant Migrations

```bash
php artisan tenants:rollback
```

### Adding a Component (shadcn-svelte)

```bash
cd frontend
npx shadcn-svelte@latest add button
npx shadcn-svelte@latest add card
```

## Multi-Tenancy

### How It Works

1. **Request arrives** at `tenant1.vrtx.local`
2. **Nginx** extracts subdomain and passes `X-Tenant` header
3. **Laravel middleware** identifies tenant from header/domain
4. **Database connection** switches to tenant-specific database
5. **All queries** automatically scoped to tenant

### Tenant Isolation

- Each tenant has a separate PostgreSQL database
- Central database stores tenant metadata
- Strict isolation prevents cross-tenant data access
- Custom domains supported via `domains` table

## API Documentation

### Authentication

```bash
POST /api/v1/auth/login
POST /api/v1/auth/register
POST /api/v1/auth/logout
```

### Contacts

```bash
GET    /api/v1/contacts
POST   /api/v1/contacts
GET    /api/v1/contacts/{id}
PUT    /api/v1/contacts/{id}
DELETE /api/v1/contacts/{id}
```

See `ARCHITECTURE.md` for complete API documentation.

## Testing

### Backend Tests

```bash
cd backend
php artisan test
```

### Frontend Tests

```bash
cd frontend
pnpm test:unit          # Vitest unit tests
pnpm test:e2e          # Playwright E2E tests
```

## Deployment

Detailed deployment instructions coming soon. The application is designed for single-server deployment with:

- Nginx as reverse proxy
- PHP-FPM for Laravel
- Node.js for SvelteKit (or build static)
- PostgreSQL database
- Redis for caching/queues

## Contributing

This is a private project. Contact the maintainer for contribution guidelines.

## License

Proprietary - All rights reserved

## Support

For issues and questions, contact the development team.

---

**Next Steps**: See `ARCHITECTURE.md` for detailed implementation roadmap and domain design.
