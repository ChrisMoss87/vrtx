#!/bin/bash

# VRTX Multi-Tenant CRM - Fresh Setup Script
# This script performs a complete fresh installation with consistent test data

set -e  # Exit on error

echo "========================================="
echo "VRTX Multi-Tenant CRM - Fresh Setup"
echo "========================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in the backend directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: Must be run from the backend directory${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Dropping all tenant databases...${NC}"
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "DROP DATABASE IF EXISTS tenantacme;" 2>/dev/null || true
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "DROP DATABASE IF EXISTS tenanttechco;" 2>/dev/null || true
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "DROP DATABASE IF EXISTS tenantstartup;" 2>/dev/null || true
echo -e "${GREEN}✓ Tenant databases dropped${NC}"
echo ""

echo -e "${YELLOW}Step 2: Resetting central database and seeding tenants...${NC}"
php artisan migrate:fresh --seed
echo -e "${GREEN}✓ Central database reset and tenants seeded${NC}"
echo ""

echo -e "${YELLOW}Step 3: Creating tenant databases...${NC}"
docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantacme OWNER vrtx_user;"
echo -e "${GREEN}✓ Created database: tenantacme${NC}"

docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenanttechco OWNER vrtx_user;"
echo -e "${GREEN}✓ Created database: tenanttechco${NC}"

docker exec vrtx_postgres psql -U vrtx_user -d vrtx_crm -c "CREATE DATABASE tenantstartup OWNER vrtx_user;"
echo -e "${GREEN}✓ Created database: tenantstartup${NC}"
echo ""

echo -e "${YELLOW}Step 4: Running tenant migrations...${NC}"
php artisan tenants:migrate --force
echo -e "${GREEN}✓ Tenant migrations complete${NC}"
echo ""

echo -e "${YELLOW}Step 5: Seeding tenant databases with test users...${NC}"
php artisan tenants:seed --class=TenantUserSeeder --force
echo -e "${GREEN}✓ Tenant databases seeded${NC}"
echo ""

echo "========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "========================================="
echo ""
echo "Test Users Created:"
echo ""
echo "Acme Corporation (http://acme.vrtx.local):"
echo "  - john@acme.com / password123"
echo "  - testuser@acme.com / password123"
echo ""
echo "TechCo Solutions (http://techco.vrtx.local):"
echo "  - bob@techco.com / password123"
echo "  - testuser@techco.com / password123"
echo ""
echo "Startup Inc (http://startup.vrtx.local):"
echo "  - alice@startup.com / password123"
echo ""
echo "For full credentials, see: CREDENTIALS.md"
echo ""
