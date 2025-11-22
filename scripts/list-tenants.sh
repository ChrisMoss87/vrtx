#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  VRTX CRM - List Tenants${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Navigate to backend
cd "$(dirname "$0")/../backend" || exit 1

# List tenants using PHP
php artisan tinker --execute="
\$tenants = \App\Models\Tenant::with('domains')->get();

if (\$tenants->isEmpty()) {
    echo '${YELLOW}No tenants found.${NC}\n';
    echo '\n';
    echo 'Create a tenant with:\n';
    echo '  ./scripts/create-tenant.sh <tenant-id> [name]\n';
} else {
    echo '${GREEN}Found ' . \$tenants->count() . ' tenant(s):${NC}\n';
    echo '\n';

    foreach (\$tenants as \$tenant) {
        echo '${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n';
        echo '${YELLOW}ID:${NC} ' . \$tenant->id . '\n';
        echo '${YELLOW}Name:${NC} ' . (\$tenant->data['name'] ?? 'N/A') . '\n';
        echo '${YELLOW}Created:${NC} ' . \$tenant->created_at->format('Y-m-d H:i:s') . '\n';

        if (\$tenant->domains->count() > 0) {
            echo '${YELLOW}Domains:${NC}\n';
            foreach (\$tenant->domains as \$domain) {
                echo '  • ' . \$domain->domain . '\n';
            }
        }

        echo '\n';
    }
}
"

echo ""
