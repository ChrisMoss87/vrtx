# 🎬 Demo Module Creation Guide

## Creating a "Sales Opportunities" Module

This demo will showcase all form builder features by creating a realistic CRM module.

### Step-by-Step Demo

#### 1. Navigate to Form Builder
```
http://techco.vrtx.local/modules/create-builder
```

#### 2. Fill Module Information
- **Module Name:** `Sales Opportunities`
- **Singular Name:** `Opportunity`
- **Description:** `Track sales opportunities from lead to close with comprehensive deal information`
- **Icon:** `TrendingUp`

#### 3. Create Block 1: "Basic Information"

**Add these fields by dragging from palette:**

1. **Opportunity Name** (Text)
   - Width: 100%
   - Required: ✓
   - Unique: ✓
   - Placeholder: "e.g., Acme Corp - Enterprise Package"
   - Min Length: 5
   - Max Length: 255

2. **Account** (Lookup)
   - Width: 50%
   - Required: ✓
   - Description: "Related company/account"

3. **Stage** (Select)
   - Width: 50%
   - Required: ✓
   - Options:
     * Prospecting (Gray #9CA3AF)
     * Qualification (Blue #3B82F6)
     * Proposal (Purple #8B5CF6)
     * Negotiation (Yellow #F59E0B)
     * Closed Won (Green #10B981)
     * Closed Lost (Red #EF4444)

4. **Priority** (Radio)
   - Width: 50%
   - Options:
     * Low (Gray)
     * Medium (Yellow)
     * High (Red)

5. **Expected Close Date** (Date)
   - Width: 50%
   - Required: ✓

#### 4. Create Block 2: "Financial Details"

1. **Amount** (Currency)
   - Width: 33%
   - Required: ✓
   - Currency Code: USD
   - Precision: 2
   - Min Value: 0

2. **Discount %** (Percent)
   - Width: 33%
   - Min Value: 0
   - Max Value: 100

3. **Probability %** (Number)
   - Width: 33%
   - Min Value: 0
   - Max Value: 100

4. **Payment Terms** (Select)
   - Width: 50%
   - Options:
     * Net 15
     * Net 30
     * Net 60
     * Upfront
     * Installments

5. **Products Interested** (Multi Select)
   - Width: 100%
   - Options:
     * Basic Package
     * Professional Package
     * Enterprise Package
     * Add-on Services
     * Custom Solution

#### 5. Create Block 3: "Additional Information"

1. **Description** (Textarea)
   - Width: 100%
   - Max Length: 2000
   - Placeholder: "Describe the opportunity details, requirements, and key stakeholders"

2. **Internal Notes** (Textarea)
   - Width: 100%
   - Max Length: 1000
   - Placeholder: "Internal team notes (not visible to client)"

3. **Attachments** (File)
   - Width: 100%

4. **Active Campaign** (Toggle)
   - Width: 50%

5. **Follow Up Required** (Checkbox)
   - Width: 50%

#### 6. Test Drag-to-Reorder
- Drag "Stage" field before "Account" using grip handle
- Drag "Priority" from Block 1 to Block 2
- Watch display orders update automatically

#### 7. Save Module
- Click "Create Module" button
- Module will be created with all configurations
- Redirects to modules list

---

## Expected Result

The module will be created with:
- ✅ 3 blocks
- ✅ 17 fields total
- ✅ 8 different field types demonstrated
- ✅ 4 fields with options (20+ options total)
- ✅ Multiple widths (33%, 50%, 100%)
- ✅ Various validation rules
- ✅ Color-coded select options

---

## Testing Each Feature

### Feature 1: Field Palette
- ✅ See all 21 field types in grid
- ✅ Use category tabs to filter
- ✅ Search for "text" to filter
- ✅ Drag any field to canvas

### Feature 2: Options Editor
- ✅ Click on "Stage" field
- ✅ See options editor appear
- ✅ Click color swatches to assign colors
- ✅ Add new option
- ✅ Watch value auto-generate from label

### Feature 3: Drag-to-Reorder
- ✅ Grab any field's grip handle (⋮⋮)
- ✅ Drag within block to reorder
- ✅ Drag to different block
- ✅ See field opacity change during drag
- ✅ Drop to place

### Feature 4: Field Configuration
- ✅ Click "Amount" currency field
- ✅ See currency-specific settings
- ✅ Change precision, currency code
- ✅ Click "Opportunity Name" text field
- ✅ See text-specific settings (min/max length)

---

## Quick Test Script

For automated testing, here's what to verify:

```bash
# 1. Page loads
curl -I http://techco.vrtx.local/modules/create-builder
# Expected: HTTP 200

# 2. After creating module, verify it exists
curl -H "Authorization: Bearer YOUR_TOKEN" \
  http://techco.vrtx.local/api/v1/modules | jq '.modules[] | select(.name == "Sales Opportunities")'
# Expected: Module JSON with all fields and blocks
```

---

## Visual Demo Checklist

When demonstrating, show:

1. ✅ **Palette Navigation**
   - Switch between category tabs
   - Search functionality
   - Field type descriptions

2. ✅ **Drag from Palette**
   - Grab field card
   - Drag to block drop zone
   - Release to add
   - Field auto-opens config panel

3. ✅ **Configure Field**
   - Change label in real-time
   - Toggle required checkbox
   - Change width (see field resize)
   - For select: add options with colors

4. ✅ **Reorder Fields**
   - Show grip handle
   - Drag to reorder
   - Drag between blocks
   - Show it updates display_order

5. ✅ **Create Module**
   - Fill module info
   - Show validation (try submitting without name)
   - Successful creation
   - Redirect to list

---

## Advanced Demo (Optional)

If implementing the advanced features, add these fields:

### Conditional Field Example:
```json
{
  "label": "Installment Details",
  "type": "textarea",
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
*This field only shows when Payment Terms = "Installments"*

### Formula Field Example:
```json
{
  "label": "Expected Revenue",
  "type": "formula",
  "formula_definition": {
    "formula": "amount * (probability / 100)",
    "formula_type": "calculation",
    "return_type": "currency",
    "dependencies": ["amount", "probability"]
  }
}
```
*Auto-calculates: Amount × Probability*

### Lookup Field Example:
```json
{
  "label": "Account",
  "type": "lookup",
  "settings": {
    "related_module_id": 1,
    "related_module_name": "accounts",
    "display_field": "company_name",
    "search_fields": ["company_name", "email"],
    "allow_create": true
  }
}
```
*Links to Accounts module with searchable dropdown*

---

## 📸 Screenshots Checklist

Capture these moments:
1. Empty canvas with "Create First Block" button
2. Field palette with all 21 types visible
3. Dragging a field from palette (mid-drag)
4. Block with multiple fields at different widths
5. Field configuration panel open (right side)
6. Options editor with colors
7. Mid-drag reordering (showing grip handle)
8. Completed module with 3 blocks

---

## 🎯 Success Criteria

The demo is successful if:
- ✅ Module creates without errors
- ✅ All 17 fields save correctly
- ✅ Options include colors
- ✅ Field widths display as configured
- ✅ Required fields marked
- ✅ Module appears in list
- ✅ Can view module structure in API

---

Ready to create the demo module! 🚀
