<?php

namespace App\Services\TeamChat;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TeamChatService
{
    /**
     * Send message to channel
     */
    public function sendMessage(
        TeamChatConnection $connection,
        string $channelId,
        string $content,
        ?array $attachments = null,
        ?int $recordId = null,
        ?string $moduleApiName = null,
        ?int $notificationId = null
    ): TeamChatMessage {
        // Create message record
        $message = DB::table('team_chat_messages')->insertGetId([
            'connection_id' => $connection->id,
            'channel_id' => DB::table('team_chat_channels')->where('connection_id', $connection->id)
                ->where('channel_id', $channelId)
                ->first()?->id,
            'notification_id' => $notificationId,
            'content' => $content,
            'attachments' => $attachments,
            'status' => 'pending',
            'module_record_id' => $recordId,
            'module_api_name' => $moduleApiName,
            'sent_by' => auth()->id(),
        ]);

        // Send via appropriate provider
        $result = $this->sendViaProvider($connection, $channelId, $content, $attachments);

        if ($result['success']) {
            $message->markAsSent($result['message_id'] ?? $result['message_ts'] ?? '');
        } else {
            $message->markAsFailed($result['error_code'] ?? 'UNKNOWN', $result['error_message'] ?? 'Unknown error');
        }

        return $message;
    }

    /**
     * Send via the appropriate provider
     */
    protected function sendViaProvider(
        TeamChatConnection $connection,
        string $channelId,
        string $content,
        ?array $attachments = null
    ): array {
        if ($connection->isSlack()) {
            $service = new SlackService($connection);
            return $service->postMessage($channelId, $content, $attachments ?? []);
        }

        if ($connection->isTeams()) {
            // For Teams, we need team ID and channel ID
            // If using webhook, send directly
            if ($connection->webhook_url) {
                $service = new TeamsService($connection);
                return $service->postToWebhook($connection->webhook_url, 'Notification', $content);
            }

            // Channel format should be "teamId:channelId"
            $parts = explode(':', $channelId, 2);
            if (count($parts) !== 2) {
                return [
                    'success' => false,
                    'error_code' => 'INVALID_CHANNEL',
                    'error_message' => 'Invalid Teams channel format',
                ];
            }

            $service = new TeamsService($connection);
            return $service->postMessage($parts[0], $parts[1], $content, $attachments ?? []);
        }

        return [
            'success' => false,
            'error_code' => 'UNSUPPORTED_PROVIDER',
            'error_message' => "Provider {$connection->provider} is not supported",
        ];
    }

    /**
     * Trigger notification for an event
     */
    public function triggerNotification(
        string $event,
        string $module,
        array $recordData,
        int $recordId
    ): void {
        $notifications = TeamChatNotification::active()
            ->byEvent($event)
            ->where(function ($q) use ($module) {
                $q->whereNull('trigger_module')
                  ->orWhere('trigger_module', $module);
            })
            ->with(['connection', 'channel'])
            ->get();

        foreach ($notifications as $notification) {
            // Check conditions
            if (!$notification->matchesConditions($recordData)) {
                continue;
            }

            // Render message
            $content = $notification->renderMessage($recordData);

            // Add mention if configured
            if ($notification->include_mentions && $notification->mention_field) {
                $userId = $recordData[$notification->mention_field] ?? null;
                if ($userId) {
                    $mention = $this->getUserMention($notification->connection_id, $userId);
                    if ($mention) {
                        $content = "{$mention} {$content}";
                    }
                }
            }

            // Get channel ID
            $channelId = $notification->channel?->channel_id;
            if (!$channelId) {
                continue;
            }

            try {
                $this->sendMessage(
                    connection: $notification->connection,
                    channelId: $channelId,
                    content: $content,
                    recordId: $recordId,
                    moduleApiName: $module,
                    notificationId: $notification->id
                );

                $notification->incrementTriggered();
            } catch (\Exception $e) {
                Log::error('Failed to trigger team chat notification', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get user mention format
     */
    public function getUserMention(int $connectionId, int $userId): ?string
    {
        $mapping = TeamChatUserMapping::findByUser($connectionId, $userId);
        return $mapping?->getMention();
    }

    /**
     * Sync channels from provider
     */
    public function syncChannels(TeamChatConnection $connection): array
    {
        $channels = [];

        if ($connection->isSlack()) {
            $service = new SlackService($connection);
            $result = $service->listChannels(true);

            if ($result['success']) {
                foreach ($result['channels'] as $channel) {
                    $channels[] = TeamChatChannel::updateOrCreate(
                        [
                            'connection_id' => $connection->id,
                            'channel_id' => $channel['id'],
                        ],
                        [
                            'name' => $channel['name'],
                            'description' => $channel['purpose']['value'] ?? null,
                            'is_private' => $channel['is_private'] ?? false,
                            'is_archived' => $channel['is_archived'] ?? false,
                            'member_count' => $channel['num_members'] ?? 0,
                        ]
                    );
                }
            }
        }

        if ($connection->isTeams()) {
            $service = new TeamsService($connection);
            $teamsResult = $service->listTeams();

            if ($teamsResult['success']) {
                foreach ($teamsResult['teams'] as $team) {
                    $channelsResult = $service->listChannels($team['id']);
                    if ($channelsResult['success']) {
                        foreach ($channelsResult['channels'] as $channel) {
                            $channels[] = TeamChatChannel::updateOrCreate(
                                [
                                    'connection_id' => $connection->id,
                                    'channel_id' => "{$team['id']}:{$channel['id']}",
                                ],
                                [
                                    'name' => "{$team['displayName']} / {$channel['displayName']}",
                                    'description' => $channel['description'] ?? null,
                                    'is_private' => $channel['membershipType'] === 'private',
                                ]
                            );
                        }
                    }
                }
            }
        }

        $connection->update(['last_synced_at' => now()]);

        return $channels;
    }

    /**
     * Sync users from provider
     */
    public function syncUsers(TeamChatConnection $connection): array
    {
        if (!$connection->isSlack()) {
            return [];
        }

        $service = new SlackService($connection);
        $result = $service->listUsers();

        $users = [];
        if ($result['success']) {
            foreach ($result['users'] as $user) {
                // Try to match with CRM user by email
                $crmUser = DB::table('users')->where('email', $user['profile']['email'] ?? '')->first();

                if ($crmUser) {
                    $users[] = TeamChatUserMapping::updateOrCreate(
                        [
                            'connection_id' => $connection->id,
                            'external_user_id' => $user['id'],
                        ],
                        [
                            'user_id' => $crmUser->id,
                            'external_username' => $user['name'] ?? $user['real_name'] ?? null,
                            'external_email' => $user['profile']['email'] ?? null,
                            'is_verified' => true,
                        ]
                    );
                }
            }
        }

        return $users;
    }
}
