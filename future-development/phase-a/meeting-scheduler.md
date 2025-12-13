# A5: Meeting Scheduler

## Overview

Public scheduling links that let prospects book meetings directly on user calendars, with automatic CRM record creation and activity logging.

## User Stories

1. As a sales rep, I want to share a link where prospects can book time with me
2. As a user, I want to sync my availability from Google/Outlook calendar
3. As a prospect, I want to easily find and book available time slots
4. As a user, I want meetings auto-logged as activities on contact records

## Feature Requirements

### Core Functionality
- [ ] Personal scheduling pages (unique URL per user)
- [ ] Team scheduling (round-robin assignment)
- [ ] Calendar sync (Google Calendar, Outlook)
- [ ] Multiple meeting types with different durations
- [ ] Buffer time between meetings
- [ ] Availability rules (working hours, blocked times)
- [ ] Timezone detection for bookers
- [ ] Automatic reminder emails
- [ ] Reschedule/cancel by attendee
- [ ] Auto-create contact if new
- [ ] Log meeting as activity

### Meeting Type Options
- Duration (15, 30, 45, 60 min, custom)
- Location (Zoom, Google Meet, phone, in-person)
- Questions to ask before booking
- Confirmation page customization
- Calendar event details

### Scheduling Rules
- Available days of week
- Available hours per day
- Minimum scheduling notice
- Maximum days in advance
- Buffer before/after meetings
- Daily/weekly meeting limits

## Technical Requirements

### Database Schema

```sql
CREATE TABLE scheduling_pages (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    slug VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    timezone VARCHAR(50) NOT NULL,
    branding JSONB DEFAULT '{}',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE meeting_types (
    id SERIAL PRIMARY KEY,
    scheduling_page_id INTEGER REFERENCES scheduling_pages(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    duration_minutes INTEGER NOT NULL,
    description TEXT,
    location_type VARCHAR(50), -- 'zoom', 'google_meet', 'phone', 'in_person', 'custom'
    location_details TEXT,
    color VARCHAR(7),
    is_active BOOLEAN DEFAULT true,
    questions JSONB DEFAULT '[]',
    settings JSONB DEFAULT '{}',
    display_order INTEGER DEFAULT 0
);

CREATE TABLE availability_rules (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    day_of_week INTEGER, -- 0-6 (Sunday-Saturday)
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available BOOLEAN DEFAULT true
);

CREATE TABLE scheduling_overrides (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    date DATE NOT NULL,
    is_available BOOLEAN DEFAULT false,
    start_time TIME,
    end_time TIME,
    reason VARCHAR(255)
);

CREATE TABLE scheduled_meetings (
    id SERIAL PRIMARY KEY,
    meeting_type_id INTEGER REFERENCES meeting_types(id),
    host_user_id INTEGER REFERENCES users(id),
    contact_id INTEGER REFERENCES module_records(id),
    attendee_name VARCHAR(255) NOT NULL,
    attendee_email VARCHAR(255) NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    timezone VARCHAR(50) NOT NULL,
    location TEXT,
    notes TEXT,
    answers JSONB, -- answers to pre-meeting questions
    status VARCHAR(20) DEFAULT 'scheduled', -- 'scheduled', 'completed', 'cancelled', 'rescheduled'
    calendar_event_id VARCHAR(255), -- external calendar ID
    reminder_sent BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE calendar_connections (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    provider VARCHAR(50) NOT NULL, -- 'google', 'outlook'
    access_token TEXT NOT NULL,
    refresh_token TEXT,
    token_expires_at TIMESTAMP,
    calendar_id VARCHAR(255),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `SchedulingService` - Manage pages, meeting types
- `AvailabilityService` - Calculate available slots
- `CalendarSyncService` - Google/Outlook integration
- `MeetingBookingService` - Book meetings, send confirmations

**Jobs:**
- `SyncCalendarEventsJob` - Keep calendar in sync
- `SendMeetingReminderJob` - Send reminder emails

**API Endpoints:**
```
# Authenticated
GET    /api/v1/scheduling/pages           # List my scheduling pages
POST   /api/v1/scheduling/pages           # Create page
PUT    /api/v1/scheduling/pages/{id}      # Update page
DELETE /api/v1/scheduling/pages/{id}      # Delete page

GET    /api/v1/scheduling/meeting-types   # List meeting types
POST   /api/v1/scheduling/meeting-types   # Create meeting type
PUT    /api/v1/scheduling/meeting-types/{id}
DELETE /api/v1/scheduling/meeting-types/{id}

GET    /api/v1/scheduling/availability    # Get/set availability rules
PUT    /api/v1/scheduling/availability    # Update rules
POST   /api/v1/scheduling/overrides       # Add date override

GET    /api/v1/scheduling/meetings        # List my meetings
GET    /api/v1/calendar/connect/{provider} # OAuth flow

# Public (no auth)
GET    /schedule/{slug}                   # Scheduling page
GET    /schedule/{slug}/{meetingType}     # Specific meeting type
GET    /schedule/{slug}/{meetingType}/slots?date=2025-01-15
POST   /schedule/{slug}/{meetingType}/book
GET    /schedule/manage/{token}           # Reschedule/cancel
POST   /schedule/cancel/{token}
```

### Frontend Components

**New Components:**
- `SchedulingPageEditor.svelte` - Create/edit scheduling page
- `MeetingTypeEditor.svelte` - Configure meeting types
- `AvailabilityEditor.svelte` - Set working hours
- `CalendarConnect.svelte` - OAuth connection UI
- `ScheduledMeetingsList.svelte` - View upcoming meetings

**Public Components (no auth):**
- `PublicSchedulingPage.svelte` - Booking interface
- `TimeSlotPicker.svelte` - Select available time
- `BookingForm.svelte` - Enter details and confirm
- `BookingConfirmation.svelte` - Success page

**New Routes:**
- `/settings/scheduling` - Manage scheduling pages
- `/schedule/{slug}` - Public scheduling page

## UI/UX Design

### Public Booking Page
```
┌─────────────────────────────────────────────────────────────────────┐
│                    📅 Schedule a Meeting                            │
│                    with John Smith                                  │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Select a Meeting Type:                                            │
│  ┌─────────────────────┐ ┌─────────────────────┐                   │
│  │ 📞 Discovery Call   │ │ 💻 Product Demo     │                   │
│  │ 30 minutes          │ │ 60 minutes          │                   │
│  │ Phone call          │ │ Zoom meeting        │                   │
│  └─────────────────────┘ └─────────────────────┘                   │
│                                                                     │
│  Select a Date:                                                    │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │     January 2025                                            │   │
│  │  Su  Mo  Tu  We  Th  Fr  Sa                                 │   │
│  │            1   2   3   4                                    │   │
│  │   5   6   7   8   9  10  11                                 │   │
│  │  12  13 [14] 15  16  17  18   ← Selected date               │   │
│  │  19  20  21  22  23  24  25                                 │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  Available Times (EST):                                            │
│  [9:00 AM] [9:30 AM] [10:00 AM] [10:30 AM]                        │
│  [2:00 PM] [2:30 PM] [3:00 PM] [3:30 PM]                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Availability Editor
```
┌─────────────────────────────────────────────────────────────────────┐
│ Weekly Availability                                                 │
├─────────────────────────────────────────────────────────────────────┤
│ Monday    [✓] [09:00 ▼] to [17:00 ▼]  [+ Add hours]               │
│ Tuesday   [✓] [09:00 ▼] to [17:00 ▼]  [+ Add hours]               │
│ Wednesday [✓] [09:00 ▼] to [17:00 ▼]  [+ Add hours]               │
│ Thursday  [✓] [09:00 ▼] to [17:00 ▼]  [+ Add hours]               │
│ Friday    [✓] [09:00 ▼] to [12:00 ▼]  [+ Add hours]               │
│ Saturday  [ ] Unavailable                                          │
│ Sunday    [ ] Unavailable                                          │
├─────────────────────────────────────────────────────────────────────┤
│ Buffer Time: [15 ▼] minutes between meetings                       │
│ Minimum Notice: [4 ▼] hours before booking                         │
│ Max Days in Advance: [30 ▼] days                                   │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test availability calculation
- [ ] Test timezone handling
- [ ] Test calendar sync
- [ ] Test booking flow
- [ ] Test conflict detection
- [ ] Test reminder sending
- [ ] E2E test complete booking

## Success Metrics

- Meetings booked per month
- No-show rate
- Average booking lead time
- Calendar connection rate
