#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  VRTX CRM - Create Tenant${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check arguments
if [ -z "$1" ]; then
    echo -e "${RED}Error: Tenant ID required${NC}"
    echo ""
    echo "Usage: $0 <tenant-id> [tenant-name]"
    echo ""
    echo "Examples:"
    echo "  $0 acme"
    echo "  $0 acme 'Acme Corporation'"
    echo ""
    exit 1
fi

TENANT_ID="$1"
TENANT_NAME="${2:-$TENANT_ID}"

echo -e "${YELLOW}Creating tenant...${NC}"
echo -e "  ID: ${TENANT_ID}"
echo -e "  Name: ${TENANT_NAME}"
echo ""

# Navigate to backend
cd "$(dirname "$0")/../backend" || exit 1

# Create tenant using PHP
php artisan tinker --execute="
\$tenant = \App\Models\Tenant::create([
    'id' => '${TENANT_ID}',
    'data' => ['name' => '${TENANT_NAME}']
]);

\$tenant->domains()->create([
    'domain' => '${TENANT_ID}.vrtx.local'
]);

echo 'Tenant created successfully!\n';
echo 'ID: ' . \$tenant->id . '\n';
echo 'Domain: ${TENANT_ID}.vrtx.local\n';
"

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ Tenant created successfully!${NC}"
    echo ""
    echo -e "${YELLOW}Next steps:${NC}"
    echo ""
    echo -e "1. Add to /etc/hosts:"
    echo -e "   ${BLUE}echo '127.0.0.1 ${TENANT_ID}.vrtx.local' | sudo tee -a /etc/hosts${NC}"
    echo ""
    echo -e "2. Run tenant migrations:"
    echo -e "   ${BLUE}cd backend && php artisan tenants:migrate${NC}"
    echo ""
    echo -e "3. Access tenant site:"
    echo -e "   ${BLUE}http://${TENANT_ID}.vrtx.local:5173${NC}"
    echo ""
    echo -e "4. Or via API:"
    echo -e "   ${BLUE}curl http://localhost:8000/api/v1/tenants/${TENANT_ID}${NC}"
    echo ""
else
    echo -e "${RED}✗ Failed to create tenant${NC}"
    exit 1
fi
