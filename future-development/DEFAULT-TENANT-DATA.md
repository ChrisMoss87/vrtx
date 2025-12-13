# Default Tenant Data Specification

This document outlines all default data that should be seeded for each new tenant, including modules, reports, dashboards, kanbans, table filters, and blueprints.

---

## 1. Core Modules (Required)

These modules form the foundation of the CRM and should be created for every new tenant.

### 1.1 Contacts Module
**API Name:** `contacts`
**Icon:** `users`
**Description:** People you interact with - leads, customers, partners

| Block | Fields |
|-------|--------|
| **Personal Information** | first_name (text, required), last_name (text, required), email (email, unique), phone (phone), mobile (phone), date_of_birth (date) |
| **Work Information** | organization_id (lookup→organizations), job_title (text), department (text), linkedin_url (url), twitter_handle (text) |
| **Address** | street (text), city (text), state (text), postal_code (text), country (select) |
| **Status & Source** | status (select: Lead/Prospect/Customer/Partner/Inactive), lead_source (select: Website/Referral/Social/Email/Cold Call/Trade Show/Other), assigned_to (lookup→users) |
| **Additional** | tags (multiselect), do_not_contact (checkbox), notes (textarea) |

**Default Views:**
- All Contacts (no filter)
- My Contacts (assigned_to = current_user)
- Active Leads (status = Lead)
- Customers (status = Customer)

---

### 1.2 Organizations Module
**API Name:** `organizations`
**Icon:** `building-2`
**Description:** Companies, businesses, and entities

| Block | Fields |
|-------|--------|
| **Basic Information** | name (text, required), website (url), industry (select), employee_count (select: 1-10/11-50/51-200/201-500/501-1000/1000+) |
| **Contact Details** | phone (phone), email (email), fax (phone) |
| **Address** | street (text), city (text), state (text), postal_code (text), country (select) |
| **Business Details** | type (select: Customer/Prospect/Partner/Vendor/Competitor), annual_revenue (currency), assigned_to (lookup→users), parent_organization_id (lookup→organizations) |
| **Additional** | description (textarea), tags (multiselect) |

**Default Views:**
- All Organizations
- My Accounts (assigned_to = current_user)
- Customers (type = Customer)
- Prospects (type = Prospect)

---

### 1.3 Deals Module
**API Name:** `deals`
**Icon:** `handshake`
**Description:** Sales opportunities and revenue tracking

| Block | Fields |
|-------|--------|
| **Deal Information** | name (text, required), amount (currency), probability (percent), expected_revenue (formula: amount * probability / 100) |
| **Related Records** | organization_id (lookup→organizations), contact_id (lookup→contacts), assigned_to (lookup→users) |
| **Stage & Timeline** | stage (select), close_date (date), source (select: Website/Referral/Partner/Outbound/Inbound) |
| **Additional** | description (textarea), next_step (text), competitors (text), tags (multiselect) |

**Stage Options (synced with Sales Pipeline):**
- Prospecting (probability: 10%, color: #6366f1)
- Qualification (probability: 25%, color: #8b5cf6)
- Proposal (probability: 50%, color: #a855f7)
- Negotiation (probability: 75%, color: #d946ef)
- Closed Won (probability: 100%, color: #22c55e, is_won)
- Closed Lost (probability: 0%, color: #ef4444, is_lost)

**Default Views:**
- All Deals
- My Open Deals (assigned_to = current_user AND stage NOT IN [Closed Won, Closed Lost])
- Closing This Month (close_date = this_month)
- High Value Deals (amount >= 10000)

---

### 1.4 Tasks Module
**API Name:** `tasks`
**Icon:** `check-square`
**Description:** To-do items and action items

| Block | Fields |
|-------|--------|
| **Task Details** | subject (text, required), description (textarea), priority (select: Low/Normal/High/Urgent), status (select: Not Started/In Progress/Completed/Waiting/Deferred) |
| **Dates & Assignment** | due_date (date), due_time (time), reminder_date (datetime), assigned_to (lookup→users) |
| **Related Records** | related_to_type (select: Contact/Organization/Deal/Case), related_to_id (number) |
| **Additional** | is_recurring (checkbox), recurrence_pattern (select), tags (multiselect) |

**Default Views:**
- All Tasks
- My Tasks (assigned_to = current_user)
- Today's Tasks (due_date = today)
- Overdue Tasks (due_date < today AND status != Completed)
- High Priority (priority IN [High, Urgent] AND status != Completed)

---

### 1.5 Activities Module
**API Name:** `activities`
**Icon:** `activity`
**Description:** Calls, meetings, emails, and interactions

| Block | Fields |
|-------|--------|
| **Activity Details** | subject (text, required), type (select: Call/Meeting/Email/Note/Demo/Lunch), description (textarea) |
| **Timing** | start_datetime (datetime), end_datetime (datetime), duration_minutes (number), all_day (checkbox) |
| **Related Records** | contact_id (lookup→contacts), organization_id (lookup→organizations), deal_id (lookup→deals) |
| **Outcome** | outcome (select: Completed/No Answer/Left Message/Rescheduled/Cancelled), next_action (text), assigned_to (lookup→users) |

**Default Views:**
- All Activities
- My Activities (assigned_to = current_user)
- Today's Activities (start_datetime = today)
- This Week's Calls (type = Call AND start_datetime = this_week)
- Recent Meetings (type = Meeting, sorted by start_datetime desc, limit 50)

---

### 1.6 Notes Module
**API Name:** `notes`
**Icon:** `file-text`
**Description:** Internal notes and documentation

| Block | Fields |
|-------|--------|
| **Note Content** | title (text, required), content (textarea, required) |
| **Related Records** | related_to_type (select: Contact/Organization/Deal/Case/Task), related_to_id (number) |
| **Metadata** | is_pinned (checkbox), tags (multiselect), visibility (select: Everyone/Team Only/Private) |

**Default Views:**
- All Notes
- My Notes (created_by = current_user)
- Pinned Notes (is_pinned = true)

---

### 1.7 Cases Module (Support Tickets)
**API Name:** `cases`
**Icon:** `headset`
**Description:** Customer support tickets and issues

| Block | Fields |
|-------|--------|
| **Case Details** | case_number (text, auto-generated, unique), subject (text, required), description (textarea), type (select: Question/Problem/Feature Request/Bug) |
| **Classification** | status (select: New/Open/In Progress/Waiting on Customer/Resolved/Closed), priority (select: Low/Medium/High/Critical), severity (select: Minor/Major/Critical/Blocker) |
| **Customer Information** | contact_id (lookup→contacts), organization_id (lookup→organizations), email (email), phone (phone) |
| **Assignment & Resolution** | assigned_to (lookup→users), team (select), resolution (textarea), resolution_date (datetime), first_response_date (datetime) |
| **SLA Tracking** | sla_due_date (datetime), escalated (checkbox), escalation_date (datetime) |

**Status Options (synced with Support Pipeline):**
- New (color: #6366f1)
- Open (color: #3b82f6)
- In Progress (color: #f59e0b)
- Waiting on Customer (color: #8b5cf6)
- Resolved (color: #22c55e, is_terminal)
- Closed (color: #6b7280, is_terminal)

**Default Views:**
- All Cases
- My Cases (assigned_to = current_user)
- Open Cases (status NOT IN [Resolved, Closed])
- Critical Issues (priority = Critical OR severity IN [Critical, Blocker])
- Awaiting Response (status = Waiting on Customer)

---

### 1.8 Products Module
**API Name:** `products`
**Icon:** `package`
**Description:** Products and services catalog

| Block | Fields |
|-------|--------|
| **Product Information** | name (text, required), sku (text, unique), description (textarea), category (select) |
| **Pricing** | unit_price (currency, required), cost (currency), margin (formula: (unit_price - cost) / unit_price * 100), tax_rate (percent) |
| **Inventory** | quantity_in_stock (number), reorder_level (number), is_active (checkbox, default: true) |
| **Additional** | vendor (text), weight (decimal), dimensions (text), tags (multiselect) |

**Category Options:**
- Software, Hardware, Services, Consulting, Training, Support, Subscription, Add-on

**Default Views:**
- All Products
- Active Products (is_active = true)
- Low Stock (quantity_in_stock <= reorder_level AND is_active = true)
- By Category (grouped by category)

---

### 1.9 Invoices Module
**API Name:** `invoices`
**Icon:** `file-text`
**Description:** Customer invoices and billing

| Block | Fields |
|-------|--------|
| **Invoice Details** | invoice_number (text, auto-generated, unique), status (select: Draft/Sent/Paid/Overdue/Cancelled/Refunded) |
| **Amounts** | subtotal (currency), tax_amount (currency), discount_amount (currency), total (formula: subtotal + tax_amount - discount_amount), amount_paid (currency), balance_due (formula: total - amount_paid) |
| **Related Records** | organization_id (lookup→organizations), contact_id (lookup→contacts), deal_id (lookup→deals) |
| **Dates** | invoice_date (date, required), due_date (date, required), payment_date (date) |
| **Payment** | payment_terms (select: Due on Receipt/Net 15/Net 30/Net 45/Net 60), payment_method (select: Bank Transfer/Credit Card/Check/Cash/PayPal) |
| **Notes** | notes (textarea), terms (textarea) |

**Default Views:**
- All Invoices
- Unpaid Invoices (status IN [Sent, Overdue])
- Overdue (status = Overdue OR (due_date < today AND status = Sent))
- This Month (invoice_date = this_month)
- Paid This Month (status = Paid AND payment_date = this_month)

---

### 1.10 Quotes Module
**API Name:** `quotes`
**Icon:** `file-signature`
**Description:** Sales quotes and proposals

| Block | Fields |
|-------|--------|
| **Quote Details** | quote_number (text, auto-generated, unique), subject (text, required), status (select: Draft/Sent/Accepted/Rejected/Expired) |
| **Amounts** | subtotal (currency), tax_amount (currency), discount_percent (percent), discount_amount (formula), total (formula) |
| **Related Records** | organization_id (lookup→organizations), contact_id (lookup→contacts), deal_id (lookup→deals) |
| **Dates** | quote_date (date), valid_until (date), accepted_date (date) |
| **Additional** | terms (textarea), notes (textarea), assigned_to (lookup→users) |

**Default Views:**
- All Quotes
- My Quotes (assigned_to = current_user)
- Pending Quotes (status = Sent)
- Expiring Soon (valid_until <= today + 7 AND status = Sent)

---

### 1.11 Calendar/Events Module
**API Name:** `events`
**Icon:** `calendar`
**Description:** Calendar events and scheduling

| Block | Fields |
|-------|--------|
| **Event Details** | title (text, required), description (textarea), location (text), event_type (select: Meeting/Call/Webinar/Conference/Personal/Other) |
| **Timing** | start_datetime (datetime, required), end_datetime (datetime, required), all_day (checkbox), timezone (select) |
| **Recurrence** | is_recurring (checkbox), recurrence_rule (text), recurrence_end_date (date) |
| **Attendees** | organizer_id (lookup→users), attendees (multiselect→users), external_attendees (textarea) |
| **Related Records** | contact_id (lookup→contacts), organization_id (lookup→organizations), deal_id (lookup→deals) |
| **Reminders** | reminder_minutes (select: 0/5/10/15/30/60/1440), reminder_sent (checkbox) |

**Default Views:**
- All Events
- My Events (organizer_id = current_user OR attendees CONTAINS current_user)
- Today's Events (start_datetime = today)
- This Week (start_datetime = this_week)
- Upcoming (start_datetime >= today, sorted by start_datetime asc)

---

## 2. Default Pipelines (Kanban Boards)

### 2.1 Sales Pipeline
**Module:** deals
**Stage Field:** stage

| Stage | Color | Probability | Type |
|-------|-------|-------------|------|
| Prospecting | #6366f1 (indigo) | 10% | Normal |
| Qualification | #8b5cf6 (violet) | 25% | Normal |
| Proposal | #a855f7 (purple) | 50% | Normal |
| Negotiation | #d946ef (fuchsia) | 75% | Normal |
| Closed Won | #22c55e (green) | 100% | Won |
| Closed Lost | #ef4444 (red) | 0% | Lost |

**Settings:**
- Title field: name
- Value field: amount
- Subtitle field: organization.name
- Due date field: close_date

---

### 2.2 Support Pipeline
**Module:** cases
**Stage Field:** status

| Stage | Color | Type |
|-------|-------|------|
| New | #6366f1 (indigo) | Initial |
| Open | #3b82f6 (blue) | Normal |
| In Progress | #f59e0b (amber) | Normal |
| Waiting on Customer | #8b5cf6 (violet) | Normal |
| Resolved | #22c55e (green) | Terminal |
| Closed | #6b7280 (gray) | Terminal |

**Settings:**
- Title field: subject
- Value field: null
- Subtitle field: contact.name
- Due date field: sla_due_date

---

### 2.3 Task Board
**Module:** tasks
**Stage Field:** status

| Stage | Color | Type |
|-------|-------|------|
| Not Started | #6b7280 (gray) | Initial |
| In Progress | #3b82f6 (blue) | Normal |
| Waiting | #f59e0b (amber) | Normal |
| Completed | #22c55e (green) | Terminal |
| Deferred | #8b5cf6 (violet) | Normal |

**Settings:**
- Title field: subject
- Value field: null
- Subtitle field: assigned_to.name
- Due date field: due_date

---

### 2.4 Quote Pipeline
**Module:** quotes
**Stage Field:** status

| Stage | Color | Type |
|-------|-------|------|
| Draft | #6b7280 (gray) | Initial |
| Sent | #3b82f6 (blue) | Normal |
| Accepted | #22c55e (green) | Won |
| Rejected | #ef4444 (red) | Lost |
| Expired | #f59e0b (amber) | Lost |

**Settings:**
- Title field: subject
- Value field: total
- Subtitle field: organization.name
- Due date field: valid_until

---

### 2.5 Invoice Pipeline
**Module:** invoices
**Stage Field:** status

| Stage | Color | Type |
|-------|-------|------|
| Draft | #6b7280 (gray) | Initial |
| Sent | #3b82f6 (blue) | Normal |
| Overdue | #ef4444 (red) | Normal |
| Paid | #22c55e (green) | Terminal |
| Cancelled | #6b7280 (gray) | Terminal |
| Refunded | #f59e0b (amber) | Terminal |

**Settings:**
- Title field: invoice_number
- Value field: total
- Subtitle field: organization.name
- Due date field: due_date

---

## 3. Default Reports

### 3.1 Sales Reports

#### Sales Pipeline Report
- **Type:** Chart (Funnel)
- **Module:** deals
- **Grouping:** stage
- **Aggregation:** SUM(amount), COUNT(*)
- **Filters:** stage NOT IN [Closed Won, Closed Lost]
- **Description:** Visual funnel of deal values by stage

#### Monthly Revenue Report
- **Type:** Chart (Bar)
- **Module:** deals
- **Grouping:** close_date (by month)
- **Aggregation:** SUM(amount)
- **Filters:** stage = Closed Won, close_date = this_year
- **Description:** Monthly closed revenue for current year

#### Top Deals Report
- **Type:** Table
- **Module:** deals
- **Columns:** name, organization.name, amount, stage, close_date, assigned_to.name
- **Filters:** stage NOT IN [Closed Won, Closed Lost]
- **Sorting:** amount DESC
- **Limit:** 10
- **Description:** Top 10 open deals by value

#### Sales by Rep Report
- **Type:** Chart (Bar)
- **Module:** deals
- **Grouping:** assigned_to
- **Aggregation:** SUM(amount), COUNT(*)
- **Filters:** stage = Closed Won, close_date = this_quarter
- **Description:** Closed revenue by sales rep this quarter

#### Win/Loss Report
- **Type:** Summary
- **Module:** deals
- **Grouping:** stage
- **Aggregation:** COUNT(*), SUM(amount)
- **Filters:** stage IN [Closed Won, Closed Lost], close_date = this_quarter
- **Description:** Win rate analysis

#### Deal Source Analysis
- **Type:** Chart (Pie)
- **Module:** deals
- **Grouping:** source
- **Aggregation:** SUM(amount)
- **Filters:** stage = Closed Won, close_date = this_year
- **Description:** Revenue by lead source

---

### 3.2 Customer Reports

#### Contacts by Status
- **Type:** Chart (Pie)
- **Module:** contacts
- **Grouping:** status
- **Aggregation:** COUNT(*)
- **Description:** Distribution of contacts by status

#### New Contacts Report
- **Type:** Chart (Line)
- **Module:** contacts
- **Grouping:** created_at (by week)
- **Aggregation:** COUNT(*)
- **Filters:** created_at = last_90_days
- **Description:** New contacts added over time

#### Organizations by Industry
- **Type:** Chart (Bar)
- **Module:** organizations
- **Grouping:** industry
- **Aggregation:** COUNT(*)
- **Description:** Organization count by industry

#### Contacts by Lead Source
- **Type:** Chart (Doughnut)
- **Module:** contacts
- **Grouping:** lead_source
- **Aggregation:** COUNT(*)
- **Description:** Where contacts are coming from

---

### 3.3 Support Reports

#### Open Cases by Priority
- **Type:** Chart (Bar)
- **Module:** cases
- **Grouping:** priority
- **Aggregation:** COUNT(*)
- **Filters:** status NOT IN [Resolved, Closed]
- **Description:** Open case distribution by priority

#### Case Resolution Time
- **Type:** Summary
- **Module:** cases
- **Grouping:** type
- **Aggregation:** AVG(resolution_time), COUNT(*)
- **Filters:** status IN [Resolved, Closed], resolution_date = this_month
- **Description:** Average resolution time by case type

#### Cases by Status
- **Type:** Chart (Pie)
- **Module:** cases
- **Grouping:** status
- **Aggregation:** COUNT(*)
- **Description:** Current case status distribution

#### Case Trend Report
- **Type:** Chart (Line)
- **Module:** cases
- **Grouping:** created_at (by week)
- **Aggregation:** COUNT(*)
- **Filters:** created_at = last_90_days
- **Description:** Case volume over time

#### Overdue Cases
- **Type:** Table
- **Module:** cases
- **Columns:** case_number, subject, priority, contact.name, sla_due_date, assigned_to.name
- **Filters:** sla_due_date < today, status NOT IN [Resolved, Closed]
- **Sorting:** sla_due_date ASC
- **Description:** Cases past SLA deadline

---

### 3.4 Activity Reports

#### Activity Summary
- **Type:** Summary
- **Module:** activities
- **Grouping:** type, outcome
- **Aggregation:** COUNT(*)
- **Filters:** start_datetime = this_month
- **Description:** Activity breakdown by type and outcome

#### Activities by Rep
- **Type:** Chart (Bar)
- **Module:** activities
- **Grouping:** assigned_to
- **Aggregation:** COUNT(*)
- **Filters:** start_datetime = this_week
- **Description:** Activity count by team member

#### Call Outcomes
- **Type:** Chart (Doughnut)
- **Module:** activities
- **Grouping:** outcome
- **Aggregation:** COUNT(*)
- **Filters:** type = Call, start_datetime = this_month
- **Description:** Call outcome distribution

---

### 3.5 Financial Reports

#### Invoice Aging Report
- **Type:** Summary
- **Module:** invoices
- **Grouping:** aging_bucket (calculated: Current/1-30/31-60/61-90/90+)
- **Aggregation:** SUM(balance_due), COUNT(*)
- **Filters:** status IN [Sent, Overdue]
- **Description:** Outstanding invoices by age

#### Revenue by Month
- **Type:** Chart (Bar)
- **Module:** invoices
- **Grouping:** payment_date (by month)
- **Aggregation:** SUM(amount_paid)
- **Filters:** status = Paid, payment_date = this_year
- **Description:** Monthly collected revenue

#### Unpaid Invoices
- **Type:** Table
- **Module:** invoices
- **Columns:** invoice_number, organization.name, total, balance_due, due_date, status
- **Filters:** status IN [Sent, Overdue]
- **Sorting:** due_date ASC
- **Description:** All outstanding invoices

#### Quote Conversion Report
- **Type:** Summary
- **Module:** quotes
- **Grouping:** status
- **Aggregation:** COUNT(*), SUM(total)
- **Filters:** quote_date = this_quarter
- **Description:** Quote acceptance rate

---

### 3.6 Task Reports

#### My Tasks Due This Week
- **Type:** Table
- **Module:** tasks
- **Columns:** subject, priority, due_date, status, related_to
- **Filters:** assigned_to = current_user, due_date = this_week, status != Completed
- **Sorting:** due_date ASC, priority DESC
- **Description:** Personal task list for the week

#### Overdue Tasks by Owner
- **Type:** Chart (Bar)
- **Module:** tasks
- **Grouping:** assigned_to
- **Aggregation:** COUNT(*)
- **Filters:** due_date < today, status NOT IN [Completed, Deferred]
- **Description:** Overdue task count by team member

#### Task Completion Rate
- **Type:** Summary
- **Module:** tasks
- **Grouping:** assigned_to
- **Aggregation:** COUNT(*) where status = Completed, COUNT(*) total
- **Filters:** created_at = this_month
- **Description:** Completion rates by team member

---

## 4. Default Dashboards

### 4.1 Sales Dashboard
**Is Default:** Yes (for sales_rep, manager roles)
**Refresh Interval:** 300 seconds (5 min)

| Widget | Type | Position | Size | Configuration |
|--------|------|----------|------|---------------|
| **Total Pipeline Value** | KPI | row 1, col 1 | 1x1 | module: deals, aggregation: SUM(amount), filter: stage NOT IN won/lost |
| **Deals to Close This Month** | KPI | row 1, col 2 | 1x1 | module: deals, aggregation: COUNT, filter: close_date = this_month |
| **Won This Month** | KPI | row 1, col 3 | 1x1 | module: deals, aggregation: SUM(amount), filter: stage = Closed Won, close_date = this_month, compare: last_month |
| **Win Rate** | KPI | row 1, col 4 | 1x1 | formula: won / (won + lost) * 100, filter: close_date = this_month |
| **Sales Pipeline** | Chart (Funnel) | row 2, col 1 | 2x2 | report: Sales Pipeline Report |
| **Monthly Revenue** | Chart (Bar) | row 2, col 3 | 2x2 | report: Monthly Revenue Report |
| **Top Deals** | Table | row 3, col 1 | 2x1 | report: Top Deals Report |
| **My Tasks** | Tasks | row 3, col 3 | 2x1 | filter: assigned_to = current_user, limit: 5 |
| **Recent Activity** | Activity | row 4, col 1 | 4x1 | filter: created_at = last_7_days, limit: 10 |

---

### 4.2 Support Dashboard
**Is Default:** Yes (for support roles)

| Widget | Type | Position | Size | Configuration |
|--------|------|----------|------|---------------|
| **Open Cases** | KPI | row 1, col 1 | 1x1 | module: cases, aggregation: COUNT, filter: status NOT IN resolved/closed |
| **Critical Cases** | KPI | row 1, col 2 | 1x1 | module: cases, aggregation: COUNT, filter: priority = Critical, status NOT IN resolved/closed |
| **Resolved Today** | KPI | row 1, col 3 | 1x1 | module: cases, aggregation: COUNT, filter: resolution_date = today |
| **Avg Resolution Time** | KPI | row 1, col 4 | 1x1 | module: cases, aggregation: AVG(resolution_hours), filter: resolution_date = this_week |
| **Cases by Priority** | Chart (Bar) | row 2, col 1 | 2x2 | report: Open Cases by Priority |
| **Cases by Status** | Chart (Pie) | row 2, col 3 | 2x2 | report: Cases by Status |
| **Overdue Cases** | Table | row 3, col 1 | 2x1 | report: Overdue Cases, limit: 10 |
| **My Cases** | Table | row 3, col 3 | 2x1 | module: cases, filter: assigned_to = current_user, status NOT IN resolved/closed |
| **Case Trend** | Chart (Line) | row 4, col 1 | 4x1 | report: Case Trend Report |

---

### 4.3 Executive Dashboard
**Is Default:** Yes (for admin, manager roles)

| Widget | Type | Position | Size | Configuration |
|--------|------|----------|------|---------------|
| **Total Revenue (YTD)** | KPI | row 1, col 1 | 1x1 | module: deals, aggregation: SUM(amount), filter: stage = Closed Won, close_date = this_year |
| **New Customers** | KPI | row 1, col 2 | 1x1 | module: organizations, aggregation: COUNT, filter: type = Customer, created_at = this_month |
| **Active Deals** | KPI | row 1, col 3 | 1x1 | module: deals, aggregation: COUNT, filter: stage NOT IN won/lost |
| **Total Pipeline** | KPI | row 1, col 4 | 1x1 | module: deals, aggregation: SUM(amount), filter: stage NOT IN won/lost |
| **Revenue Trend** | Chart (Area) | row 2, col 1 | 2x2 | report: Monthly Revenue Report |
| **Sales by Rep** | Chart (Bar) | row 2, col 3 | 2x2 | report: Sales by Rep Report |
| **Deal Source Analysis** | Chart (Pie) | row 3, col 1 | 2x2 | report: Deal Source Analysis |
| **Top Performers** | Table | row 3, col 3 | 2x1 | grouped by rep, sorted by revenue |
| **Recent Wins** | Table | row 4, col 1 | 2x1 | module: deals, filter: stage = Closed Won, limit: 5 |
| **Upcoming Renewals** | Table | row 4, col 3 | 2x1 | module: deals, filter: type = Renewal, close_date = next_30_days |

---

### 4.4 Activity Dashboard

| Widget | Type | Position | Size | Configuration |
|--------|------|----------|------|---------------|
| **Calls Today** | KPI | row 1, col 1 | 1x1 | module: activities, aggregation: COUNT, filter: type = Call, start_datetime = today |
| **Meetings Today** | KPI | row 1, col 2 | 1x1 | module: activities, aggregation: COUNT, filter: type = Meeting, start_datetime = today |
| **Tasks Completed** | KPI | row 1, col 3 | 1x1 | module: tasks, aggregation: COUNT, filter: status = Completed, updated_at = today |
| **Pending Tasks** | KPI | row 1, col 4 | 1x1 | module: tasks, aggregation: COUNT, filter: status != Completed, assigned_to = current_user |
| **Activity by Type** | Chart (Bar) | row 2, col 1 | 2x2 | report: Activity Summary |
| **Call Outcomes** | Chart (Doughnut) | row 2, col 3 | 2x2 | report: Call Outcomes |
| **My Calendar** | Calendar | row 3, col 1 | 2x2 | module: events, filter: organizer = current_user OR attendees CONTAINS current_user |
| **My Tasks** | Tasks | row 3, col 3 | 2x1 | filter: assigned_to = current_user, status != Completed |

---

### 4.5 Financial Dashboard

| Widget | Type | Position | Size | Configuration |
|--------|------|----------|------|---------------|
| **Outstanding AR** | KPI | row 1, col 1 | 1x1 | module: invoices, aggregation: SUM(balance_due), filter: status IN sent/overdue |
| **Collected This Month** | KPI | row 1, col 2 | 1x1 | module: invoices, aggregation: SUM(amount_paid), filter: payment_date = this_month |
| **Overdue Amount** | KPI | row 1, col 3 | 1x1 | module: invoices, aggregation: SUM(balance_due), filter: status = Overdue |
| **Avg Days to Pay** | KPI | row 1, col 4 | 1x1 | module: invoices, aggregation: AVG(days_to_pay), filter: status = Paid, payment_date = this_quarter |
| **Invoice Aging** | Chart (Bar) | row 2, col 1 | 2x2 | report: Invoice Aging Report |
| **Revenue by Month** | Chart (Line) | row 2, col 3 | 2x2 | report: Revenue by Month |
| **Unpaid Invoices** | Table | row 3, col 1 | 2x1 | report: Unpaid Invoices |
| **Recent Payments** | Table | row 3, col 3 | 2x1 | module: invoices, filter: status = Paid, sorted by payment_date DESC, limit: 10 |

---

## 5. Default Table Filters (Saved Views)

### Per-Module Default Filters

Each module should have a standard set of saved views that users can quickly access:

#### Contacts
| View Name | Filters | Columns | Sorting | Is Default |
|-----------|---------|---------|---------|------------|
| All Contacts | none | first_name, last_name, email, phone, organization, status | last_name ASC | Yes |
| My Contacts | assigned_to = current_user | first_name, last_name, email, phone, status | created_at DESC | No |
| Leads | status = Lead | full_name, email, phone, lead_source, assigned_to | created_at DESC | No |
| Customers | status = Customer | full_name, email, organization, phone | last_name ASC | No |
| Recently Added | created_at = last_7_days | full_name, email, status, created_at | created_at DESC | No |

#### Organizations
| View Name | Filters | Columns | Sorting | Is Default |
|-----------|---------|---------|---------|------------|
| All Organizations | none | name, industry, type, phone, website | name ASC | Yes |
| My Accounts | assigned_to = current_user | name, type, industry, phone | name ASC | No |
| Customers | type = Customer | name, industry, annual_revenue, phone | name ASC | No |
| Prospects | type = Prospect | name, industry, assigned_to | created_at DESC | No |

#### Deals
| View Name | Filters | Columns | Sorting | Is Default |
|-----------|---------|---------|---------|------------|
| All Deals | none | name, organization, amount, stage, close_date | close_date ASC | Yes |
| My Open Deals | assigned_to = current_user, stage NOT IN won/lost | name, amount, stage, close_date | close_date ASC | No |
| Closing This Month | close_date = this_month, stage NOT IN won/lost | name, organization, amount, stage, probability | amount DESC | No |
| Won Deals | stage = Closed Won | name, organization, amount, close_date | close_date DESC | No |
| High Value | amount >= 10000, stage NOT IN won/lost | name, organization, amount, stage, assigned_to | amount DESC | No |

#### Tasks
| View Name | Filters | Columns | Sorting | Is Default |
|-----------|---------|---------|---------|------------|
| All Tasks | none | subject, status, priority, due_date, assigned_to | due_date ASC | Yes |
| My Tasks | assigned_to = current_user | subject, status, priority, due_date | due_date ASC | No |
| Today | due_date = today | subject, status, priority, assigned_to | priority DESC | No |
| Overdue | due_date < today, status != Completed | subject, priority, due_date, assigned_to | due_date ASC | No |
| High Priority | priority IN High/Urgent, status != Completed | subject, status, due_date, assigned_to | due_date ASC | No |

#### Cases
| View Name | Filters | Columns | Sorting | Is Default |
|-----------|---------|---------|---------|------------|
| All Cases | none | case_number, subject, status, priority, contact | created_at DESC | Yes |
| My Cases | assigned_to = current_user | case_number, subject, status, priority, sla_due_date | priority DESC | No |
| Open Cases | status NOT IN Resolved/Closed | case_number, subject, priority, contact, assigned_to | created_at DESC | No |
| Critical | priority = Critical, status NOT IN Resolved/Closed | case_number, subject, contact, sla_due_date | sla_due_date ASC | No |
| Escalated | escalated = true | case_number, subject, priority, escalation_date | escalation_date DESC | No |

#### Invoices
| View Name | Filters | Columns | Sorting | Is Default |
|-----------|---------|---------|---------|------------|
| All Invoices | none | invoice_number, organization, total, status, due_date | invoice_date DESC | Yes |
| Unpaid | status IN Sent/Overdue | invoice_number, organization, total, balance_due, due_date | due_date ASC | No |
| Overdue | status = Overdue | invoice_number, organization, balance_due, due_date | due_date ASC | No |
| This Month | invoice_date = this_month | invoice_number, organization, total, status | invoice_date DESC | No |
| Paid | status = Paid | invoice_number, organization, total, payment_date | payment_date DESC | No |

---

## 6. Blueprint Templates (Optional Advanced Workflows)

### 6.1 Deal Approval Blueprint
**Module:** deals
**Field:** stage

**Transitions:**
| From | To | Requirements | Actions |
|------|-----|--------------|---------|
| Qualification → Proposal | Approval from manager if amount > 50000 | Proposal document attached | Create task for proposal follow-up |
| Proposal → Negotiation | Customer requirements documented | - | Send notification to sales manager |
| Negotiation → Closed Won | Signed contract attached, Approval if amount > 100000 | - | Create invoice, Send welcome email |
| Any → Closed Lost | Reason for loss required | - | Create follow-up task in 6 months |

### 6.2 Case Escalation Blueprint
**Module:** cases
**Field:** status

**Transitions:**
| From | To | Conditions | Requirements | Actions |
|------|-----|------------|--------------|---------|
| New → Open | - | - | - | Start SLA timer |
| Open → In Progress | - | - | - | - |
| In Progress → Waiting on Customer | - | Note explaining what's needed | - | Send email to customer |
| Waiting on Customer → In Progress | - | - | - | - |
| Any → Resolved | - | Resolution notes required | - | Send satisfaction survey |
| Resolved → Closed | 7 days after resolution OR customer confirms | - | - | - |

**SLA Rules:**
- New: Must move to Open within 1 hour
- Open: Must move to In Progress within 4 hours
- In Progress: Must be resolved within 24 hours (based on priority)
- Escalation: If SLA breached, auto-escalate to manager

### 6.3 Invoice Workflow Blueprint
**Module:** invoices
**Field:** status

**Transitions:**
| From | To | Requirements | Actions |
|------|-----|--------------|---------|
| Draft → Sent | Line items added, Total > 0 | - | Send invoice email to customer |
| Sent → Paid | Payment received | Payment amount, Payment date, Payment method | Record payment, Update deal revenue |
| Sent → Overdue | Auto-transition when due_date passed | - | Send reminder email |
| Overdue → Paid | Payment received | Payment details | Record payment |
| Any → Cancelled | Cancellation reason | - | Notify customer, Reverse any related records |
| Paid → Refunded | Refund reason, Approval required | - | Process refund, Send confirmation |

---

## 7. Implementation Notes

### Seeder Structure
Create separate seeders for each component that can be run independently:

```
database/seeders/tenant/
├── DefaultModulesSeeder.php      # All 11 core modules with fields
├── DefaultPipelinesSeeder.php    # 5 kanban pipelines
├── DefaultReportsSeeder.php      # 25+ standard reports
├── DefaultDashboardsSeeder.php   # 5 dashboards with widgets
├── DefaultViewsSeeder.php        # Saved table filters/views
├── DefaultBlueprintsSeeder.php   # Optional workflow blueprints
└── TenantDefaultDataSeeder.php   # Orchestrator that calls all above
```

### Configuration Options
Allow tenants to choose which defaults to install:
- **Starter**: Contacts, Organizations, Tasks only
- **Sales**: + Deals, Quotes, Products, Sales Pipeline, Sales Dashboard
- **Support**: + Cases, Support Pipeline, Support Dashboard
- **Full**: Everything

### Sample Data (Optional)
For demo/trial tenants, also include:
- 50 sample organizations (realistic company names)
- 100 sample contacts
- 30 sample deals at various stages
- 50 sample tasks
- 20 sample cases

### Field Sync
After creating modules with select fields, sync:
1. Stage fields with their respective pipelines
2. Status fields with any associated blueprints
3. Lookup fields with their target modules

### Migration Considerations
- All module/field IDs should use `id` not hardcoded values
- Use `firstOrCreate` patterns to allow re-running seeders
- Store API names consistently (snake_case)
- Include proper `display_order` values for UI consistency

---

## 8. Future Enhancements

### Additional Modules to Consider
- Campaigns (marketing)
- Vendors/Suppliers
- Contracts
- Projects
- Time Entries
- Expenses
- Knowledge Base Articles

### Additional Reports
- Forecast reports
- Cohort analysis
- Customer lifetime value
- Churn prediction
- Activity heatmaps

### Additional Dashboards
- Marketing dashboard
- Project dashboard
- Personal productivity dashboard
- Mobile-optimized dashboard

### Integrations
- Email sync (Gmail, Outlook)
- Calendar sync
- Document storage (Google Drive, Dropbox)
- Communication (Slack, Teams)
- Accounting (QuickBooks, Xero)
