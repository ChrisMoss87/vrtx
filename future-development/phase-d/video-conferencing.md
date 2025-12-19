# D7: Video Conferencing Integration

## Overview
Integration with Zoom, Google Meet, and Microsoft Teams for scheduling, one-click joining, and meeting data sync.

## Key Features
- OAuth connection to video platforms
- Schedule meetings from CRM
- One-click meeting creation
- Meeting link auto-insertion in emails
- Post-meeting recording import
- Attendance tracking
- Meeting analytics

## Database Additions
```sql
CREATE TABLE video_connections (id, provider, user_id, access_token);
CREATE TABLE video_meetings (id, provider_meeting_id, deal_id, host_id, scheduled_at);
CREATE TABLE meeting_recordings (id, meeting_id, recording_url, transcript);
```

## Components
- `VideoConnector.svelte`
- `MeetingScheduler.svelte`
- `JoinMeetingButton.svelte`
- `MeetingRecordings.svelte`
