# G4: Renewal Management

## Overview
Track and manage subscription/contract renewals with automated reminders, pipeline views, and renewal forecasting.

## Key Features
- Renewal pipeline view
- Automated renewal reminders
- Renewal opportunity creation
- Contract end date tracking
- Renewal rate analytics
- Churn prediction
- Upsell opportunity identification
- Renewal playbooks

## Renewal Workflow
1. Auto-create renewal opportunity X days before end
2. Assign to account owner
3. Send customer reminder
4. Track renewal discussions
5. Process renewal or churn

## Database Additions
```sql
CREATE TABLE renewals (id, contract_id, deal_id, renewal_date, amount, status);
CREATE TABLE renewal_reminders (id, renewal_id, days_before, channel, sent_at);
CREATE TABLE renewal_settings (id, create_days_before, reminder_schedule, auto_assign);
```

## Components
- `RenewalPipeline.svelte`
- `RenewalCalendar.svelte`
- `RenewalForecast.svelte`
- `RenewalSettings.svelte`
