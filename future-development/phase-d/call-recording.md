# D6: Call Recording

## Overview
Integration with VoIP providers for call recording, transcription, and analysis linked to CRM records.

## Key Features
- VoIP integration (Twilio, RingCentral)
- Automatic call recording
- Recording playback in CRM
- AI transcription
- Call notes and tags
- Link to contacts/deals
- Call analytics
- Compliance controls

## Technical Requirements
- Telephony API integration
- Recording storage (S3)
- Transcription service (Whisper, etc.)
- Consent handling

## Database Additions
```sql
CREATE TABLE call_recordings (id, call_id, contact_id, recording_url, duration);
CREATE TABLE call_transcripts (id, recording_id, content, speakers);
CREATE TABLE call_analytics (id, recording_id, talk_ratio, sentiment, keywords);
```

## Components
- `CallRecordingPlayer.svelte`
- `CallTranscript.svelte`
- `CallAnalytics.svelte`
- `CallHistory.svelte`
