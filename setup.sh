#!/bin/bash

set -e

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}=========================================${NC}"
echo -e "${BLUE}  VRTX CRM - Complete Setup${NC}"
echo -e "${BLUE}=========================================${NC}"
echo ""

# Check for required tools
check_requirements() {
    local missing=()

    command -v docker >/dev/null 2>&1 || missing+=("docker")
    command -v php >/dev/null 2>&1 || missing+=("php")
    command -v pnpm >/dev/null 2>&1 || missing+=("pnpm")

    if [ ${#missing[@]} -ne 0 ]; then
        echo -e "${RED}Missing required tools: ${missing[*]}${NC}"
        exit 1
    fi
}

# Setup hosts entries
setup_hosts() {
    echo -e "${YELLOW}Step 1: Setting up hosts entries...${NC}"

    local domains=("vrtx.local" "techco.vrtx.local" "acme.vrtx.local" "startup.vrtx.local")
    local needs_update=false

    for domain in "${domains[@]}"; do
        if ! grep -q "$domain" /etc/hosts 2>/dev/null; then
            needs_update=true
            break
        fi
    done

    if [ "$needs_update" = true ]; then
        echo -e "${YELLOW}Adding domains to /etc/hosts (requires sudo)...${NC}"

        if command -v hostctl >/dev/null 2>&1; then
            sudo hostctl add domains local "${domains[@]}"
        else
            for domain in "${domains[@]}"; do
                if ! grep -q "$domain" /etc/hosts; then
                    echo "127.0.0.1 $domain" | sudo tee -a /etc/hosts >/dev/null
                fi
            done
        fi
        echo -e "${GREEN}✓ Hosts entries added${NC}"
    else
        echo -e "${GREEN}✓ Hosts entries already configured${NC}"
    fi
    echo ""
}

# Start Docker services
start_docker() {
    echo -e "${YELLOW}Step 2: Starting Docker services...${NC}"
    docker compose up -d

    echo -e "${BLUE}Waiting for PostgreSQL to be ready...${NC}"
    sleep 3

    # Wait for postgres to accept connections
    for i in {1..30}; do
        if docker exec vrtx_postgres pg_isready -U vrtx_user >/dev/null 2>&1; then
            echo -e "${GREEN}✓ PostgreSQL is ready${NC}"
            break
        fi
        sleep 1
    done
    echo ""
}

# Install dependencies
install_deps() {
    echo -e "${YELLOW}Step 3: Installing dependencies...${NC}"

    echo -e "${BLUE}Installing backend dependencies...${NC}"
    cd backend
    if [ ! -d "vendor" ]; then
        composer install --no-interaction
    else
        echo -e "${GREEN}✓ Backend dependencies already installed${NC}"
    fi

    echo -e "${BLUE}Installing frontend dependencies...${NC}"
    cd ../frontend
    if [ ! -d "node_modules" ]; then
        pnpm install
    else
        echo -e "${GREEN}✓ Frontend dependencies already installed${NC}"
    fi

    cd ..
    echo ""
}

# Setup environment files
setup_env() {
    echo -e "${YELLOW}Step 4: Setting up environment files...${NC}"

    if [ ! -f "backend/.env" ]; then
        cp backend/.env.example backend/.env
        cd backend
        php artisan key:generate
        cd ..
        echo -e "${GREEN}✓ Backend .env created${NC}"
    else
        echo -e "${GREEN}✓ Backend .env already exists${NC}"
    fi

    if [ ! -f "frontend/.env" ]; then
        cp frontend/.env.example frontend/.env
        echo -e "${GREEN}✓ Frontend .env created${NC}"
    else
        echo -e "${GREEN}✓ Frontend .env already exists${NC}"
    fi
    echo ""
}

# Run migrations and seeders
setup_database() {
    echo -e "${YELLOW}Step 5: Setting up database...${NC}"

    cd backend

    echo -e "${BLUE}Running central database migrations...${NC}"
    php artisan migrate --force

    echo -e "${BLUE}Seeding tenants...${NC}"
    php artisan db:seed --class=TenantSeeder --force

    echo -e "${BLUE}Creating tenant databases...${NC}"
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenanttechco OWNER vrtx_user;" 2>/dev/null || true
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantacme OWNER vrtx_user;" 2>/dev/null || true
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantstartup OWNER vrtx_user;" 2>/dev/null || true

    echo -e "${BLUE}Running tenant migrations...${NC}"
    php artisan tenants:migrate --force

    echo -e "${BLUE}Seeding tenant databases...${NC}"
    php artisan tenants:seed --class=TenantDemoSeeder --force

    cd ..
    echo -e "${GREEN}✓ Database setup complete${NC}"
    echo ""
}

# Fresh setup (drops everything and recreates)
fresh_setup() {
    echo -e "${YELLOW}Step 5: Fresh database setup...${NC}"

    cd backend

    echo -e "${BLUE}Dropping tenant databases...${NC}"
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "DROP DATABASE IF EXISTS tenanttechco;" 2>/dev/null || true
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "DROP DATABASE IF EXISTS tenantacme;" 2>/dev/null || true
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "DROP DATABASE IF EXISTS tenantstartup;" 2>/dev/null || true

    echo -e "${BLUE}Creating tenant databases...${NC}"
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenanttechco OWNER vrtx_user;" 2>/dev/null || true
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantacme OWNER vrtx_user;" 2>/dev/null || true
    docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantstartup OWNER vrtx_user;" 2>/dev/null || true

    echo -e "${BLUE}Running fresh migrations with seeders...${NC}"
    php artisan migrate:fresh --seed --force

    cd ..
    echo -e "${GREEN}✓ Fresh database setup complete${NC}"
    echo ""
}

# Print credentials
print_credentials() {
    echo -e "${GREEN}=========================================${NC}"
    echo -e "${GREEN}  Setup Complete!${NC}"
    echo -e "${GREEN}=========================================${NC}"
    echo ""
    echo -e "${BLUE}To start development servers:${NC}"
    echo -e "  ./dev.sh"
    echo ""
    echo -e "${BLUE}Test Tenants:${NC}"
    echo ""
    echo -e "${YELLOW}TechCo Solutions${NC} (https://techco.vrtx.local)"
    echo -e "  bob@techco.com / password123 (admin)"
    echo -e "  sarah@techco.com / password123 (manager)"
    echo -e "  mike@techco.com / password123 (sales_rep)"
    echo ""
    echo -e "${YELLOW}Acme Corporation${NC} (https://acme.vrtx.local)"
    echo -e "  admin@acme.com / password123 (admin)"
    echo -e "  john@acme.com / password123 (manager)"
    echo -e "  jane@acme.com / password123 (sales_rep)"
    echo ""
    echo -e "${YELLOW}Startup Inc${NC} (https://startup.vrtx.local)"
    echo -e "  alice@startup.com / password123 (admin)"
    echo -e "  charlie@startup.com / password123 (sales_rep)"
    echo ""
}

# Main
main() {
    local fresh=false

    # Parse arguments
    while [[ "$#" -gt 0 ]]; do
        case $1 in
            --fresh|-f) fresh=true ;;
            --help|-h)
                echo "Usage: ./setup.sh [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --fresh, -f    Drop all data and start fresh"
                echo "  --help, -h     Show this help message"
                exit 0
                ;;
            *) echo "Unknown option: $1"; exit 1 ;;
        esac
        shift
    done

    check_requirements
    setup_hosts
    start_docker
    install_deps
    setup_env

    if [ "$fresh" = true ]; then
        fresh_setup
    else
        setup_database
    fi

    print_credentials
}

main "$@"
