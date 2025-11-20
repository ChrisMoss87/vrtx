#!/bin/bash

echo "==================================="
echo "VRTX CRM Multi-Tenancy Setup Script"
echo "==================================="

# Check if .env exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Start Docker services (only infrastructure: DB, Redis, Mailhog)
echo ""
echo "Starting infrastructure services (PostgreSQL, Redis, Mailhog)..."
docker-compose up -d

# Wait for PostgreSQL to be ready
echo ""
echo "Waiting for PostgreSQL to be ready..."
sleep 5

# Setup Laravel Backend
echo ""
echo "Setting up Laravel backend..."
cd backend

if [ ! -f .env ]; then
    echo "Creating backend .env file..."
    cp .env.example .env
fi

if [ ! -d "vendor" ]; then
    echo "Installing Laravel dependencies..."
    composer install
fi

echo "Generating application key..."
php artisan key:generate

echo ""
read -p "Do you want to run migrations? (y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate:fresh
fi

cd ..

# Setup SvelteKit Frontend
echo ""
echo "Setting up SvelteKit frontend..."
cd frontend

if [ ! -f ".env" ]; then
    echo "Creating frontend .env file..."
    cat > .env << EOF
PUBLIC_API_URL=https://api.vrtx.local
EOF
fi

if [ ! -d "node_modules" ]; then
    echo "Installing frontend dependencies..."
    npm install
fi

cd ..

echo ""
echo "==================================="
echo "Setup Complete!"
echo "==================================="
echo ""
echo "Next steps:"
echo "1. Copy the nginx configuration to /etc/nginx/sites-available/:"
echo "   sudo cp nginx/conf.d/vrtx.conf /etc/nginx/sites-available/vrtx"
echo ""
echo "2. Create symbolic link to sites-enabled:"
echo "   sudo ln -s /etc/nginx/sites-available/vrtx /etc/nginx/sites-enabled/"
echo ""
echo "3. Generate SSL certificates (using mkcert or similar):"
echo "   mkcert vrtx.local '*.vrtx.local'"
echo "   sudo cp vrtx.local+1.pem /etc/ssl/certs/vrtx.local.pem"
echo "   sudo cp vrtx.local+1-key.pem /etc/ssl/private/vrtx.local-key.pem"
echo ""
echo "4. Add to /etc/hosts:"
echo "   127.0.0.1 vrtx.local api.vrtx.local app.vrtx.local"
echo ""
echo "5. Test nginx configuration:"
echo "   sudo nginx -t"
echo ""
echo "6. Reload nginx:"
echo "   sudo systemctl reload nginx"
echo ""
echo "7. Start the backend (in backend directory):"
echo "   php artisan serve --host=0.0.0.0 --port=8000"
echo "   Or use php-fpm with nginx"
echo ""
echo "8. Start the frontend (in frontend directory):"
echo "   npm run dev -- --host 0.0.0.0"
echo ""
