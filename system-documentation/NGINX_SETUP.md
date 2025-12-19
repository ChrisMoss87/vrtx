# Nginx Setup for Multi-Tenant Domains

## Configuration File Created

A new Nginx configuration file has been created at:
```
/tmp/vrtx-tenants.conf
```

This configuration proxies all tenant domains to Laravel's built-in development server running on `localhost:8000`.

## Installation Steps

Run these commands to install and enable the configuration:

```bash
# 1. Copy the config to Nginx sites-available
sudo cp /tmp/vrtx-tenants.conf /etc/nginx/sites-available/vrtx-tenants.conf

# 2. Enable the site by creating a symlink
sudo ln -sf /etc/nginx/sites-available/vrtx-tenants.conf /etc/nginx/sites-enabled/vrtx-tenants.conf

# 3. Test the Nginx configuration
sudo nginx -t

# 4. If test passes, reload Nginx
sudo systemctl reload nginx
# OR
sudo nginx -s reload
```

## What This Configuration Does

- **Listens on port 80** for all tenant domains
- **Server names**: `*.vrtx.local` and `crm.startup.com`
- **Proxies requests** to `http://localhost:8000` (Laravel's dev server)
- **Preserves headers**: Host, X-Real-IP, X-Forwarded-* headers
- **WebSocket support**: For Vite HMR (Hot Module Replacement)

## Tenant Domains Covered

Once enabled, these URLs will work:
- http://acme.vrtx.local/
- http://techco.vrtx.local/
- http://startup.vrtx.local/
- http://crm.startup.com/

All will proxy to Laravel with the correct Host header, triggering tenant identification.

## Testing After Setup

### Test Tenant Root
```bash
curl http://acme.vrtx.local/
# Should show Laravel welcome or tenant-specific page
```

### Test Tenant Isolation Endpoint
```bash
curl http://acme.vrtx.local/test-isolation
# Should return:
# {"tenant_id":"acme","database":"tenant_acme","users":[...]}
```

### Test Registration API
```bash
curl -X POST http://acme.vrtx.local/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@acme.com",
    "password": "password",
    "password_confirmation": "password"
  }'
```

### Test in Browser
Open your browser and visit:
- http://acme.vrtx.local:5173/register (Frontend - if updated for tenancy)
- http://acme.vrtx.local/ (Backend - Laravel welcome)
- http://acme.vrtx.local/test-isolation (Tenant isolation test)

## Important Notes

1. **Laravel must be running**: Make sure `./dev.sh` is running so Laravel is on `localhost:8000`

2. **/etc/hosts must be configured**: Ensure these entries exist:
   ```
   127.0.0.1 acme.vrtx.local
   127.0.0.1 techco.vrtx.local
   127.0.0.1 startup.vrtx.local
   127.0.0.1 crm.startup.com
   ```

3. **Fix incorrect entry**: Change `127.0.0.0 acme.vrtx.local` to `127.0.0.1 acme.vrtx.local`

4. **Port 80**: Nginx listens on port 80, so you access http://acme.vrtx.local (no :8000 needed)

## Troubleshooting

### If you still see Nginx default page:
```bash
# Check if the site is enabled
ls -la /etc/nginx/sites-enabled/ | grep vrtx

# Check Nginx error log
sudo tail -f /var/log/nginx/error.log

# Check if config is loaded
sudo nginx -T | grep vrtx
```

### If you get 502 Bad Gateway:
- Make sure Laravel is running: `./dev.sh`
- Check if port 8000 is accessible: `curl http://localhost:8000`

### If domain doesn't resolve:
- Check /etc/hosts: `cat /etc/hosts | grep vrtx`
- Verify DNS: `ping acme.vrtx.local`

## Configuration File Contents

```nginx
server {
    listen 80;
    server_name *.vrtx.local crm.startup.com;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;

        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
```

## Next Steps After Nginx Setup

1. Access http://acme.vrtx.local/test-isolation to verify tenancy
2. Update frontend to use tenant-aware API URLs
3. Test registration flow on each tenant domain
4. Verify complete isolation between tenants
