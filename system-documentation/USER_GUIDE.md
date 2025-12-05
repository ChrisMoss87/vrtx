# VRTX CRM User Guide

## Table of Contents
1. [Getting Started](#getting-started)
2. [Navigation](#navigation)
3. [Modules & Records](#modules--records)
4. [Pipelines & Kanban](#pipelines--kanban)
5. [Reports & Dashboards](#reports--dashboards)
6. [Workflows & Automation](#workflows--automation)
7. [Import & Export](#import--export)
8. [Settings & Configuration](#settings--configuration)

---

## Getting Started

### Logging In
1. Navigate to your organization's VRTX URL (e.g., `acme.vrtx.local`)
2. Enter your email and password
3. Click "Login"

### Dashboard Overview
After logging in, you'll see the main dashboard with:
- Quick stats and KPIs
- Recent activities
- Upcoming tasks
- Favorite reports

---

## Navigation

### Sidebar
The left sidebar provides access to all main features:
- **Dashboard** - Main overview
- **Modules** - All CRM modules (Contacts, Companies, Deals, etc.)
- **CRM** - Quick access to core modules
- **Pipelines** - Visual deal management
- **Workflows** - Automation rules
- **Reports** - Analytics and reporting
- **Dashboards** - Custom dashboards
- **Settings** - System configuration

### Quick Actions
- Use the **+** button to quickly create records
- Use **Cmd/Ctrl + K** to open global search

---

## Modules & Records

### Viewing Records
1. Click on any module in the sidebar (e.g., "Contacts")
2. Use the DataTable to browse records
3. Use filters to narrow results
4. Click column headers to sort

### Creating Records
1. Click "Create [Module Name]" button
2. Fill in the required fields (marked with *)
3. Click "Save" or "Save & New"

### Editing Records
1. Click on a record to open it
2. Modify any editable field
3. Changes auto-save or click "Save"

### DataTable Features
- **Search**: Use the search box to find records
- **Filters**: Click "Filters" to add advanced filters
- **Columns**: Toggle visible columns
- **Views**: Save and switch between different views
- **Bulk Actions**: Select multiple records for bulk operations

### Field Types
VRTX supports many field types:
- Text, Email, Phone, URL
- Number, Currency, Percent
- Date, DateTime, Time
- Select, Multi-select, Radio
- Checkbox, Switch
- Lookup (related records)
- File upload, Image
- Rich text editor
- And more...

---

## Pipelines & Kanban

### Viewing Pipelines
1. Go to **Pipelines** in the sidebar
2. Select a pipeline from the dropdown

### Kanban Board
- Each column represents a stage
- Cards represent deals/opportunities
- Drag cards between stages to update status

### Managing Deals
- Click a card to view details
- Drag cards to change stage
- Use quick actions menu (⋮) for common operations

### Pipeline Analytics
- View pipeline value by stage
- Track conversion rates
- Monitor sales velocity

---

## Reports & Dashboards

### Creating Reports
1. Go to **Reports** > "Create Report"
2. Select a module
3. Choose report type (Table, Chart, Summary)
4. Select fields to include
5. Add filters and grouping
6. Save the report

### Report Types
- **Table**: Detailed data view
- **Chart**: Visual representations (bar, line, pie)
- **Summary**: Aggregated metrics

### Dashboards
1. Go to **Dashboards** > "Create Dashboard"
2. Add widgets (KPIs, charts, tables)
3. Arrange widgets by dragging
4. Configure each widget's data source

### Sharing Reports
- Make reports public for all users
- Schedule email delivery
- Export to CSV or JSON

---

## Workflows & Automation

### Creating Workflows
1. Go to **Workflows** > "Create Workflow"
2. Configure the trigger (when to run)
3. Add conditions (optional filters)
4. Define actions (what to do)
5. Activate the workflow

### Trigger Types
- **Record Created**: When new records are added
- **Record Updated**: When records are modified
- **Field Changed**: When specific fields change
- **Time-based**: Scheduled execution
- **Manual**: Triggered by user

### Action Types
- Send email
- Create/update records
- Update fields
- Send webhook
- Assign to user
- Add/remove tags
- Create tasks

### Workflow Monitoring
- View execution history
- Debug failed workflows
- Test workflows with sample data

---

## Import & Export

### Importing Data
1. Go to a module's records page
2. Click "Import"
3. Upload CSV or Excel file
4. Map columns to fields
5. Preview and validate
6. Execute import

### Import Tips
- Use the template download for correct format
- Ensure required fields are mapped
- Preview data before importing
- Check for duplicates

### Exporting Data
1. Go to a module's records page
2. Click "Export"
3. Select fields to export
4. Apply filters (optional)
5. Choose format (CSV, Excel, JSON)
6. Download file

---

## Settings & Configuration

### Module Settings
- **Module Order**: Drag to reorder modules in sidebar
- **Module Status**: Enable/disable modules

### Roles & Permissions
- Create custom roles
- Assign permissions per module
- Control field-level access

### User Management
- Add/remove users
- Assign roles
- Manage user profiles

### Email Configuration
- Connect email accounts
- Configure SMTP settings
- Set up email templates

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| Cmd/Ctrl + K | Global search |
| Cmd/Ctrl + N | New record |
| Cmd/Ctrl + S | Save |
| Escape | Close modal/cancel |
| Enter | Submit form |

---

## Getting Help

- **Documentation**: Access full docs from the help menu
- **Support**: Contact your administrator
- **Feedback**: Report issues at github.com/anthropics/claude-code/issues

---

*VRTX CRM - Built with Svelte, Laravel, and PostgreSQL*
