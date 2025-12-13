# G2: Support Ticketing

## Overview
Customer support ticket system integrated with CRM for tracking issues, requests, and service delivery.

## Key Features
- Ticket creation (email, form, portal)
- Priority and category assignment
- SLA management
- Assignment rules
- Ticket queues
- Email threading
- Internal notes
- Status workflows
- Canned responses
- Customer satisfaction surveys

## Ticket Properties
- Priority (Low, Medium, High, Urgent)
- Category (Bug, Question, Request, etc.)
- Status (Open, In Progress, Waiting, Resolved, Closed)
- SLA deadline
- Assigned agent/team

## Database Additions
```sql
CREATE TABLE tickets (id, contact_id, company_id, subject, status, priority, category, sla_due);
CREATE TABLE ticket_messages (id, ticket_id, sender_type, content, attachments);
CREATE TABLE ticket_slas (id, priority, first_response_hours, resolution_hours);
CREATE TABLE ticket_satisfaction (id, ticket_id, score, feedback);
```

## Components
- `TicketList.svelte`
- `TicketDetail.svelte`
- `TicketForm.svelte`
- `SLASettings.svelte`
- `SatisfactionSurvey.svelte`
