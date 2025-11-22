# Dynamic Form Builder - Complete Specification

## Table of Contents
1. [Overview](#overview)
2. [Sample Module JSON](#sample-module-json)
3. [Field Types & Options](#field-types--options)
4. [Conditional Visibility](#conditional-visibility)
5. [Calculated Fields](#calculated-fields)
6. [Lookup/Relationship Fields](#lookuprelationship-fields)
7. [Field Dependencies](#field-dependencies)
8. [Implementation Plan](#implementation-plan)

---

## Overview

The Dynamic Form Builder is a comprehensive visual tool for creating custom modules with advanced field configurations, conditional logic, calculations, and relationships.

### Key Features
- ✅ 21 field types (text, number, select, lookup, formula, etc.)
- ✅ Drag-and-drop interface
- ✅ Conditional field visibility
- ✅ Calculated/formula fields
- ✅ Lookup/relationship fields
- ✅ Field dependencies (cascading dropdowns)
- ✅ Comprehensive validation
- ✅ Flexible layouts (widths, blocks, tabs)
- ✅ Real-time preview

---

## Sample Module JSON

This is a complete example showcasing ALL features:

```json
{
  "module": {
    "name": "Sales Opportunities",
    "singular_name": "Opportunity",
    "api_name": "sales_opportunities",
    "icon": "TrendingUp",
    "description": "Track sales opportunities from lead to close",
    "is_active": true,
    "display_order": 1,
    "settings": {
      "has_import": true,
      "has_export": true,
      "has_mass_actions": true,
      "has_comments": true,
      "has_attachments": true,
      "has_activity_log": true,
      "has_custom_views": true,
      "record_name_field": "opportunity_name",
      "additional_settings": {
        "enable_kanban_view": true,
        "kanban_field": "stage",
        "enable_timeline": true,
        "auto_number_prefix": "OPP-",
        "default_sort": "created_at DESC"
      }
    },
    "blocks": [
      {
        "id": null,
        "name": "Basic Information",
        "type": "section",
        "display_order": 1,
        "settings": {
          "collapsible": false,
          "default_collapsed": false,
          "columns": 2,
          "conditional_visibility": null
        },
        "fields": [
          {
            "label": "Opportunity Name",
            "api_name": "opportunity_name",
            "type": "text",
            "description": "Internal name for this opportunity",
            "help_text": "Use a descriptive name that includes company and product",
            "is_required": true,
            "is_unique": true,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 1,
            "width": 100,
            "validation_rules": {
              "rules": ["required", "string", "max:255", "unique:opportunities,opportunity_name"]
            },
            "settings": {
              "min_length": 5,
              "max_length": 255,
              "placeholder": "e.g., Acme Corp - Enterprise Package",
              "conditional_visibility": null,
              "calculate_on_change": null,
              "additional_settings": {
                "auto_capitalize": true,
                "trim_whitespace": true
              }
            }
          },
          {
            "label": "Account",
            "api_name": "account_id",
            "type": "lookup",
            "description": "Link to the related company/account",
            "help_text": "Select or create a new account",
            "is_required": true,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 2,
            "width": 50,
            "validation_rules": {
              "rules": ["required", "integer", "exists:accounts,id"]
            },
            "settings": {
              "related_module_id": 1,
              "related_module_name": "accounts",
              "display_field": "company_name",
              "search_fields": ["company_name", "email", "phone"],
              "allow_create": true,
              "cascade_delete": false,
              "relationship_type": "many_to_one",
              "additional_settings": {
                "quick_create_fields": ["company_name", "email", "phone"],
                "show_recent": true,
                "recent_limit": 10
              }
            }
          },
          {
            "label": "Contact",
            "api_name": "contact_id",
            "type": "lookup",
            "description": "Primary contact for this opportunity",
            "help_text": "Must belong to selected account",
            "is_required": true,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 3,
            "width": 50,
            "validation_rules": {
              "rules": ["required", "integer", "exists:contacts,id"]
            },
            "settings": {
              "related_module_id": 2,
              "related_module_name": "contacts",
              "display_field": "full_name",
              "search_fields": ["first_name", "last_name", "email"],
              "allow_create": true,
              "cascade_delete": false,
              "relationship_type": "many_to_one",
              "depends_on": "account_id",
              "dependency_filter": {
                "field": "account_id",
                "operator": "equals",
                "target_field": "account_id"
              },
              "additional_settings": {
                "filter_message": "Only contacts from selected account shown"
              }
            }
          },
          {
            "label": "Stage",
            "api_name": "stage",
            "type": "select",
            "description": "Current stage in the sales pipeline",
            "help_text": "Update as the deal progresses",
            "is_required": true,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": "prospecting",
            "display_order": 4,
            "width": 50,
            "validation_rules": {
              "rules": ["required", "in:prospecting,qualification,proposal,negotiation,closed_won,closed_lost"]
            },
            "settings": {
              "additional_settings": {
                "color_coding": true,
                "stage_colors": {
                  "prospecting": "#9CA3AF",
                  "qualification": "#3B82F6",
                  "proposal": "#8B5CF6",
                  "negotiation": "#F59E0B",
                  "closed_won": "#10B981",
                  "closed_lost": "#EF4444"
                }
              }
            },
            "options": [
              {
                "label": "Prospecting",
                "value": "prospecting",
                "display_order": 1,
                "is_active": true,
                "metadata": {"probability": 10}
              },
              {
                "label": "Qualification",
                "value": "qualification",
                "display_order": 2,
                "is_active": true,
                "metadata": {"probability": 25}
              },
              {
                "label": "Proposal",
                "value": "proposal",
                "display_order": 3,
                "is_active": true,
                "metadata": {"probability": 50}
              },
              {
                "label": "Negotiation",
                "value": "negotiation",
                "display_order": 4,
                "is_active": true,
                "metadata": {"probability": 75}
              },
              {
                "label": "Closed Won",
                "value": "closed_won",
                "display_order": 5,
                "is_active": true,
                "metadata": {"probability": 100}
              },
              {
                "label": "Closed Lost",
                "value": "closed_lost",
                "display_order": 6,
                "is_active": true,
                "metadata": {"probability": 0}
              }
            ]
          },
          {
            "label": "Probability (%)",
            "api_name": "probability",
            "type": "formula",
            "description": "Auto-calculated based on stage",
            "help_text": "Automatically updated when stage changes",
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 5,
            "width": 50,
            "validation_rules": {
              "rules": []
            },
            "settings": {
              "formula": "LOOKUP(stage, 'options.metadata.probability', 10)",
              "formula_type": "lookup",
              "return_type": "number",
              "dependencies": ["stage"],
              "recalculate_on": ["stage"],
              "additional_settings": {
                "display_as_percent": true,
                "editable": false
              }
            }
          }
        ]
      },
      {
        "id": null,
        "name": "Financial Details",
        "type": "section",
        "display_order": 2,
        "settings": {
          "collapsible": true,
          "default_collapsed": false,
          "columns": 2,
          "conditional_visibility": null
        },
        "fields": [
          {
            "label": "Amount",
            "api_name": "amount",
            "type": "currency",
            "description": "Total opportunity value",
            "help_text": "Enter the expected deal value",
            "is_required": true,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 1,
            "width": 33,
            "validation_rules": {
              "rules": ["required", "numeric", "min:0", "max:999999999.99"]
            },
            "settings": {
              "min_value": 0,
              "max_value": 999999999.99,
              "precision": 2,
              "currency_code": "USD",
              "additional_settings": {
                "allow_negative": false,
                "thousand_separator": true,
                "currency_position": "before"
              }
            }
          },
          {
            "label": "Discount (%)",
            "api_name": "discount_percent",
            "type": "percent",
            "description": "Discount percentage applied",
            "help_text": "Enter as whole number (e.g., 15 for 15%)",
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": "0",
            "display_order": 2,
            "width": 33,
            "validation_rules": {
              "rules": ["numeric", "min:0", "max:100"]
            },
            "settings": {
              "min_value": 0,
              "max_value": 100,
              "precision": 2,
              "additional_settings": {
                "show_slider": true,
                "slider_step": 5
              }
            }
          },
          {
            "label": "Final Amount",
            "api_name": "final_amount",
            "type": "formula",
            "description": "Amount after discount",
            "help_text": "Automatically calculated",
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 3,
            "width": 33,
            "validation_rules": {
              "rules": []
            },
            "settings": {
              "formula": "amount - (amount * (discount_percent / 100))",
              "formula_type": "calculation",
              "return_type": "currency",
              "dependencies": ["amount", "discount_percent"],
              "recalculate_on": ["amount", "discount_percent"],
              "additional_settings": {
                "currency_code": "USD",
                "editable": false
              }
            }
          },
          {
            "label": "Expected Revenue",
            "api_name": "expected_revenue",
            "type": "formula",
            "description": "Weighted revenue based on probability",
            "help_text": "Final Amount × Probability",
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 4,
            "width": 50,
            "validation_rules": {
              "rules": []
            },
            "settings": {
              "formula": "final_amount * (probability / 100)",
              "formula_type": "calculation",
              "return_type": "currency",
              "dependencies": ["final_amount", "probability"],
              "recalculate_on": ["final_amount", "probability"],
              "additional_settings": {
                "currency_code": "USD",
                "editable": false,
                "highlight_color": "#10B981"
              }
            }
          },
          {
            "label": "Payment Terms",
            "api_name": "payment_terms",
            "type": "select",
            "description": "Payment structure for this deal",
            "help_text": null,
            "is_required": false,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": "net_30",
            "display_order": 5,
            "width": 50,
            "validation_rules": {
              "rules": ["in:net_15,net_30,net_60,net_90,upfront,installments"]
            },
            "settings": {
              "additional_settings": {}
            },
            "options": [
              {"label": "Net 15", "value": "net_15", "display_order": 1, "is_active": true},
              {"label": "Net 30", "value": "net_30", "display_order": 2, "is_active": true},
              {"label": "Net 60", "value": "net_60", "display_order": 3, "is_active": true},
              {"label": "Net 90", "value": "net_90", "display_order": 4, "is_active": true},
              {"label": "Upfront", "value": "upfront", "display_order": 5, "is_active": true},
              {"label": "Installments", "value": "installments", "display_order": 6, "is_active": true}
            ]
          },
          {
            "label": "Installment Plan",
            "api_name": "installment_plan",
            "type": "textarea",
            "description": "Details of payment installments",
            "help_text": "Describe the installment schedule",
            "is_required": false,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": false,
            "is_sortable": false,
            "default_value": null,
            "display_order": 6,
            "width": 100,
            "validation_rules": {
              "rules": ["string", "max:1000"]
            },
            "settings": {
              "max_length": 1000,
              "conditional_visibility": {
                "enabled": true,
                "operator": "and",
                "conditions": [
                  {
                    "field": "payment_terms",
                    "operator": "equals",
                    "value": "installments"
                  }
                ]
              },
              "additional_settings": {
                "rows": 4
              }
            }
          }
        ]
      },
      {
        "id": null,
        "name": "Timeline",
        "type": "section",
        "display_order": 3,
        "settings": {
          "collapsible": true,
          "default_collapsed": false,
          "columns": 2
        },
        "fields": [
          {
            "label": "Close Date",
            "api_name": "close_date",
            "type": "date",
            "description": "Expected close date",
            "help_text": "When do you expect to close this deal?",
            "is_required": true,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 1,
            "width": 50,
            "validation_rules": {
              "rules": ["required", "date", "after:today"]
            },
            "settings": {
              "additional_settings": {
                "min_date": "today",
                "max_date": "+2 years",
                "format": "Y-m-d",
                "show_calendar": true
              }
            }
          },
          {
            "label": "Days to Close",
            "api_name": "days_to_close",
            "type": "formula",
            "description": "Days remaining until close date",
            "help_text": "Auto-calculated",
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 2,
            "width": 50,
            "validation_rules": {
              "rules": []
            },
            "settings": {
              "formula": "DAYS_BETWEEN(TODAY(), close_date)",
              "formula_type": "date_calculation",
              "return_type": "number",
              "dependencies": ["close_date"],
              "recalculate_on": ["close_date"],
              "additional_settings": {
                "editable": false,
                "color_coding": {
                  "ranges": [
                    {"max": 7, "color": "#EF4444", "label": "Urgent"},
                    {"min": 8, "max": 30, "color": "#F59E0B", "label": "Soon"},
                    {"min": 31, "color": "#10B981", "label": "On Track"}
                  ]
                }
              }
            }
          },
          {
            "label": "Last Contact Date",
            "api_name": "last_contact_date",
            "type": "date",
            "description": "Last time we contacted the prospect",
            "help_text": null,
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 3,
            "width": 50,
            "validation_rules": {
              "rules": ["date", "before_or_equal:today"]
            },
            "settings": {
              "additional_settings": {
                "max_date": "today"
              }
            }
          },
          {
            "label": "Next Follow-up",
            "api_name": "next_followup_date",
            "type": "date",
            "description": "Scheduled next follow-up",
            "help_text": null,
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 4,
            "width": 50,
            "validation_rules": {
              "rules": ["date", "after_or_equal:today"]
            },
            "settings": {
              "additional_settings": {
                "min_date": "today",
                "create_task": true
              }
            }
          }
        ]
      },
      {
        "id": null,
        "name": "Additional Information",
        "type": "tab",
        "display_order": 4,
        "settings": {
          "collapsible": false,
          "columns": 1
        },
        "fields": [
          {
            "label": "Description",
            "api_name": "description",
            "type": "rich_text",
            "description": "Detailed opportunity description",
            "help_text": "Include key details, stakeholders, and requirements",
            "is_required": false,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": false,
            "is_sortable": false,
            "default_value": null,
            "display_order": 1,
            "width": 100,
            "validation_rules": {
              "rules": ["string", "max:10000"]
            },
            "settings": {
              "max_length": 10000,
              "additional_settings": {
                "toolbar": ["bold", "italic", "underline", "strike", "link", "list", "code"],
                "min_height": 200
              }
            }
          },
          {
            "label": "Attachments",
            "api_name": "attachments",
            "type": "file",
            "description": "Upload relevant documents",
            "help_text": "Contracts, proposals, etc.",
            "is_required": false,
            "is_unique": false,
            "is_searchable": false,
            "is_filterable": false,
            "is_sortable": false,
            "default_value": null,
            "display_order": 2,
            "width": 100,
            "validation_rules": {
              "rules": ["array", "max:5"]
            },
            "settings": {
              "allowed_file_types": ["pdf", "doc", "docx", "xls", "xlsx", "txt"],
              "max_file_size": 10240,
              "additional_settings": {
                "multiple": true,
                "max_files": 5,
                "show_preview": true
              }
            }
          },
          {
            "label": "Competitors",
            "api_name": "competitors",
            "type": "multiselect",
            "description": "Known competitors for this deal",
            "help_text": null,
            "is_required": false,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": false,
            "default_value": null,
            "display_order": 3,
            "width": 50,
            "validation_rules": {
              "rules": ["array"]
            },
            "settings": {
              "additional_settings": {
                "allow_custom": true,
                "max_selections": 5
              }
            },
            "options": [
              {"label": "Salesforce", "value": "salesforce", "display_order": 1, "is_active": true},
              {"label": "HubSpot", "value": "hubspot", "display_order": 2, "is_active": true},
              {"label": "Pipedrive", "value": "pipedrive", "display_order": 3, "is_active": true},
              {"label": "Zoho", "value": "zoho", "display_order": 4, "is_active": true},
              {"label": "Monday.com", "value": "monday", "display_order": 5, "is_active": true}
            ]
          },
          {
            "label": "Source",
            "api_name": "source",
            "type": "radio",
            "description": "How did we learn about this opportunity?",
            "help_text": null,
            "is_required": false,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": "website",
            "display_order": 4,
            "width": 50,
            "validation_rules": {
              "rules": ["in:website,referral,cold_call,event,social_media,partner"]
            },
            "settings": {
              "additional_settings": {
                "layout": "horizontal"
              }
            },
            "options": [
              {"label": "Website", "value": "website", "display_order": 1, "is_active": true},
              {"label": "Referral", "value": "referral", "display_order": 2, "is_active": true},
              {"label": "Cold Call", "value": "cold_call", "display_order": 3, "is_active": true},
              {"label": "Event", "value": "event", "display_order": 4, "is_active": true},
              {"label": "Social Media", "value": "social_media", "display_order": 5, "is_active": true},
              {"label": "Partner", "value": "partner", "display_order": 6, "is_active": true}
            ]
          },
          {
            "label": "Referral Source",
            "api_name": "referral_source",
            "type": "text",
            "description": "Who referred this opportunity?",
            "help_text": "Name and contact info",
            "is_required": false,
            "is_unique": false,
            "is_searchable": true,
            "is_filterable": true,
            "is_sortable": true,
            "default_value": null,
            "display_order": 5,
            "width": 100,
            "validation_rules": {
              "rules": ["string", "max:255"]
            },
            "settings": {
              "max_length": 255,
              "conditional_visibility": {
                "enabled": true,
                "operator": "and",
                "conditions": [
                  {
                    "field": "source",
                    "operator": "equals",
                    "value": "referral"
                  }
                ]
              },
              "additional_settings": {}
            }
          }
        ]
      }
    ]
  }
}
```

---

## Field Types & Options

### Complete Field Type Matrix

| Field Type | Requires Options | Numeric | Relationship | Calculated | Key Settings |
|------------|-----------------|---------|--------------|------------|--------------|
| text | No | No | No | No | min/max length, pattern, placeholder |
| textarea | No | No | No | No | min/max length, rows |
| number | No | Yes | No | No | min/max value, step |
| decimal | No | Yes | No | No | precision, min/max value |
| email | No | No | No | No | pattern, allow multiple |
| phone | No | No | No | No | format, country code |
| url | No | No | No | No | pattern, allow protocols |
| select | Yes | No | No | No | options, allow custom |
| multiselect | Yes | No | No | No | options, max selections |
| radio | Yes | No | No | No | options, layout |
| checkbox | No | No | No | No | default checked |
| toggle | No | No | No | No | on/off labels |
| date | No | No | No | No | min/max date, format |
| datetime | No | No | No | No | min/max datetime, timezone |
| time | No | No | No | No | format, step |
| currency | No | Yes | No | No | currency code, precision |
| percent | No | Yes | No | No | min/max, show slider |
| lookup | No | No | Yes | No | related module, display field |
| formula | No | Depends | No | Yes | formula, dependencies |
| file | No | No | No | No | allowed types, max size |
| image | No | No | No | No | allowed formats, dimensions |
| rich_text | No | No | No | No | toolbar, max length |

### Universal Field Options

Every field supports:
- `label` - Display name
- `api_name` - Database/API identifier (auto-generated from label)
- `description` - Short description
- `help_text` - Contextual help
- `is_required` - Validation requirement
- `is_unique` - Enforce uniqueness
- `is_searchable` - Include in search
- `is_filterable` - Allow filtering
- `is_sortable` - Allow sorting
- `default_value` - Default value
- `display_order` - Order within block
- `width` - 25, 33, 50, 100 (percentage)
- `validation_rules` - Laravel validation rules
- `conditional_visibility` - Show/hide based on conditions

---

## Conditional Visibility

### Structure

```typescript
interface ConditionalVisibility {
  enabled: boolean;
  operator: 'and' | 'or';
  conditions: Condition[];
}

interface Condition {
  field: string;           // Field API name to check
  operator: ConditionOperator;
  value?: any;             // Value to compare against
  field_value?: string;    // Compare against another field
}

type ConditionOperator =
  | 'equals'
  | 'not_equals'
  | 'contains'
  | 'not_contains'
  | 'starts_with'
  | 'ends_with'
  | 'greater_than'
  | 'less_than'
  | 'greater_than_or_equal'
  | 'less_than_or_equal'
  | 'between'
  | 'in'
  | 'not_in'
  | 'is_empty'
  | 'is_not_empty'
  | 'is_checked'
  | 'is_not_checked';
```

### Examples

**Simple condition:**
```json
{
  "conditional_visibility": {
    "enabled": true,
    "operator": "and",
    "conditions": [
      {
        "field": "payment_terms",
        "operator": "equals",
        "value": "installments"
      }
    ]
  }
}
```

**Complex condition (multiple):**
```json
{
  "conditional_visibility": {
    "enabled": true,
    "operator": "or",
    "conditions": [
      {
        "field": "stage",
        "operator": "in",
        "value": ["proposal", "negotiation"]
      },
      {
        "field": "amount",
        "operator": "greater_than",
        "value": 100000
      }
    ]
  }
}
```

**Field comparison:**
```json
{
  "conditional_visibility": {
    "enabled": true,
    "operator": "and",
    "conditions": [
      {
        "field": "close_date",
        "operator": "greater_than",
        "field_value": "start_date"
      }
    ]
  }
}
```

---

## Calculated Fields

### Formula Types

1. **calculation** - Mathematical operations
2. **lookup** - Retrieve value from option metadata
3. **date_calculation** - Date arithmetic
4. **text_manipulation** - String operations
5. **conditional** - If/then logic

### Supported Functions

**Mathematical:**
- `SUM(field1, field2, ...)`
- `SUBTRACT(a, b)`
- `MULTIPLY(a, b)`
- `DIVIDE(a, b)`
- `ROUND(value, decimals)`
- `CEILING(value)`
- `FLOOR(value)`
- `ABS(value)`
- `MIN(field1, field2, ...)`
- `MAX(field1, field2, ...)`
- `AVERAGE(field1, field2, ...)`

**Date/Time:**
- `TODAY()`
- `NOW()`
- `DAYS_BETWEEN(date1, date2)`
- `MONTHS_BETWEEN(date1, date2)`
- `YEARS_BETWEEN(date1, date2)`
- `ADD_DAYS(date, days)`
- `ADD_MONTHS(date, months)`
- `ADD_YEARS(date, years)`
- `FORMAT_DATE(date, format)`

**Text:**
- `CONCAT(str1, str2, ...)`
- `UPPER(text)`
- `LOWER(text)`
- `TRIM(text)`
- `LEFT(text, length)`
- `RIGHT(text, length)`
- `SUBSTRING(text, start, length)`
- `REPLACE(text, find, replace)`

**Logical:**
- `IF(condition, true_value, false_value)`
- `AND(condition1, condition2, ...)`
- `OR(condition1, condition2, ...)`
- `NOT(condition)`
- `IS_BLANK(field)`
- `IS_NUMBER(field)`

**Lookup:**
- `LOOKUP(field, path, default)` - Get value from option metadata

### Examples

**Simple calculation:**
```json
{
  "formula": "amount * 0.15",
  "formula_type": "calculation",
  "return_type": "currency",
  "dependencies": ["amount"],
  "recalculate_on": ["amount"]
}
```

**Complex calculation:**
```json
{
  "formula": "IF(stage = 'closed_won', final_amount, final_amount * (probability / 100))",
  "formula_type": "calculation",
  "return_type": "currency",
  "dependencies": ["stage", "final_amount", "probability"],
  "recalculate_on": ["stage", "final_amount", "probability"]
}
```

**Lookup from options:**
```json
{
  "formula": "LOOKUP(stage, 'options.metadata.probability', 10)",
  "formula_type": "lookup",
  "return_type": "number",
  "dependencies": ["stage"],
  "recalculate_on": ["stage"]
}
```

**Date calculation:**
```json
{
  "formula": "DAYS_BETWEEN(TODAY(), close_date)",
  "formula_type": "date_calculation",
  "return_type": "number",
  "dependencies": ["close_date"],
  "recalculate_on": ["close_date"]
}
```

---

## Lookup/Relationship Fields

### Configuration

```typescript
interface LookupSettings {
  related_module_id: number;
  related_module_name: string;
  display_field: string;              // Field to show in dropdown
  search_fields: string[];            // Fields to search
  allow_create: boolean;              // Allow quick create
  cascade_delete: boolean;            // Delete record if parent deleted
  relationship_type: 'one_to_one' | 'many_to_one' | 'many_to_many';
  depends_on?: string;                // Parent field for filtering
  dependency_filter?: DependencyFilter;
  additional_settings: {
    quick_create_fields?: string[];   // Fields for quick create modal
    show_recent?: boolean;
    recent_limit?: number;
    filters?: FilterCondition[];      // Static filters
  };
}
```

### Field Dependencies (Cascading Dropdowns)

**Scenario:** Contact dropdown filtered by selected Account

```json
{
  "label": "Contact",
  "type": "lookup",
  "settings": {
    "related_module_id": 2,
    "related_module_name": "contacts",
    "depends_on": "account_id",
    "dependency_filter": {
      "field": "account_id",
      "operator": "equals",
      "target_field": "account_id"
    }
  }
}
```

**How it works:**
1. User selects Account (account_id = 5)
2. Contact field automatically filters to only show contacts where account_id = 5
3. Contact dropdown updates reactively when Account changes

### Quick Create

When `allow_create: true`:
- Show "+ Create New" option in dropdown
- Modal opens with `quick_create_fields`
- New record created and automatically selected
- Supports pre-filling fields from parent record

---

## Field Dependencies

### Types of Dependencies

1. **Filter Dependencies** - Filter options based on another field
2. **Value Dependencies** - Update value when another field changes
3. **Validation Dependencies** - Validation rules that depend on other fields
4. **Calculation Dependencies** - Recalculate when dependencies change

### Configuration

```typescript
interface FieldDependency {
  type: 'filter' | 'value' | 'validation' | 'calculation';
  depends_on: string[];
  action: DependencyAction;
}

interface FilterDependency extends FieldDependency {
  type: 'filter';
  filter_expression: string;
}

interface ValueDependency extends FieldDependency {
  type: 'value';
  value_expression: string;
}

interface CalculationDependency extends FieldDependency {
  type: 'calculation';
  formula: string;
}
```

---

## Implementation Plan

### Phase 1: Backend Schema Extensions (Priority 1)

**Files to modify:**
1. `backend/app/Domain/Modules/ValueObjects/FieldSettings.php`
   - Add `conditionalVisibility` property
   - Add `dependsOn` property
   - Add `calculateOnChange` property

2. Create new value objects:
   - `ConditionalVisibility.php`
   - `FieldDependency.php`
   - `FormulaDefinition.php`

3. Database migration:
   - Add `conditional_visibility` JSON column to `fields` table
   - Add `dependencies` JSON column to `fields` table
   - Add `formula_definition` JSON column to `fields` table

**Estimated time:** 2-3 hours

### Phase 2: Form Builder UI (Priority 1)

**Components to create:**

1. **Field Palette** (`src/lib/components/form-builder/FieldPalette.svelte`)
   - Grid of all 21 field types with icons
   - Search/filter by name or category
   - Drag source for @dnd-kit

2. **Form Canvas** (`src/lib/components/form-builder/FormCanvas.svelte`)
   - Drop zones for blocks and fields
   - Visual layout preview
   - Drag handles for reordering
   - Field width controls (25%, 33%, 50%, 100%)

3. **Field Config Panel** (`src/lib/components/form-builder/FieldConfigPanel.svelte`)
   - Right sidebar when field selected
   - Tabs: Basic, Validation, Display, Advanced
   - Type-specific options (e.g., lookup config, formula editor)

4. **Block Config Panel** (`src/lib/components/form-builder/BlockConfigPanel.svelte`)
   - Block type selection
   - Layout options (columns)
   - Conditional visibility

**Dependencies:**
```bash
pnpm add @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
```

**Estimated time:** 8-10 hours

### Phase 3: Advanced Features (Priority 2)

**Components to create:**

1. **Conditional Visibility Builder** (`ConditionalVisibilityBuilder.svelte`)
   - Visual rule builder (similar to Zapier filters)
   - Add/remove conditions
   - AND/OR operator selection
   - Field selector, operator dropdown, value input

2. **Formula Editor** (`FormulaEditor.svelte`)
   - Monaco editor integration
   - Syntax highlighting for formulas
   - Autocomplete for fields and functions
   - Live validation
   - Function reference panel

3. **Lookup Configurator** (`LookupConfigurator.svelte`)
   - Module selector
   - Display field selection
   - Search fields multi-select
   - Dependency mapping UI
   - Quick create field selection

4. **Dependency Mapper** (`DependencyMapper.svelte`)
   - Visual dependency graph
   - Show which fields depend on current field
   - Circular dependency detection

**Dependencies:**
```bash
pnpm add monaco-editor @monaco-editor/react
pnpm add formula-parser  # For formula validation
```

**Estimated time:** 6-8 hours

### Phase 4: Form Renderer (Priority 1)

**Components to create:**

1. **Dynamic Form** (`src/lib/components/dynamic-form/DynamicForm.svelte`)
   - Reads module JSON structure
   - Renders all blocks and fields
   - Handles form state
   - Validates on submit

2. **Field Renderer** (`FieldRenderer.svelte`)
   - Switch on field type
   - Render appropriate component
   - Handle conditional visibility
   - Trigger calculations on change

3. **Formula Calculator** (`formulaCalculator.ts`)
   - Parse formula AST
   - Evaluate expressions
   - Handle all supported functions
   - Error handling

4. **Lookup Field Component** (`LookupField.svelte`)
   - Searchable dropdown
   - Async data loading
   - Recent items
   - Quick create modal
   - Dependency filtering

**Estimated time:** 4-5 hours

### Phase 5: Testing & Polish (Priority 3)

1. **E2E Tests**
   - Create module with all field types
   - Test drag-and-drop
   - Test conditional visibility
   - Test formula calculations
   - Test lookup relationships

2. **Performance Optimization**
   - Lazy load Monaco editor
   - Virtualize field palette
   - Debounce formula calculations
   - Optimize re-renders

3. **Error Handling**
   - Validation error display
   - Circular dependency warnings
   - Formula syntax errors
   - Relationship integrity errors

4. **Documentation**
   - User guide for form builder
   - Formula function reference
   - Video tutorials

**Estimated time:** 3-4 hours

---

## Total Estimated Timeline

| Phase | Priority | Hours | Can Start |
|-------|----------|-------|-----------|
| Phase 1: Backend Schema | P1 | 2-3 | Immediately |
| Phase 2: Form Builder UI | P1 | 8-10 | After Phase 1 |
| Phase 3: Advanced Features | P2 | 6-8 | After Phase 2 |
| Phase 4: Form Renderer | P1 | 4-5 | After Phase 1 |
| Phase 5: Testing & Polish | P3 | 3-4 | After all phases |

**Total: 23-30 hours**

**Incremental Delivery:**
- After Phase 1+2: Basic form builder works (drag-drop, basic fields)
- After Phase 3: Advanced features available (formulas, conditions, lookups)
- After Phase 4: Can create and use forms
- After Phase 5: Production-ready

---

## Next Steps

1. **Approve this spec** - Confirm all features align with requirements
2. **Start with Phase 1** - Backend schema extensions
3. **Install dependencies** - @dnd-kit packages
4. **Build incrementally** - Each phase delivers value

---

**Questions? Ready to start?**
