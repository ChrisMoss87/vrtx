# F3: Proposal Builder

## Overview
Interactive proposal creation with content blocks, pricing tables, and client-facing proposal portal.

## Key Features
- Block-based proposal builder
- Pricing tables with line items
- Cover pages and branding
- Client viewing portal
- View tracking analytics
- Comments and questions
- Accept/reject with signature
- Proposal templates

## Proposal Sections
- Cover page
- Executive summary
- Scope of work
- Pricing/packages
- Timeline
- Terms and conditions
- Team bios
- Case studies

## Database Additions
```sql
CREATE TABLE proposals (id, name, deal_id, template_id, content, status, viewed_at);
CREATE TABLE proposal_sections (id, proposal_id, section_type, content, order);
CREATE TABLE proposal_views (id, proposal_id, viewer_email, viewed_at, time_spent);
CREATE TABLE proposal_comments (id, proposal_id, section_id, comment, author_email);
```

## Components
- `ProposalBuilder.svelte`
- `ProposalSection.svelte`
- `PricingTable.svelte`
- `ProposalAnalytics.svelte`
- `PublicProposalViewer.svelte`
