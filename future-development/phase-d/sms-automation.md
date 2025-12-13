# D3: SMS Automation

## Overview
SMS messaging capabilities with automation support for alerts, reminders, and marketing campaigns.

## Key Features
- Twilio/SMS gateway integration
- Send SMS from CRM
- SMS templates with merge fields
- Bulk SMS campaigns
- Delivery tracking
- Reply handling
- Opt-out management
- Automated SMS in workflows

## Technical Requirements
- Twilio API integration
- Phone number provisioning
- Webhook for replies
- Compliance (TCPA, consent)

## Database Additions
```sql
CREATE TABLE sms_connections (id, provider, phone_number, settings);
CREATE TABLE sms_templates (id, name, content, merge_fields);
CREATE TABLE sms_messages (id, contact_id, direction, content, status, cost);
CREATE TABLE sms_opt_outs (id, phone_number, opted_out_at);
```

## Components
- `SMSComposer.svelte`
- `SMSTemplateEditor.svelte`
- `SMSCampaignBuilder.svelte`
- `SMSConversation.svelte`
