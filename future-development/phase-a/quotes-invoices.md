# A6: Quotes & Invoices

## Overview

Create, send, and track quotes and invoices directly from deal records, with e-signature support and payment integration.

## User Stories

1. As a sales rep, I want to generate a quote from a deal with line items
2. As a user, I want to send quotes via email and track when they're viewed
3. As a prospect, I want to accept/decline quotes online
4. As a finance user, I want to convert accepted quotes to invoices
5. As an admin, I want to customize quote/invoice templates

## Feature Requirements

### Core Functionality
- [ ] Product/service catalog
- [ ] Quote builder with line items
- [ ] Discount and tax calculations
- [ ] Quote templates (customizable)
- [ ] PDF generation
- [ ] Email delivery with tracking
- [ ] Online quote viewing portal
- [ ] Quote acceptance/rejection
- [ ] Quote expiration dates
- [ ] Convert quote to invoice
- [ ] Invoice generation
- [ ] Payment tracking

### Quote Features
- Add products from catalog
- Custom line items
- Quantity and unit pricing
- Discounts (percentage or fixed)
- Tax calculation
- Subtotal, tax, total
- Terms and conditions
- Validity period
- Version history
- Status tracking (draft, sent, viewed, accepted, rejected, expired)

### Invoice Features
- Create from quote or standalone
- Invoice numbering
- Due date and payment terms
- Payment status tracking
- Partial payments
- Payment reminders
- Mark as paid

## Technical Requirements

### Database Schema

```sql
-- Product catalog
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100),
    description TEXT,
    unit_price DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    tax_rate DECIMAL(5,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    category_id INTEGER,
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE product_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    parent_id INTEGER REFERENCES product_categories(id),
    display_order INTEGER DEFAULT 0
);

-- Quotes
CREATE TABLE quotes (
    id SERIAL PRIMARY KEY,
    quote_number VARCHAR(50) UNIQUE NOT NULL,
    deal_id INTEGER, -- optional link to deal
    contact_id INTEGER REFERENCES module_records(id),
    company_id INTEGER REFERENCES module_records(id),
    status VARCHAR(20) DEFAULT 'draft',
    title VARCHAR(255),
    subtotal DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    valid_until DATE,
    terms TEXT,
    notes TEXT,
    template_id INTEGER,
    version INTEGER DEFAULT 1,
    accepted_at TIMESTAMP,
    accepted_by VARCHAR(255),
    accepted_signature TEXT, -- base64 signature image
    viewed_at TIMESTAMP,
    sent_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE quote_line_items (
    id SERIAL PRIMARY KEY,
    quote_id INTEGER REFERENCES quotes(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    display_order INTEGER DEFAULT 0
);

CREATE TABLE quote_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    is_default BOOLEAN DEFAULT false,
    header_html TEXT,
    footer_html TEXT,
    styling JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT NOW()
);

-- Invoices
CREATE TABLE invoices (
    id SERIAL PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    quote_id INTEGER REFERENCES quotes(id),
    deal_id INTEGER,
    contact_id INTEGER REFERENCES module_records(id),
    company_id INTEGER REFERENCES module_records(id),
    status VARCHAR(20) DEFAULT 'draft', -- draft, sent, viewed, paid, partial, overdue, cancelled
    subtotal DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    amount_paid DECIMAL(15,2) DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'USD',
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    payment_terms VARCHAR(50),
    notes TEXT,
    template_id INTEGER,
    sent_at TIMESTAMP,
    paid_at TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE invoice_line_items (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id),
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    display_order INTEGER DEFAULT 0
);

CREATE TABLE invoice_payments (
    id SERIAL PRIMARY KEY,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE CASCADE,
    amount DECIMAL(15,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50),
    reference VARCHAR(255),
    notes TEXT,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `ProductService` - Manage product catalog
- `QuoteService` - Create, send, track quotes
- `InvoiceService` - Invoice management
- `PdfGeneratorService` - Generate PDF documents

**API Endpoints:**
```
# Products
GET    /api/v1/products
POST   /api/v1/products
PUT    /api/v1/products/{id}
DELETE /api/v1/products/{id}

# Quotes
GET    /api/v1/quotes
GET    /api/v1/quotes/{id}
POST   /api/v1/quotes
PUT    /api/v1/quotes/{id}
DELETE /api/v1/quotes/{id}
POST   /api/v1/quotes/{id}/send
POST   /api/v1/quotes/{id}/duplicate
GET    /api/v1/quotes/{id}/pdf
POST   /api/v1/quotes/{id}/convert-to-invoice

# Public quote portal
GET    /quote/{token}                     # View quote
POST   /quote/{token}/accept
POST   /quote/{token}/reject

# Invoices
GET    /api/v1/invoices
GET    /api/v1/invoices/{id}
POST   /api/v1/invoices
PUT    /api/v1/invoices/{id}
POST   /api/v1/invoices/{id}/send
POST   /api/v1/invoices/{id}/payments
GET    /api/v1/invoices/{id}/pdf
```

### Frontend Components

**New Components:**
- `ProductCatalog.svelte` - Manage products
- `QuoteBuilder.svelte` - Create/edit quotes
- `LineItemEditor.svelte` - Add/edit line items
- `QuotePreview.svelte` - PDF-style preview
- `QuoteSendModal.svelte` - Email composition
- `QuotesList.svelte` - List with status filters
- `InvoiceBuilder.svelte` - Create invoices
- `PaymentRecorder.svelte` - Record payments

**Public Components:**
- `PublicQuoteViewer.svelte` - View quote online
- `SignaturePad.svelte` - Accept with signature

**New Routes:**
- `/products` - Product catalog
- `/quotes` - Quote list
- `/quotes/new` - Create quote
- `/quotes/{id}` - View/edit quote
- `/invoices` - Invoice list
- `/invoices/{id}` - View invoice

## UI/UX Design

### Quote Builder
```
┌─────────────────────────────────────────────────────────────────────┐
│ Quote #Q-2025-0042                                     [Draft ▼]   │
├─────────────────────────────────────────────────────────────────────┤
│ To: Acme Corporation                    Valid Until: Jan 30, 2025  │
│     John Smith (john@acme.com)                                      │
├─────────────────────────────────────────────────────────────────────┤
│ Line Items                                            [+ Add Item] │
│ ┌───────────────────────────────────────────────────────────────┐   │
│ │ Product          │ Qty │ Unit Price │ Discount │    Total    │   │
│ ├───────────────────────────────────────────────────────────────┤   │
│ │ Enterprise Lic.  │  5  │   $500.00  │    10%   │  $2,250.00  │   │
│ │ Implementation   │  1  │ $5,000.00  │     0%   │  $5,000.00  │   │
│ │ Annual Support   │  1  │ $2,000.00  │     0%   │  $2,000.00  │   │
│ └───────────────────────────────────────────────────────────────┘   │
│                                                                     │
│                                      Subtotal:        $9,250.00    │
│                                      Tax (8%):          $740.00    │
│                                      ─────────────────────────     │
│                                      Total:           $9,990.00    │
├─────────────────────────────────────────────────────────────────────┤
│ [Preview PDF] [Save Draft] [Send Quote]                            │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test line item calculations
- [ ] Test discount and tax logic
- [ ] Test PDF generation
- [ ] Test quote acceptance flow
- [ ] Test quote to invoice conversion
- [ ] Test payment tracking
- [ ] E2E test full quote lifecycle

## Success Metrics

- Quotes sent per month
- Quote acceptance rate
- Average time to acceptance
- Quote to invoice conversion rate
