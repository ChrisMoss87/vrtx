# Developer Guide - VRTX CRM

## Quick Start

### One Command to Rule Them All

```bash
./dev.sh
```

This single command:
- ✓ Cleans up any existing processes
- ✓ Starts Docker services (PostgreSQL, Redis, Mailhog)
- ✓ Starts Laravel backend (port 8000)
- ✓ Starts SvelteKit frontend (port 5173)
- ✓ Captures logs to `logs/` directory
- ✓ Gracefully shuts down everything on Ctrl+C

### Access Points

- **Frontend App**: http://localhost:5173
- **Backend API**: http://localhost:8000/api/v1
- **Mailhog UI**: http://localhost:8025
- **PostgreSQL**: localhost:5433
- **Redis**: localhost:6379

---

## Development Workflow

### 1. Start Services
```bash
./dev.sh
```

### 2. View Logs (in another terminal)
```bash
# Backend logs
tail -f logs/backend.log

# Frontend logs
tail -f logs/frontend.log

# Or both
tail -f logs/*.log
```

### 3. Make Changes
- Edit files in `backend/` or `frontend/`
- Hot reload works automatically
- See changes immediately

### 4. Run Tests
```bash
# E2E tests
pnpm test:e2e

# Only auth tests
pnpm test:e2e e2e/auth.test.ts

# Backend tests
cd backend && php artisan test
```

### 5. Stop Services
- Press `Ctrl+C` in the terminal running `./dev.sh`

---

## Common Tasks

### Create a New User
```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### Check Database
```bash
docker exec -it vrtx_postgres psql -U vrtx_user -d vrtx_crm

# List tables
\dt

# Query users
SELECT * FROM users;

# Exit
\q
```

### Clear Cache
```bash
cd backend
php artisan cache:clear
php artisan config:clear
```

### Reset Database
```bash
cd backend
php artisan migrate:fresh
```

### Add New Migration
```bash
cd backend
php artisan make:migration create_contacts_table
```

### Install New Package

**Backend:**
```bash
cd backend
composer require package-name
```

**Frontend:**
```bash
cd frontend
pnpm add package-name
```

---

## Testing

### E2E Tests (Playwright)

```bash
# Run all tests
pnpm test:e2e

# Run specific file
pnpm test:e2e e2e/auth.test.ts

# Run in headed mode (see browser)
pnpm test:e2e --headed

# Debug mode
pnpm test:e2e --debug

# Update snapshots
pnpm test:e2e --update-snapshots
```

### Unit Tests

```bash
# Frontend (Vitest)
cd frontend
pnpm test:unit

# Backend (PHPUnit)
cd backend
php artisan test
```

---

## Debugging

### Backend Issues

**Check logs:**
```bash
tail -f logs/backend.log
```

**Check if backend is running:**
```bash
curl http://localhost:8000/up
```

**Common issues:**
- Port 8000 already in use → Kill existing process: `pkill -f "php artisan serve"`
- Database connection error → Check Docker: `docker compose ps`

### Frontend Issues

**Check logs:**
```bash
tail -f logs/frontend.log
```

**Check if frontend is running:**
```bash
curl http://localhost:5173
```

**Common issues:**
- Port 5173 already in use → Kill existing process: `pkill -f "vite"`
- Module not found → Run: `pnpm install`

### Docker Issues

**Check running containers:**
```bash
docker compose ps
```

**Restart containers:**
```bash
docker compose down
docker compose up -d
```

**View container logs:**
```bash
docker compose logs postgres
docker compose logs redis
docker compose logs mailhog
```

---

## Database Management

### Migrations

```bash
# Run migrations
php artisan migrate

# Rollback
php artisan migrate:rollback

# Fresh (drop all + migrate)
php artisan migrate:fresh

# With seeding
php artisan migrate:fresh --seed
```

### Tinker (Laravel REPL)

```bash
cd backend
php artisan tinker

# Create a user
$user = App\Models\User::create([
    'name' => 'Test',
    'email' => 'test@example.com',
    'password' => bcrypt('password')
]);

# Query users
App\Models\User::all();
```

---

## Environment Variables

### Backend (.env)
```env
DB_PORT=5433  # Not 5432!
PUBLIC_API_URL=http://localhost:8000/api/v1
```

### Frontend (.env)
```env
PUBLIC_API_URL=http://localhost:8000/api/v1
```

---

## Git Workflow

### Before Committing

1. **Run tests:**
   ```bash
   pnpm test:e2e
   cd backend && php artisan test
   ```

2. **Check code style:**
   ```bash
   cd backend && ./vendor/bin/pint
   cd frontend && pnpm lint
   ```

3. **Commit:**
   ```bash
   git add .
   git commit -m "feat: add feature name"
   ```

---

## Troubleshooting

### "Failed to fetch" in browser

1. Check CORS configuration in `backend/config/cors.php`
2. Make sure backend is running
3. Check browser console for exact error

### "Address already in use"

```bash
# Find process using port 8000
lsof -i :8000

# Kill it
kill -9 <PID>

# Or use the dev script which handles this
./dev.sh
```

### "Database connection failed"

1. Check Docker containers: `docker compose ps`
2. Restart PostgreSQL: `docker compose restart postgres`
3. Check credentials in `backend/.env`

### Tests failing

1. Make sure services are running: `./dev.sh`
2. Clear browser cache
3. Check test logs for specific errors
4. Run in headed mode: `pnpm test:e2e --headed`

---

## Performance Tips

### Faster Frontend Builds

```bash
# Use production build for testing
cd frontend
pnpm build
pnpm preview
```

### Database Query Optimization

```bash
# Enable query log
cd backend
php artisan tinker

DB::enableQueryLog();
# Run your code
DB::getQueryLog();
```

---

## Resources

- **Laravel Docs**: https://laravel.com/docs
- **SvelteKit Docs**: https://kit.svelte.dev/docs
- **Playwright Docs**: https://playwright.dev/
- **Tailwind Docs**: https://tailwindcss.com/docs

---

## Need Help?

1. Check logs: `tail -f logs/*.log`
2. Review `AUTHENTICATION_COMPLETE.md` for auth flow details
3. Review `ARCHITECTURE.md` for system design
4. Check `SETUP_COMPLETE.md` for initial setup steps

---

**Happy Coding!** 🚀
