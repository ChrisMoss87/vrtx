<?php

namespace App\Services\TeamChat;

use App\Models\TeamChatConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamsService
{
    protected TeamChatConnection $connection;
    protected string $graphUrl = 'https://graph.microsoft.com/v1.0';

    public function __construct(TeamChatConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Send a message to a channel
     */
    public function postMessage(string $teamId, string $channelId, string $content, array $attachments = []): array
    {
        $token = $this->connection->getDecryptedAccessToken();

        $data = [
            'body' => [
                'contentType' => 'html',
                'content' => $content,
            ],
        ];

        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        }

        try {
            $response = Http::withToken($token)
                ->post("{$this->graphUrl}/teams/{$teamId}/channels/{$channelId}/messages", $data);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'message_id' => $result['id'] ?? null,
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'error_code' => $error['error']['code'] ?? 'UNKNOWN',
                'error_message' => $error['error']['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('Teams post message failed', [
                'connection_id' => $this->connection->id,
                'team' => $teamId,
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
     * Post to incoming webhook
     */
    public function postToWebhook(string $webhookUrl, string $title, string $text, ?array $facts = null): array
    {
        $card = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'themeColor' => '0076D7',
            'summary' => $title,
            'sections' => [
                [
                    'activityTitle' => $title,
                    'text' => $text,
                ],
            ],
        ];

        if ($facts) {
            $card['sections'][0]['facts'] = $facts;
        }

        try {
            $response = Http::post($webhookUrl, $card);

            if ($response->successful()) {
                return ['success' => true];
            }

            return [
                'success' => false,
                'error_code' => 'HTTP_ERROR',
                'error_message' => $response->body(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error_code' => 'EXCEPTION',
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List teams
     */
    public function listTeams(): array
    {
        $token = $this->connection->getDecryptedAccessToken();

        try {
            $response = Http::withToken($token)
                ->get("{$this->graphUrl}/me/joinedTeams");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'teams' => $result['value'] ?? [],
                ];
            }

            return [
                'success' => false,
                'teams' => [],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'teams' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List channels in a team
     */
    public function listChannels(string $teamId): array
    {
        $token = $this->connection->getDecryptedAccessToken();

        try {
            $response = Http::withToken($token)
                ->get("{$this->graphUrl}/teams/{$teamId}/channels");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'channels' => $result['value'] ?? [],
                ];
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
     * Verify connection
     */
    public function verifyConnection(): array
    {
        $token = $this->connection->getDecryptedAccessToken();

        try {
            $response = Http::withToken($token)
                ->get("{$this->graphUrl}/me");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'user_id' => $result['id'],
                    'display_name' => $result['displayName'],
                    'email' => $result['mail'] ?? $result['userPrincipalName'],
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to verify connection',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Refresh access token
     */
    public function refreshToken(): array
    {
        $refreshToken = $this->connection->getDecryptedRefreshToken();
        if (!$refreshToken) {
            return [
                'success' => false,
                'error' => 'No refresh token available',
            ];
        }

        $settings = $this->connection->settings ?? [];
        $clientId = $settings['client_id'] ?? config('services.microsoft.client_id');
        $clientSecret = $settings['client_secret'] ?? config('services.microsoft.client_secret');

        try {
            $response = Http::asForm()->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            if ($response->successful()) {
                $result = $response->json();

                $this->connection->update([
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'] ?? $refreshToken,
                    'token_expires_at' => now()->addSeconds($result['expires_in']),
                ]);

                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => 'Failed to refresh token',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build adaptive card for rich messages
     */
    public function buildAdaptiveCard(string $title, string $text, ?string $url = null, ?array $facts = null): array
    {
        $card = [
            'contentType' => 'application/vnd.microsoft.card.adaptive',
            'content' => [
                '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                'type' => 'AdaptiveCard',
                'version' => '1.4',
                'body' => [
                    [
                        'type' => 'TextBlock',
                        'text' => $title,
                        'weight' => 'bolder',
                        'size' => 'medium',
                    ],
                    [
                        'type' => 'TextBlock',
                        'text' => $text,
                        'wrap' => true,
                    ],
                ],
            ],
        ];

        if ($facts) {
            $card['content']['body'][] = [
                'type' => 'FactSet',
                'facts' => array_map(function ($fact) {
                    return [
                        'title' => $fact['label'],
                        'value' => $fact['value'],
                    ];
                }, $facts),
            ];
        }

        if ($url) {
            $card['content']['actions'] = [
                [
                    'type' => 'Action.OpenUrl',
                    'title' => 'View in CRM',
                    'url' => $url,
                ],
            ];
        }

        return [$card];
    }
}
