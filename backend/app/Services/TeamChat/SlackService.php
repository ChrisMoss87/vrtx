<?php

namespace App\Services\TeamChat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SlackService
{
    protected TeamChatConnection $connection;
    protected string $baseUrl = 'https://slack.com/api';

    public function __construct(TeamChatConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Post a message to a channel
     */
    public function postMessage(string $channelId, string $text, array $blocks = []): array
    {
        $token = $this->connection->getDecryptedBotToken() ?? $this->connection->getDecryptedAccessToken();

        $data = [
            'channel' => $channelId,
            'text' => $text,
        ];

        if (!empty($blocks)) {
            $data['blocks'] = $blocks;
        }

        try {
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/chat.postMessage", $data);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['ok']) {
                    return [
                        'success' => true,
                        'message_ts' => $result['ts'],
                        'channel' => $result['channel'],
                    ];
                }
                return [
                    'success' => false,
                    'error_code' => $result['error'] ?? 'UNKNOWN',
                    'error_message' => $result['error'] ?? 'Unknown error',
                ];
            }

            return [
                'success' => false,
                'error_code' => 'HTTP_ERROR',
                'error_message' => 'HTTP request failed',
            ];
        } catch (\Exception $e) {
            Log::error('Slack post message failed', [
                'connection_id' => $this->connection->id,
                'channel' => $channelId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List channels
     */
    public function listChannels(bool $includePrivate = false): array
    {
        $token = $this->connection->getDecryptedBotToken() ?? $this->connection->getDecryptedAccessToken();

        $types = $includePrivate ? 'public_channel,private_channel' : 'public_channel';

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/conversations.list", [
                    'types' => $types,
                    'exclude_archived' => true,
                    'limit' => 1000,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['ok']) {
                    return [
                        'success' => true,
                        'channels' => $result['channels'] ?? [],
                    ];
                }
            }

            return [
                'success' => false,
                'channels' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'channels' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List users in workspace
     */
    public function listUsers(): array
    {
        $token = $this->connection->getDecryptedBotToken() ?? $this->connection->getDecryptedAccessToken();

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/users.list", [
                    'limit' => 1000,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if ($result['ok']) {
                    return [
                        'success' => true,
                        'users' => collect($result['members'] ?? [])->filter(function ($user) {
                            return !($user['deleted'] ?? false) && !($user['is_bot'] ?? false);
                        })->values()->toArray(),
                    ];
                }
            }

            return [
                'success' => false,
                'users' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'users' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify connection (test auth)
     */
    public function verifyConnection(): array
    {
        $token = $this->connection->getDecryptedBotToken() ?? $this->connection->getDecryptedAccessToken();

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/auth.test");

            if ($response->successful()) {
                $result = $response->json();
                if ($result['ok']) {
                    return [
                        'success' => true,
                        'team_id' => $result['team_id'],
                        'team_name' => $result['team'],
                        'user_id' => $result['user_id'],
                        'bot_id' => $result['bot_id'] ?? null,
                    ];
                }
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }

            return [
                'success' => false,
                'error' => 'HTTP request failed',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build rich message blocks
     */
    public function buildBlocks(string $title, string $text, ?string $url = null, ?array $fields = null): array
    {
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => $title,
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ],
        ];

        if ($fields) {
            $blocks[] = [
                'type' => 'section',
                'fields' => array_map(function ($field) {
                    return [
                        'type' => 'mrkdwn',
                        'text' => "*{$field['label']}*\n{$field['value']}",
                    ];
                }, $fields),
            ];
        }

        if ($url) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'View in CRM',
                            'emoji' => true,
                        ],
                        'url' => $url,
                        'action_id' => 'view_record',
                    ],
                ],
            ];
        }

        return $blocks;
    }
}
