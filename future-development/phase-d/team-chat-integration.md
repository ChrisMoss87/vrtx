# D4: Team Chat Integration

## Overview
Integration with Slack and Microsoft Teams for CRM notifications, deal updates, and collaborative actions.

## Key Features
- Slack/Teams app installation
- Deal notifications to channels
- Mention alerts
- Quick actions from chat
- Deal cards in messages
- Channel per deal option
- Activity digests
- Slash commands

## Integration Features
- `/crm search <query>` - Search CRM from chat
- `/crm deal <id>` - Show deal card
- `/crm update <id> <field> <value>` - Update from chat
- Automatic notifications on stage changes

## Database Additions
```sql
CREATE TABLE chat_integrations (id, provider, team_id, access_token);
CREATE TABLE chat_channel_links (id, channel_id, deal_id, settings);
CREATE TABLE chat_notification_rules (id, trigger, channel_id, template);
```

## Components
- `SlackConnector.svelte`
- `TeamsConnector.svelte`
- `ChannelLinkManager.svelte`
- `NotificationRuleBuilder.svelte`
