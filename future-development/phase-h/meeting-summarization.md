# H3: Meeting Summarization

## Overview
AI-powered meeting transcription and summarization with action item extraction and CRM data enrichment.

## Key Features
- Audio/video transcription
- Meeting summary generation
- Action item extraction
- Key topics identification
- Speaker attribution
- Follow-up suggestions
- Auto-populate CRM fields
- Search across meetings

## AI Capabilities
- Transcribe recording (Whisper, etc.)
- Generate concise summary
- Extract commitments and next steps
- Identify objections and concerns
- Suggest CRM field updates

## Technical Requirements
- Audio transcription service
- LLM for summarization
- Speaker diarization
- Recording storage

## Database Additions
```sql
CREATE TABLE meeting_transcripts (id, meeting_id, raw_transcript, speakers);
CREATE TABLE meeting_summaries (id, transcript_id, summary, action_items, key_topics);
CREATE TABLE extracted_insights (id, summary_id, insight_type, content, confidence);
```

## Components
- `MeetingSummary.svelte`
- `TranscriptViewer.svelte`
- `ActionItemExtractor.svelte`
- `InsightPanel.svelte`
