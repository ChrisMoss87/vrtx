# Quotes & Invoices Improvements

## Research Summary

Based on analysis of Xero, Wave, HubSpot, Square, and other accounting software:

### Key UX Patterns to Implement

1. **Drag-and-Drop Line Item Reordering** - Essential for professional quotes
2. **Inline Editing** - Edit directly in the table without modals
3. **Product Search with Autocomplete** - Quick product lookup as you type
4. **Tax Rates as Presets** - Select from configured tax rates, not manual entry
5. **Discount Types per Line** - Fixed amount OR percentage per line item
6. **Tracking Categories** - Assign cost centers/categories to line items
7. **Unit of Measure** - Support qty units (hours, units, boxes, etc.)
8. **Multi-Currency** - Full multi-currency with exchange rates
9. **Attachments** - Attach documents to quotes/invoices
10. **Activity Timeline** - Show quote/invoice history (sent, viewed, etc.)

---

## Improved Line Item Component

### Line Item Fields (Xero-inspired)

```typescript
interface EnhancedLineItem {
  id: string;                    // UUID for drag-drop
  sort_order: number;            // For reordering
  item_type: 'product' | 'service' | 'expense' | 'text';
  product_id: number | null;
  sku: string | null;
  description: string;
  detailed_description: string | null;  // Rich text
  quantity: number;
  unit: string | null;           // 'hours', 'units', 'boxes', etc.
  unit_price: number;
  discount_type: 'none' | 'percent' | 'fixed';
  discount_value: number;
  discount_amount: number;       // Calculated
  tax_rate_id: number | null;    // Reference to tax rate preset
  tax_rate: number;
  tax_amount: number;            // Calculated
  line_total: number;            // Calculated
  account_id: number | null;     // For accounting integration
  tracking_category_id: number | null;
}
```

### Tax Rate Presets

```typescript
interface TaxRate {
  id: number;
  name: string;           // 'GST', 'VAT', 'Sales Tax', 'Tax Exempt'
  rate: number;           // 10.00, 20.00, 0.00
  is_default: boolean;
  is_compound: boolean;   // Tax on tax
  is_active: boolean;
  components?: TaxRateComponent[];  // For compound taxes
}
```

---

## UI Improvements Required

### 1. Line Items Table (New Design)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ ⋮⋮  │ Item / Description           │ Qty │ Unit │ Price   │ Tax │ Amount  │
├─────┼──────────────────────────────┼─────┼──────┼─────────┼─────┼─────────┤
│ ⋮⋮  │ [🔍 Search products...]      │     │      │         │     │         │
│     │  Logo Design Services        │ 4   │ hrs  │ $150.00 │ GST │ $600.00 │
│     │  Professional logo design    │     │      │ -10%    │     │ -$60.00 │
├─────┼──────────────────────────────┼─────┼──────┼─────────┼─────┼─────────┤
│ ⋮⋮  │ Website Development          │ 40  │ hrs  │ $175.00 │ GST │$7,000.00│
│     │  Full responsive website     │     │      │         │     │         │
├─────┼──────────────────────────────┼─────┼──────┼─────────┼─────┼─────────┤
│     │ [+ Add line item]            │     │      │         │     │         │
│     │ [+ Add text line]            │     │      │ Subtotal│     │$7,540.00│
│     │                              │     │      │ GST 10% │     │  $754.00│
│     │                              │     │      │ Total   │     │$8,294.00│
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2. Product Search Dropdown

- Typeahead search
- Shows: Name, SKU, Price, Stock
- Recently used items at top
- "Create new product" option

### 3. Inline Discount Editor

- Click on amount to toggle discount
- Pop-up for discount type selection
- Show original price struck through

### 4. Tax Rate Selector

- Dropdown of configured tax rates
- Shows rate % next to name
- "Tax Exempt" option
- "Mixed" for different line taxes

### 5. Keyboard Navigation

- Tab through cells
- Enter to add new line
- Delete/Backspace to remove empty line
- Arrow keys for navigation
- Escape to cancel editing

---

## Backend Changes Required

### New Models

```php
// app/Models/TaxRate.php
class TaxRate extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'rate',
        'is_compound',
        'is_default',
        'is_active',
        'effective_from',
        'region',
    ];
}

// app/Models/BillingSetting.php (enhanced)
class BillingSetting extends Model
{
    protected $fillable = [
        'default_currency',
        'default_tax_rate_id',
        'default_payment_terms',
        'quote_prefix',
        'quote_next_number',
        'invoice_prefix',
        'invoice_next_number',
        'company_details',     // JSON: name, address, tax_id, logo
        'bank_details',        // JSON: for payments
        'email_templates',     // JSON: quote_sent, invoice_sent, etc.
    ];
}

// app/Models/TrackingCategory.php
class TrackingCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];
}
```

### Enhanced Quote/Invoice Line Item

```php
// Migration additions
Schema::table('quote_line_items', function (Blueprint $table) {
    $table->uuid('uuid')->after('id');
    $table->string('item_type', 20)->default('product')->after('uuid');
    $table->string('sku')->nullable()->after('product_id');
    $table->text('detailed_description')->nullable()->after('description');
    $table->string('unit', 20)->nullable()->after('quantity');
    $table->string('discount_type', 10)->default('none')->after('unit_price');
    $table->decimal('discount_value', 15, 4)->default(0)->after('discount_type');
    $table->foreignId('tax_rate_id')->nullable()->after('tax_rate');
    $table->foreignId('account_id')->nullable()->after('line_total');
    $table->foreignId('tracking_category_id')->nullable()->after('account_id');
});
```

---

## Component Implementation

### LineItemsEditor.svelte

Key features:
- Virtualized list for performance
- Drag-drop with @dnd-kit or svelte-dnd-action
- Inline editing with click-to-edit
- Auto-save on change
- Undo/redo support
- Copy/paste rows

### Files to Create

```
frontend/src/lib/components/billing/
├── LineItemsEditor.svelte       # Main editor component
├── LineItemRow.svelte           # Individual row
├── ProductSearch.svelte         # Autocomplete product search
├── TaxRateSelector.svelte       # Tax rate dropdown
├── DiscountEditor.svelte        # Inline discount pop-up
├── QuoteTotals.svelte          # Totals summary
├── BillingSettings.svelte       # Settings page
└── index.ts
```

---

## Implementation Priority

### Phase 1: Core Improvements
1. ✅ Tax rate presets (backend + frontend)
2. ✅ Unit of measure field
3. ✅ Improved line item table UI
4. ✅ Product search autocomplete

### Phase 2: Advanced Features
1. Drag-and-drop reordering
2. Inline editing
3. Keyboard navigation
4. Text-only lines (for headings/notes)

### Phase 3: Polish
1. Undo/redo
2. Copy/paste rows
3. Bulk actions
4. Templates with line items
