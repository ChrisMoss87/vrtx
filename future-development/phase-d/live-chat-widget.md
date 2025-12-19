# D1: Live Chat Widget

## Overview
Embeddable live chat widget for websites that connects visitors to CRM and routes conversations to sales/support teams.

## Key Features
- Embeddable JavaScript widget
- Real-time messaging
- Visitor identification and tracking
- Automatic contact creation
- Conversation routing rules
- Chat history on contact records
- Offline message capture
- Canned responses
- File sharing

## Technical Requirements
- WebSocket-based real-time messaging
- Visitor fingerprinting
- IP geolocation
- Chat assignment rules
- Agent availability status

## Database Additions
```sql
CREATE TABLE chat_widgets (id, settings, styling, routing_rules);
CREATE TABLE chat_conversations (id, visitor_id, contact_id, agent_id, status);
CREATE TABLE chat_messages (id, conversation_id, sender_type, content, attachments);
CREATE TABLE chat_visitors (id, fingerprint, ip, location, pages_viewed);
```

## Components
- `ChatWidgetBuilder.svelte`
- `ChatInbox.svelte`
- `ChatConversation.svelte`
- `CannedResponseManager.svelte`
