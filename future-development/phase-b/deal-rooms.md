# B4: Deal Rooms (Collaborative Deal Spaces)

## Overview

Dedicated collaboration spaces where sales teams and prospects can share documents, track mutual action plans, and communicate in real-time throughout the sales process.

## User Stories

1. As a sales rep, I want to create a dedicated space for each deal where the prospect can access relevant information
2. As a prospect, I want to see a clear action plan and track progress with the vendor
3. As a sales manager, I want visibility into prospect engagement within deal rooms
4. As a team member, I want to collaborate with colleagues on deal strategy

## Feature Requirements

### Core Functionality
- [ ] Dedicated room per deal with unique URL
- [ ] Mutual action plan with shared tasks
- [ ] Document sharing with version control
- [ ] Real-time messaging between parties
- [ ] Activity feed showing all interactions
- [ ] Stakeholder management
- [ ] Engagement analytics (who viewed what, when)
- [ ] Customizable branding

### Room Sections
- **Overview** - Deal summary, key dates, stakeholders
- **Action Plan** - Mutual tasks and milestones
- **Documents** - Proposals, contracts, resources
- **Messages** - Chat between parties
- **Timeline** - Activity history

### Access Control
- Internal team members (full access)
- External stakeholders (configurable access)
- View-only guests
- Expiration dates for access

### Engagement Tracking
- Document views and time spent
- Action plan progress
- Message response times
- Last activity timestamps

## Technical Requirements

### Database Schema

```sql
CREATE TABLE deal_rooms (
    id SERIAL PRIMARY KEY,
    deal_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    status VARCHAR(20) DEFAULT 'active', -- active, won, lost, archived
    branding JSONB DEFAULT '{}',
    settings JSONB DEFAULT '{}',
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE deal_room_members (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES deal_rooms(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id), -- null for external
    external_email VARCHAR(255),
    external_name VARCHAR(255),
    role VARCHAR(50) NOT NULL, -- 'owner', 'team', 'stakeholder', 'viewer'
    access_token VARCHAR(100), -- for external access
    token_expires_at TIMESTAMP,
    last_accessed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE deal_room_action_items (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES deal_rooms(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INTEGER, -- member id
    assigned_party VARCHAR(20), -- 'seller', 'buyer'
    due_date DATE,
    status VARCHAR(20) DEFAULT 'pending', -- pending, in_progress, completed
    display_order INTEGER DEFAULT 0,
    completed_at TIMESTAMP,
    completed_by INTEGER,
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE deal_room_documents (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES deal_rooms(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INTEGER,
    mime_type VARCHAR(100),
    version INTEGER DEFAULT 1,
    description TEXT,
    is_visible_to_external BOOLEAN DEFAULT true,
    uploaded_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE deal_room_document_views (
    id SERIAL PRIMARY KEY,
    document_id INTEGER REFERENCES deal_rooms(id) ON DELETE CASCADE,
    member_id INTEGER REFERENCES deal_room_members(id),
    viewed_at TIMESTAMP DEFAULT NOW(),
    time_spent_seconds INTEGER
);

CREATE TABLE deal_room_messages (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES deal_rooms(id) ON DELETE CASCADE,
    member_id INTEGER REFERENCES deal_room_members(id),
    message TEXT NOT NULL,
    attachments JSONB DEFAULT '[]',
    is_internal BOOLEAN DEFAULT false, -- internal team only
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE deal_room_activities (
    id SERIAL PRIMARY KEY,
    room_id INTEGER REFERENCES deal_rooms(id) ON DELETE CASCADE,
    member_id INTEGER REFERENCES deal_room_members(id),
    activity_type VARCHAR(50) NOT NULL,
    activity_data JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### Backend Components

**Services:**
- `DealRoomService` - Room management
- `DealRoomAccessService` - Manage members, generate tokens
- `DealRoomNotificationService` - Email notifications

**API Endpoints:**
```
# Internal (authenticated)
GET    /api/v1/deal-rooms                 # List rooms
GET    /api/v1/deal-rooms/{id}            # Get room details
POST   /api/v1/deal-rooms                 # Create room
PUT    /api/v1/deal-rooms/{id}            # Update room
DELETE /api/v1/deal-rooms/{id}            # Delete room

# Members
GET    /api/v1/deal-rooms/{id}/members
POST   /api/v1/deal-rooms/{id}/members    # Invite member
DELETE /api/v1/deal-rooms/{id}/members/{memberId}

# Action Items
GET    /api/v1/deal-rooms/{id}/actions
POST   /api/v1/deal-rooms/{id}/actions
PUT    /api/v1/deal-rooms/{id}/actions/{actionId}
DELETE /api/v1/deal-rooms/{id}/actions/{actionId}

# Documents
GET    /api/v1/deal-rooms/{id}/documents
POST   /api/v1/deal-rooms/{id}/documents
DELETE /api/v1/deal-rooms/{id}/documents/{docId}

# Messages
GET    /api/v1/deal-rooms/{id}/messages
POST   /api/v1/deal-rooms/{id}/messages

# Analytics
GET    /api/v1/deal-rooms/{id}/analytics

# Public (token-based)
GET    /rooms/{slug}?token={accessToken}  # External access
POST   /rooms/{slug}/actions/{id}/complete
POST   /rooms/{slug}/messages
```

### Frontend Components

**New Components:**
- `DealRoomDashboard.svelte` - List of rooms
- `DealRoom.svelte` - Main room interface
- `RoomOverview.svelte` - Summary and stakeholders
- `ActionPlan.svelte` - Mutual action items
- `DocumentLibrary.svelte` - Document management
- `RoomChat.svelte` - Real-time messaging
- `RoomTimeline.svelte` - Activity feed
- `InviteMemberModal.svelte` - Add stakeholders
- `EngagementAnalytics.svelte` - View metrics
- `RoomBrandingEditor.svelte` - Customize appearance

**Public Components:**
- `PublicDealRoom.svelte` - External stakeholder view

**New Routes:**
- `/deal-rooms` - Room dashboard
- `/deal-rooms/{id}` - Room detail (internal)
- `/rooms/{slug}` - Public room access

## UI/UX Design

### Deal Room (Internal View)
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🤝 Deal Room: Acme Corp Enterprise Deal                            │
│ [Overview] [Action Plan] [Documents] [Messages] [Analytics]        │
├─────────────────────────────────────────────────────────────────────┤
│ ┌─────────────────────────────────────┐ ┌─────────────────────────┐ │
│ │ Action Plan Progress                │ │ Stakeholders            │ │
│ │ ████████████░░░░░░░░░ 60%          │ │                         │ │
│ │                                     │ │ 👤 John (You) - Owner   │ │
│ │ ✅ Send proposal          Jan 10   │ │ 👤 Sarah - Team         │ │
│ │ ✅ Technical demo         Jan 12   │ │ 👥 Mike@acme - Buyer    │ │
│ │ ⏳ Security review        Jan 18   │ │ 👥 Lisa@acme - Buyer    │ │
│ │ ○ Legal review            Jan 22   │ │                         │ │
│ │ ○ Contract signing        Jan 28   │ │ [+ Invite]              │ │
│ │                                     │ └─────────────────────────┘ │
│ │ [+ Add Action Item]                 │ ┌─────────────────────────┐ │
│ └─────────────────────────────────────┘ │ Recent Activity         │ │
│                                         │                         │ │
│ ┌─────────────────────────────────────┐ │ • Mike viewed proposal  │ │
│ │ Documents                           │ │   2 hours ago (5 min)   │ │
│ │                                     │ │ • Lisa joined room      │ │
│ │ 📄 Proposal v2.pdf     [3 views]   │ │   Yesterday             │ │
│ │ 📄 Security Docs.pdf   [1 view]    │ │ • Action completed      │ │
│ │ 📄 Pricing Sheet.xlsx  [5 views]   │ │   "Technical demo"      │ │
│ │                                     │ │                         │ │
│ │ [+ Upload Document]                 │ └─────────────────────────┘ │
│ └─────────────────────────────────────┘                             │
└─────────────────────────────────────────────────────────────────────┘
```

### External Stakeholder View
```
┌─────────────────────────────────────────────────────────────────────┐
│ 🏢 VRTX Inc                                                        │
│ Acme Corp Enterprise Partnership                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  Welcome, Mike! Here's everything you need for our partnership.    │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │ 📋 Our Mutual Action Plan                    60% Complete   │   │
│  │                                                             │   │
│  │ ✅ You: Review proposal                           Done     │   │
│  │ ✅ VRTX: Technical demonstration                  Done     │   │
│  │ ⏳ You: Complete security questionnaire    Due Jan 18 ⚠️   │   │
│  │ ○ You: Legal review                        Due Jan 22      │   │
│  │ ○ Both: Contract signing                   Due Jan 28      │   │
│  │                                                             │   │
│  │ [Mark Complete: Security questionnaire]                     │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  📁 Documents                                                      │
│  ├── 📄 Proposal - Enterprise License v2.pdf                       │
│  ├── 📄 Security & Compliance Documentation.pdf                   │
│  └── 📄 Pricing Breakdown.xlsx                                     │
│                                                                     │
│  💬 Have questions? [Send Message to John]                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## Testing Requirements

- [ ] Test room creation and setup
- [ ] Test external access with tokens
- [ ] Test action plan completion
- [ ] Test document upload/view tracking
- [ ] Test real-time messaging
- [ ] Test analytics calculation
- [ ] E2E test buyer journey

## Success Metrics

- Deal rooms created per month
- External stakeholder engagement rate
- Action plan completion rate
- Document view counts
- Correlation with win rate
