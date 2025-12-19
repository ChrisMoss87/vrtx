# A4: Public Web Forms

## Overview

Create embeddable web forms that capture leads and data directly into CRM modules, with customizable styling and spam protection.

## User Stories

1. As a marketer, I want to create forms that visitors can fill out on our website
2. As an admin, I want to map form fields to CRM module fields
3. As a user, I want to see which form a lead came from
4. As a developer, I want to embed forms via iframe or JavaScript snippet

## Feature Requirements

### Core Functionality
- [ ] Visual form builder (drag-and-drop)
- [ ] Map form fields to any module
- [ ] Multiple form styles/themes
- [ ] Embed via iframe or JavaScript
- [ ] reCAPTCHA/spam protection
- [ ] Thank you page customization
- [ ] Redirect after submission
- [ ] Hidden fields (UTM params, source)
- [ ] Form analytics (views, submissions, conversion rate)

### Form Field Types
- Text input
- Email
- Phone
- Textarea
- Select dropdown
- Multi-select
- Checkbox
- Radio buttons
- Date picker
- File upload
- Hidden field

### Customization
- Colors and fonts
- Logo upload
- Custom CSS
- Submit button text
- Field labels and placeholders
- Required field markers
- Validation messages

## Technical Requirements

### Database Schema

```sql
CREATE TABLE web_forms (
    id SERIAL PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    module_id INTEGER REFERENCES modules(id),
    is_active BOOLEAN DEFAULT true,
    settings JSONB DEFAULT '{}',
    styling JSONB DEFAULT '{}',
    thank_you_config JSONB DEFAULT '{}',
    spam_protection JSONB DEFAULT '{}',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- settings example:
-- {
--   "submit_button_text": "Submit",
--   "success_message": "Thank you!",
--   "redirect_url": "https://example.com/thanks",
--   "assign_to_user_id": 5,
--   "auto_responder_template_id": 12
-- }

CREATE TABLE web_form_fields (
    id SERIAL PRIMARY KEY,
    form_id INTEGER REFERENCES web_forms(id) ON DELETE CASCADE,
    field_type VARCHAR(50) NOT NULL,
    label VARCHAR(255) NOT NULL,
    placeholder VARCHAR(255),
    is_required BOOLEAN DEFAULT false,
    module_field_id INTEGER REFERENCES fields(id),
    options JSONB, -- for select/radio/checkbox
    validation_rules JSONB,
    display_order INTEGER DEFAULT 0,
    settings JSONB DEFAULT '{}'
);

CREATE TABLE web_form_submissions (
    id SERIAL PRIMARY KEY,
    form_id INTEGER REFERENCES web_forms(id),
    record_id INTEGER, -- created record in module
    submission_data JSONB NOT NULL,
    ip_address INET,
    user_agent TEXT,
    referrer TEXT,
    utm_params JSONB,
    submitted_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE web_form_analytics (
    id SERIAL PRIMARY KEY,
    form_id INTEGER REFERENCES web_forms(id),
    date DATE NOT NULL,
    views INTEGER DEFAULT 0,
    submissions INTEGER DEFAULT 0,
    UNIQUE(form_id, date)
);
```

### Backend Components

**Controllers:**
- `WebFormController` - CRUD for forms
- `WebFormPublicController` - Public submission endpoint (no auth)

**Services:**
- `WebFormService` - Form management
- `WebFormSubmissionService` - Process submissions, create records

**API Endpoints:**
```
# Authenticated (admin)
GET    /api/v1/web-forms                  # List forms
GET    /api/v1/web-forms/{id}             # Get form details
POST   /api/v1/web-forms                  # Create form
PUT    /api/v1/web-forms/{id}             # Update form
DELETE /api/v1/web-forms/{id}             # Delete form
GET    /api/v1/web-forms/{id}/submissions # List submissions
GET    /api/v1/web-forms/{id}/analytics   # Get analytics

# Public (no auth required)
GET    /forms/{slug}                      # Render form HTML
POST   /forms/{slug}/submit               # Submit form
GET    /forms/{slug}/embed.js             # JavaScript embed code
```

### Frontend Components

**New Components:**
- `WebFormBuilder.svelte` - Visual form builder
- `WebFormFieldEditor.svelte` - Configure field properties
- `WebFormPreview.svelte` - Live preview
- `WebFormStyler.svelte` - Styling options
- `WebFormSubmissionsList.svelte` - View submissions
- `WebFormAnalytics.svelte` - Charts and metrics
- `WebFormEmbedCode.svelte` - Get embed snippets

**New Routes:**
- `/web-forms` - List all forms
- `/web-forms/new` - Create form
- `/web-forms/{id}/edit` - Edit form
- `/web-forms/{id}/submissions` - View submissions

### Public Form Renderer

Standalone page that renders without CRM authentication:
- Clean, minimal design
- Mobile responsive
- Loads quickly
- CORS-enabled for embedding

## UI/UX Design

### Form Builder
```
┌─────────────────────────────────────────────────────────────────────┐
│ Form Builder: Contact Us Form                                       │
├─────────────────────────────────────────────────────────────────────┤
│ ┌──────────────┐ ┌─────────────────────────────────────────────────┐│
│ │ Field Types  │ │                 Form Preview                    ││
│ │              │ │                                                 ││
│ │ [Text]       │ │  ┌─────────────────────────────────────────┐   ││
│ │ [Email]      │ │  │ Full Name *                             │   ││
│ │ [Phone]      │ │  │ [________________________]              │   ││
│ │ [Textarea]   │ │  │                                         │   ││
│ │ [Select]     │ │  │ Email Address *                         │   ││
│ │ [Checkbox]   │ │  │ [________________________]              │   ││
│ │ [Radio]      │ │  │                                         │   ││
│ │ [Date]       │ │  │ Message                                 │   ││
│ │ [File]       │ │  │ [________________________]              │   ││
│ │ [Hidden]     │ │  │ [________________________]              │   ││
│ │              │ │  │                                         │   ││
│ └──────────────┘ │  │ [    Submit    ]                        │   ││
│                  │  └─────────────────────────────────────────┘   ││
│                  └─────────────────────────────────────────────────┘│
│ [Settings] [Styling] [Integrations] [Embed Code]                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Embed Options
```
<!-- Iframe Embed -->
<iframe src="https://acme.vrtx.app/forms/contact-us"
        width="100%" height="500" frameborder="0">
</iframe>

<!-- JavaScript Embed -->
<div id="vrtx-form-contact-us"></div>
<script src="https://acme.vrtx.app/forms/contact-us/embed.js"></script>
```

## Testing Requirements

- [ ] Test form creation and field mapping
- [ ] Test public submission (no auth)
- [ ] Test spam protection
- [ ] Test record creation from submission
- [ ] Test analytics tracking
- [ ] Test embed functionality
- [ ] E2E test full flow

## Success Metrics

- Number of forms created
- Submission volume
- Conversion rates (views → submissions)
- Records created from forms
