ever# VRTX CRM - Production Deployment Guide

## Server Requirements (AlmaLinux)

### Minimum Requirements
- AlmaLinux 8/9
- 4 CPU cores
- 8GB RAM
- 50GB SSD storage
- PostgreSQL 15+
- Redis 7+
- Node.js 20 LTS
- PHP 8.2+
- Nginx

---

## 1. Server Setup

### Install Required Packages

```bash
# Update system
sudo dnf update -y

# Install EPEL and Remi repos
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm

# Install PHP 8.2
sudo dnf module reset php
sudo dnf module enable php:remi-8.2
sudo dnf install -y php php-cli php-fpm php-pgsql php-mbstring php-xml php-curl php-zip php-gd php-redis php-bcmath php-intl

# Install Node.js 20
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

# Install pnpm
npm install -g pnpm

# Install PostgreSQL 15
sudo dnf install -y postgresql15-server postgresql15
sudo postgresql-setup --initdb
sudo systemctl enable --now postgresql

# Install Redis
sudo dnf install -y redis
sudo systemctl enable --now redis

# Install Nginx
sudo dnf install -y nginx
sudo systemctl enable --now nginx

# Install Supervisor (for process management)
sudo dnf install -y supervisor
sudo systemctl enable --now supervisord

# Install Certbot for SSL
sudo dnf install -y certbot python3-certbot-nginx
```

---

## 2. Database Setup

```bash
# Create PostgreSQL user and databases
sudo -u postgres psql

CREATE USER vrtx_user WITH PASSWORD 'your-secure-password';
CREATE DATABASE vrtx_central OWNER vrtx_user;
GRANT ALL PRIVILEGES ON DATABASE vrtx_central TO vrtx_user;

# Enable UUID extension
\c vrtx_central
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
\q

# Update pg_hba.conf for password authentication
sudo nano /var/lib/pgsql/data/pg_hba.conf
# Change 'ident' to 'md5' for local connections

sudo systemctl restart postgresql
```

---

## 3. Application Setup

### Clone Repository

```bash
# Create app directory
sudo mkdir -p /var/www/vrtx
sudo chown $USER:$USER /var/www/vrtx

# Clone repository
cd /var/www/vrtx
git clone https://github.com/your-repo/vrtx.git .
```

### Backend Setup

```bash
cd /var/www/vrtx/backend

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install dependencies
composer install --no-dev --optimize-autoloader

# Copy and configure environment
cp .env.production.example .env
nano .env  # Edit with your production values

# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R nginx:nginx /var/www/vrtx/backend/storage
sudo chown -R nginx:nginx /var/www/vrtx/backend/bootstrap/cache
sudo chmod -R 775 /var/www/vrtx/backend/storage
sudo chmod -R 775 /var/www/vrtx/backend/bootstrap/cache
```

### Frontend Setup

```bash
cd /var/www/vrtx/frontend

# Install dependencies
pnpm install

# Copy and configure environment
cp .env.production.example .env
nano .env  # Edit with your production values

# Build for production (Node adapter)
ADAPTER=node pnpm build
```

---

## 4. Nginx Configuration

### API Backend (Laravel)

```nginx
# /etc/nginx/conf.d/api.vrtx.io.conf

server {
    listen 80;
    server_name api.vrtx.io;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.vrtx.io;

    ssl_certificate /etc/letsencrypt/live/api.vrtx.io/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.vrtx.io/privkey.pem;

    root /var/www/vrtx/backend/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # API rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

### Frontend (SvelteKit)

```nginx
# /etc/nginx/conf.d/app.vrtx.io.conf

upstream sveltekit {
    server 127.0.0.1:3000;
    keepalive 64;
}

server {
    listen 80;
    server_name app.vrtx.io *.vrtx.io;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name app.vrtx.io *.vrtx.io;

    ssl_certificate /etc/letsencrypt/live/app.vrtx.io/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.vrtx.io/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self' https://api.vrtx.io wss:;" always;

    # Gzip
    gzip on;
    gzip_types text/plain application/json application/javascript text/css;

    location / {
        proxy_pass http://sveltekit;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 300;
    }

    # Static assets caching
    location /_app/ {
        proxy_pass http://sveltekit;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

---

## 5. Process Management (Supervisor)

### Laravel Queue Worker

```ini
# /etc/supervisord.d/vrtx-queue.ini

[program:vrtx-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vrtx/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=nginx
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/vrtx/queue.log
stopwaitsecs=3600
```

### Laravel Scheduler

```ini
# /etc/supervisord.d/vrtx-scheduler.ini

[program:vrtx-scheduler]
command=/bin/bash -c "while [ true ]; do php /var/www/vrtx/backend/artisan schedule:run --verbose --no-interaction >> /var/log/vrtx/scheduler.log 2>&1; sleep 60; done"
autostart=true
autorestart=true
user=nginx
redirect_stderr=true
stdout_logfile=/var/log/vrtx/scheduler.log
```

### SvelteKit Frontend

```ini
# /etc/supervisord.d/vrtx-frontend.ini

[program:vrtx-frontend]
command=/usr/bin/node /var/www/vrtx/frontend/build/index.js
directory=/var/www/vrtx/frontend
autostart=true
autorestart=true
user=nginx
environment=NODE_ENV="production",PORT="3000",ORIGIN="https://app.vrtx.io"
redirect_stderr=true
stdout_logfile=/var/log/vrtx/frontend.log
```

### Start Supervisor

```bash
# Create log directory
sudo mkdir -p /var/log/vrtx
sudo chown nginx:nginx /var/log/vrtx

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

---

## 6. SSL Certificates

```bash
# Obtain SSL certificates
sudo certbot certonly --nginx -d api.vrtx.io
sudo certbot certonly --nginx -d app.vrtx.io -d "*.vrtx.io"

# Auto-renewal is configured automatically
sudo certbot renew --dry-run
```

---

## 7. Firewall Configuration

```bash
# Configure firewalld
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-port=3000/tcp  # If needed for internal
sudo firewall-cmd --reload
```

---

## 8. Backup Strategy

### Database Backup Script

```bash
# /usr/local/bin/vrtx-backup.sh

#!/bin/bash
BACKUP_DIR="/var/backups/vrtx"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

mkdir -p $BACKUP_DIR

# Backup central database
pg_dump -U vrtx_user vrtx_central | gzip > $BACKUP_DIR/central_$DATE.sql.gz

# Backup all tenant databases
for db in $(psql -U vrtx_user -d vrtx_central -t -c "SELECT tenants.id FROM tenants"); do
    pg_dump -U vrtx_user tenant_$db | gzip > $BACKUP_DIR/tenant_${db}_$DATE.sql.gz
done

# Clean old backups
find $BACKUP_DIR -name "*.sql.gz" -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
```

### Cron Job

```bash
# Add to crontab
sudo crontab -e

# Daily backup at 2 AM
0 2 * * * /usr/local/bin/vrtx-backup.sh >> /var/log/vrtx/backup.log 2>&1
```

---

## 9. Monitoring

### Health Check Endpoint

The API includes a health check at `/api/health` that returns:
- Database connectivity
- Redis connectivity
- Queue status

### Recommended Monitoring Tools
- **Uptime**: UptimeRobot, Pingdom
- **Logs**: Logrotate + centralized logging (e.g., Papertrail)
- **Metrics**: Prometheus + Grafana
- **Errors**: Sentry

---

## 10. Deployment Commands

### Deploy Updates

```bash
#!/bin/bash
# /usr/local/bin/vrtx-deploy.sh

cd /var/www/vrtx

# Pull latest code
git pull origin main

# Backend
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart

# Frontend
cd ../frontend
pnpm install
ADAPTER=node pnpm build

# Restart services
sudo supervisorctl restart vrtx-frontend
sudo systemctl reload php-fpm
sudo systemctl reload nginx

echo "Deployment complete!"
```

---

## Quick Reference

| Service | Port | URL |
|---------|------|-----|
| Frontend | 3000 | https://app.vrtx.io |
| API | 80/443 | https://api.vrtx.io |
| PostgreSQL | 5432 | localhost |
| Redis | 6379 | localhost |

### Common Commands

```bash
# Check service status
sudo supervisorctl status

# View logs
tail -f /var/log/vrtx/frontend.log
tail -f /var/log/vrtx/queue.log

# Restart services
sudo supervisorctl restart all
sudo systemctl restart php-fpm
sudo systemctl restart nginx

# Clear Laravel caches
cd /var/www/vrtx/backend
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
