# D5: Shared Team Inboxes

## Overview
Collaborative email inboxes where teams can manage shared email addresses with assignment, collision detection, and SLA tracking.

## Key Features
- Connect shared email addresses
- Email assignment to team members
- Collision detection (someone else is replying)
- SLA tracking and alerts
- Email templates
- Internal notes on threads
- Status tracking (open, pending, closed)
- Workload distribution

## Technical Requirements
- OAuth connection to email providers
- Real-time sync
- Concurrent editing prevention

## Database Additions
```sql
CREATE TABLE shared_inboxes (id, email, provider, team_id, sla_settings);
CREATE TABLE inbox_emails (id, inbox_id, thread_id, assigned_to, status, sla_due_at);
CREATE TABLE inbox_internal_notes (id, email_id, user_id, content);
CREATE TABLE inbox_assignments (id, email_id, assigned_to, assigned_at);
```

## Components
- `SharedInboxDashboard.svelte`
- `InboxEmailList.svelte`
- `EmailThreadView.svelte`
- `AssignmentPanel.svelte`
- `SLAIndicator.svelte`
