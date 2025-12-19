# VRTX CRM Feature Tiers

This document outlines the categorization of features into Core, Advanced, and Plugin tiers for the VRTX CRM platform.

## Tier Overview

| Tier | Description | Licensing |
|------|-------------|-----------|
| **Core** | Essential CRM functionality included in all plans | Base subscription |
| **Advanced** | Enhanced features for growing teams | Professional+ plans |
| **Enterprise/Plugins** | Specialized features, integrations, and add-ons | Enterprise plan or separate license |

---

## CORE FEATURES (All Plans)

These features form the foundation of the CRM and are available to all users.

### Contact & Account Management
- **Modules** - Leads, Contacts, Accounts, Deals
- **Custom Fields** - Text, number, date, dropdown, lookup, multi-select
- **Field Validation** - Required fields, format validation
- **Record Ownership** - Owner assignment, reassignment
- **Related Records** - Linking records across modules
- **Activity Timeline** - Activity history per record

### Pipeline & Sales Management
- **Pipeline Views** - Kanban, list, and table views
- **Stage Management** - Custom stages, stage transitions
- **Deal Values** - Amount tracking, currency support
- **Win/Loss Tracking** - Outcome recording

### Dashboards & Reporting
- **Basic Dashboards** - KPI widgets, charts, tables
- **Standard Reports** - Pre-built reports by module
- **Custom Reports** - Report builder with filters
- **Export** - CSV/Excel export

### User Management
- **User Accounts** - Create, edit, deactivate users
- **Basic Roles** - Admin, Manager, Sales Rep, Read-Only
- **Password Management** - Reset, requirements

### Data Management
- **Import** - CSV/Excel import with field mapping
- **Export** - Bulk data export
- **Duplicate Detection** - Basic duplicate matching
- **Merge Records** - Manual record merging

### Activity & Notes
- **Tasks** - Task creation, assignment, due dates
- **Notes** - Record notes and attachments
- **Activity Logging** - Call, email, meeting logging

### Email Templates
- **Template Management** - Create, edit templates
- **Merge Fields** - Dynamic field insertion
- **Template Categories** - Organization by type

---

## ADVANCED FEATURES (Professional+)

Features for teams requiring automation, forecasting, and enhanced productivity.

### Workflow Automation
- **Workflows** - Trigger-based automation rules
- **Blueprints** - Process enforcement with SLAs
- **Approval Rules** - Multi-level approval workflows
- **Scheduled Actions** - Time-based automation

### Sales Productivity
- **Playbooks** - Guided selling scripts
- **Cadences/Sequences** - Multi-step outreach automation
- **Email Tracking** - Open/click tracking
- **Meeting Scheduling** - Calendar integration, booking pages

### Forecasting & Quotas
- **Revenue Forecasts** - Pipeline-based forecasting
- **Quota Management** - Individual and team quotas
- **Goal Tracking** - Progress toward targets
- **Leaderboards** - Performance rankings

### Campaign Management
- **Campaign Tracking** - Multi-channel campaigns
- **ROI Analysis** - Campaign performance metrics
- **Lead Attribution** - Source tracking

### API & Integrations
- **API Keys** - REST API access
- **Webhooks** - Event-based notifications
- **OAuth Integration** - Third-party app auth

### Advanced Reporting
- **Report Scheduling** - Automated report delivery
- **Dashboard Sharing** - Team/public dashboards
- **Custom Calculations** - Calculated fields in reports

### Audit & Compliance
- **Audit Logs** - Full change history
- **Field-Level Tracking** - Detailed change tracking
- **Export Logs** - Compliance reporting

---

## ENTERPRISE FEATURES (Enterprise Plan)

Features for large organizations requiring advanced security, customization, and scale.

### Customer Portal
- **Portal Users** - External user access
- **Portal Invitations** - Invite management
- **Portal Dashboards** - Customer-facing views
- **Portal Announcements** - Customer communications
- **Document Sharing** - Secure document access

### Deal Rooms
- **Virtual Deal Rooms** - Collaborative spaces per deal
- **Stakeholder Management** - Multi-party engagement
- **Activity Analytics** - Engagement tracking
- **Document Versioning** - Version control

### Document Management
- **Document Library** - Central document storage
- **Template Management** - Document templates
- **Version Control** - Document versioning
- **Access Controls** - Permission-based access

### E-Signatures
- **Signature Workflows** - Multi-signer flows
- **Audit Trails** - Legal compliance
- **Template Signatures** - Predefined signature positions
- **Mobile Signing** - Cross-device support

### Proposals
- **Proposal Builder** - Visual proposal creation
- **Content Library** - Reusable content blocks
- **Pricing Tables** - Product/pricing configuration
- **Proposal Analytics** - View tracking

### AI Features
- **AI Insights** - Intelligent recommendations
- **Sentiment Analysis** - Communication analysis
- **Lead Scoring AI** - ML-based scoring
- **Email Suggestions** - AI-powered drafts

### Competitor Intelligence
- **Battlecards** - Competitive comparison cards
- **Win/Loss Analysis** - Competitive outcomes
- **Competitor Tracking** - Market intelligence

### Advanced Security
- **IP Restrictions** - Access control by IP
- **Session Management** - Active session control
- **2FA Enforcement** - Two-factor requirements
- **SSO Integration** - SAML/OAuth SSO

---

## PLUGIN FEATURES (Separate License)

Optional add-ons that extend functionality for specific use cases.

### Marketing Suite Plugin
- **Landing Pages** - Page builder with templates
- **Web Forms** - Form builder with submissions
- **A/B Testing** - Conversion optimization
- **Email Campaigns** - Bulk email sending
- **Marketing Automation** - Lead nurturing flows

### CMS Plugin
- **Page Management** - Content pages
- **Blog/Articles** - Content publishing
- **Media Library** - Asset management
- **Templates** - Layout templates
- **SEO Tools** - Meta management

### Billing & Invoicing Plugin
- **Invoice Generation** - Professional invoices
- **Quote Management** - Quote to invoice conversion
- **Payment Integration** - Payment gateway support
- **Subscription Billing** - Recurring billing
- **Revenue Recognition** - Financial reporting

### Support & Ticketing Plugin
- **Ticket Management** - Support case tracking
- **SLA Management** - Response time tracking
- **Knowledge Base** - Self-service articles
- **Customer Satisfaction** - CSAT surveys

### Live Chat Plugin
- **Website Chat** - Embedded chat widget
- **Chat Routing** - Team/skill-based routing
- **Chat Transcripts** - Conversation history
- **Bot Integration** - Chatbot support

### WhatsApp Integration Plugin
- **WhatsApp Business** - Official API integration
- **Template Messages** - Pre-approved templates
- **Conversation Management** - Unified inbox

### Video & Recording Plugin
- **Screen Recording** - Browser-based recording
- **Video Messages** - Video email integration
- **Playback Analytics** - View tracking

### Telephony Plugin
- **Click-to-Call** - One-click dialing
- **Call Logging** - Automatic call records
- **Call Recording** - Voice recording storage
- **IVR Integration** - Phone system integration

### Calendar Sync Plugin
- **Google Calendar** - Two-way sync
- **Outlook Calendar** - Two-way sync
- **Apple Calendar** - Two-way sync
- **Scheduling Pages** - Public booking links

---

## RBAC Permission Mapping

### Permission Categories

| Category | Core | Advanced | Enterprise |
|----------|------|----------|------------|
| modules.* | ✓ | ✓ | ✓ |
| pipelines.* | ✓ | ✓ | ✓ |
| dashboards.* | ✓ | ✓ | ✓ |
| reports.* | ✓ | ✓ | ✓ |
| users.* | ✓ | ✓ | ✓ |
| roles.* | ✓ | ✓ | ✓ |
| settings.* | ✓ | ✓ | ✓ |
| email_templates.* | ✓ | ✓ | ✓ |
| data.* | ✓ | ✓ | ✓ |
| activity.view | ✓ | ✓ | ✓ |
| blueprints.* | | ✓ | ✓ |
| workflows.* | | ✓ | ✓ |
| approvals.* | | ✓ | ✓ |
| api_keys.* | | ✓ | ✓ |
| webhooks.* | | ✓ | ✓ |
| forecasts.* | | ✓ | ✓ |
| quotas.* | | ✓ | ✓ |
| email.* | | ✓ | ✓ |
| meetings.* | | ✓ | ✓ |
| playbooks.* | | ✓ | ✓ |
| cadences.* | | ✓ | ✓ |
| campaigns.* | | ✓ | ✓ |
| audit_logs.view | | ✓ | ✓ |
| portal.* | | | ✓ |
| documents.* | | | ✓ |
| signatures.* | | | ✓ |
| proposals.* | | | ✓ |
| deal_rooms.* | | | ✓ |
| ai.* | | | ✓ |
| competitors.* | | | ✓ |
| knowledge_base.* | | | Plugin |
| landing_pages.* | | | Plugin |
| web_forms.* | | | Plugin |
| ab_tests.* | | | Plugin |
| cms.* | | | Plugin |
| billing.* | | | Plugin |
| support.* | | | Plugin |
| live_chat.* | | | Plugin |
| integrations.* | | | Plugin |
| recordings.* | | | Plugin |

---

## Implementation Notes

### Plan-Based Access Control

The system uses a combination of:
1. **RBAC Permissions** - What actions a user can perform
2. **Plan Feature Flags** - What features are available to the tenant
3. **Plugin Licenses** - What add-ons are enabled

### Middleware Stack

```
Route -> TenantMiddleware -> PlanCheck -> PluginLicense -> Permission -> Controller
```

### Frontend Guards

Components should use the PermissionGuard:
```svelte
<PermissionGuard permission="workflows.view">
  <WorkflowList />
</PermissionGuard>
```

### Backend Middleware

Routes should use permission middleware:
```php
Route::middleware('permission:workflows.view')->get('/workflows', ...);
Route::middleware('plan:advanced')->group(function() { ... });
Route::middleware('plugin-license:marketing')->group(function() { ... });
```

---

## Migration Path

When upgrading plans:
1. Advanced features become available immediately
2. Enterprise features enabled with plan upgrade
3. Plugins require separate activation and license

When downgrading:
1. Advanced features are soft-disabled (data retained)
2. Enterprise features become read-only
3. Plugin data retained for 90 days
