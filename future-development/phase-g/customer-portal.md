# G1: Customer Portal

## Overview
Self-service portal where customers can view their account, access documents, submit requests, and manage their relationship.

## Key Features
- Branded customer login
- Account overview dashboard
- Document access
- Invoice viewing and payment
- Support ticket submission
- Order/deal history
- Contact management
- Knowledge base access

## Portal Sections
- Dashboard with key metrics
- Documents and contracts
- Invoices and payments
- Support tickets
- Product/service catalog
- Contact information
- Team/user management

## Database Additions
```sql
CREATE TABLE portal_settings (id, tenant_id, branding, enabled_features, custom_domain);
CREATE TABLE portal_users (id, contact_id, email, password_hash, last_login);
CREATE TABLE portal_sessions (id, portal_user_id, token, expires_at);
CREATE TABLE portal_permissions (id, portal_user_id, section, can_view, can_edit);
```

## Components
- `PortalDashboard.svelte`
- `PortalDocuments.svelte`
- `PortalInvoices.svelte`
- `PortalTickets.svelte`
- `PortalSettings.svelte` (admin)
