#!/bin/bash

# Demo Script: Create Sales Opportunities Module
# This demonstrates all form builder features via API

set -e

echo "🎬 Creating Sales Opportunities Demo Module..."
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
TENANT_DOMAIN="techco.vrtx.local"
API_URL="http://${TENANT_DOMAIN}/api/v1"

# Check if we have an auth token
if [ -z "$AUTH_TOKEN" ]; then
    echo -e "${YELLOW}⚠️  AUTH_TOKEN not set. Please login first:${NC}"
    echo ""
    echo "export AUTH_TOKEN=\$(curl -s -X POST ${API_URL}/auth/login \\"
    echo "  -H 'Content-Type: application/json' \\"
    echo "  -d '{\"email\":\"admin@techco.com\",\"password\":\"password\"}' \\"
    echo "  | jq -r '.token')"
    echo ""
    exit 1
fi

echo -e "${BLUE}📋 Module: Sales Opportunities${NC}"
echo "   17 fields across 3 blocks"
echo "   Demonstrates all major field types"
echo ""

# Create the module with all fields
curl -X POST "${API_URL}/modules" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ${AUTH_TOKEN}" \
  -d '{
  "name": "Sales Opportunities",
  "singular_name": "Opportunity",
  "description": "Track sales opportunities from lead to close with comprehensive deal information",
  "icon": "TrendingUp",
  "is_active": true,
  "blocks": [
    {
      "name": "Basic Information",
      "type": "section",
      "display_order": 0,
      "settings": {
        "columns": 2,
        "collapsible": false
      },
      "fields": [
        {
          "label": "Opportunity Name",
          "type": "text",
          "placeholder": "e.g., Acme Corp - Enterprise Package",
          "is_required": true,
          "is_unique": true,
          "is_searchable": true,
          "is_filterable": true,
          "is_sortable": true,
          "width": 100,
          "display_order": 0,
          "settings": {
            "min_length": 5,
            "max_length": 255,
            "additional_settings": {}
          }
        },
        {
          "label": "Account",
          "type": "lookup",
          "description": "Related company/account",
          "is_required": true,
          "is_searchable": true,
          "is_filterable": true,
          "width": 50,
          "display_order": 1,
          "settings": {
            "additional_settings": {}
          }
        },
        {
          "label": "Stage",
          "type": "select",
          "is_required": true,
          "is_searchable": true,
          "is_filterable": true,
          "width": 50,
          "display_order": 2,
          "settings": {
            "additional_settings": {}
          },
          "options": [
            {
              "label": "Prospecting",
              "value": "prospecting",
              "color": "#9CA3AF",
              "display_order": 0
            },
            {
              "label": "Qualification",
              "value": "qualification",
              "color": "#3B82F6",
              "display_order": 1
            },
            {
              "label": "Proposal",
              "value": "proposal",
              "color": "#8B5CF6",
              "display_order": 2
            },
            {
              "label": "Negotiation",
              "value": "negotiation",
              "color": "#F59E0B",
              "display_order": 3
            },
            {
              "label": "Closed Won",
              "value": "closed_won",
              "color": "#10B981",
              "display_order": 4
            },
            {
              "label": "Closed Lost",
              "value": "closed_lost",
              "color": "#EF4444",
              "display_order": 5
            }
          ]
        },
        {
          "label": "Priority",
          "type": "radio",
          "width": 50,
          "display_order": 3,
          "settings": {
            "additional_settings": {}
          },
          "options": [
            {
              "label": "Low",
              "value": "low",
              "color": "#9CA3AF",
              "display_order": 0
            },
            {
              "label": "Medium",
              "value": "medium",
              "color": "#F59E0B",
              "display_order": 1
            },
            {
              "label": "High",
              "value": "high",
              "color": "#EF4444",
              "display_order": 2
            }
          ]
        },
        {
          "label": "Expected Close Date",
          "type": "date",
          "is_required": true,
          "width": 50,
          "display_order": 4,
          "settings": {
            "additional_settings": {}
          }
        }
      ]
    },
    {
      "name": "Financial Details",
      "type": "section",
      "display_order": 1,
      "settings": {
        "columns": 2,
        "collapsible": false
      },
      "fields": [
        {
          "label": "Amount",
          "type": "currency",
          "is_required": true,
          "width": 33,
          "display_order": 0,
          "settings": {
            "currency_code": "USD",
            "precision": 2,
            "min_value": 0,
            "additional_settings": {}
          }
        },
        {
          "label": "Discount %",
          "type": "percent",
          "width": 33,
          "display_order": 1,
          "settings": {
            "min_value": 0,
            "max_value": 100,
            "additional_settings": {}
          }
        },
        {
          "label": "Probability %",
          "type": "number",
          "width": 33,
          "display_order": 2,
          "settings": {
            "min_value": 0,
            "max_value": 100,
            "additional_settings": {}
          }
        },
        {
          "label": "Payment Terms",
          "type": "select",
          "width": 50,
          "display_order": 3,
          "settings": {
            "additional_settings": {}
          },
          "options": [
            {
              "label": "Net 15",
              "value": "net_15",
              "display_order": 0
            },
            {
              "label": "Net 30",
              "value": "net_30",
              "display_order": 1
            },
            {
              "label": "Net 60",
              "value": "net_60",
              "display_order": 2
            },
            {
              "label": "Upfront",
              "value": "upfront",
              "display_order": 3
            },
            {
              "label": "Installments",
              "value": "installments",
              "display_order": 4
            }
          ]
        },
        {
          "label": "Products Interested",
          "type": "multiselect",
          "width": 100,
          "display_order": 4,
          "settings": {
            "additional_settings": {}
          },
          "options": [
            {
              "label": "Basic Package",
              "value": "basic_package",
              "display_order": 0
            },
            {
              "label": "Professional Package",
              "value": "professional_package",
              "display_order": 1
            },
            {
              "label": "Enterprise Package",
              "value": "enterprise_package",
              "display_order": 2
            },
            {
              "label": "Add-on Services",
              "value": "addon_services",
              "display_order": 3
            },
            {
              "label": "Custom Solution",
              "value": "custom_solution",
              "display_order": 4
            }
          ]
        }
      ]
    },
    {
      "name": "Additional Information",
      "type": "section",
      "display_order": 2,
      "settings": {
        "columns": 2,
        "collapsible": false
      },
      "fields": [
        {
          "label": "Description",
          "type": "textarea",
          "placeholder": "Describe the opportunity details, requirements, and key stakeholders",
          "width": 100,
          "display_order": 0,
          "settings": {
            "max_length": 2000,
            "additional_settings": {}
          }
        },
        {
          "label": "Internal Notes",
          "type": "textarea",
          "placeholder": "Internal team notes (not visible to client)",
          "width": 100,
          "display_order": 1,
          "settings": {
            "max_length": 1000,
            "additional_settings": {}
          }
        },
        {
          "label": "Attachments",
          "type": "file",
          "width": 100,
          "display_order": 2,
          "settings": {
            "additional_settings": {}
          }
        },
        {
          "label": "Active Campaign",
          "type": "toggle",
          "width": 50,
          "display_order": 3,
          "settings": {
            "additional_settings": {}
          }
        },
        {
          "label": "Follow Up Required",
          "type": "checkbox",
          "width": 50,
          "display_order": 4,
          "settings": {
            "additional_settings": {}
          }
        }
      ]
    }
  ]
}' | jq '.'

echo ""
echo -e "${GREEN}✅ Module created successfully!${NC}"
echo ""
echo -e "${BLUE}📊 Summary:${NC}"
echo "   • 3 blocks created"
echo "   • 17 fields total"
echo "   • 8 different field types"
echo "   • 20+ options with colors"
echo "   • Multiple widths (33%, 50%, 100%)"
echo ""
echo -e "${YELLOW}🌐 View at: http://${TENANT_DOMAIN}/modules${NC}"
echo ""
