# E2: Landing Page Builder

## Overview
Drag-and-drop landing page builder for lead capture, events, and promotions with CRM form integration.

## Key Features
- Visual page builder
- Pre-built templates
- Responsive design
- Custom domains
- Form integration
- Thank you pages
- A/B page variants
- Analytics (views, conversions)
- SEO settings

## Page Elements
- Hero sections
- Text blocks
- Images and videos
- Forms
- CTAs
- Testimonials
- Pricing tables
- FAQ accordions

## Database Additions
```sql
CREATE TABLE landing_pages (id, name, slug, domain, template_id, content, settings);
CREATE TABLE page_variants (id, page_id, variant_name, content, traffic_split);
CREATE TABLE page_analytics (id, page_id, date, views, form_submissions, bounce_rate);
```

## Components
- `PageBuilder.svelte`
- `PageElementLibrary.svelte`
- `PagePreview.svelte`
- `PageSettings.svelte`
- `PageAnalytics.svelte`
