<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TwilioService
{
    protected SmsConnection $connection;
    protected string $baseUrl = 'https://api.twilio.com/2010-04-01';

    public function __construct(SmsConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Send an SMS message
     */
    public function sendMessage(string $to, string $body, ?string $from = null): array
    {
        $accountSid = $this->connection->account_sid;
        $authToken = $this->connection->getDecryptedAuthToken();
        $fromNumber = $from ?? $this->connection->phone_number;

        $data = [
            'To' => $to,
            'From' => $fromNumber,
            'Body' => $body,
        ];

        // Use messaging service if configured
        if ($this->connection->messaging_service_sid) {
            $data['MessagingServiceSid'] = $this->connection->messaging_service_sid;
            unset($data['From']);
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("{$this->baseUrl}/Accounts/{$accountSid}/Messages.json", $data);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'message_sid' => $result['sid'],
                    'status' => $result['status'],
                    'segments' => $result['num_segments'] ?? 1,
                    'price' => $result['price'] ?? null,
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'error_code' => $error['code'] ?? 'UNKNOWN',
                'error_message' => $error['message'] ?? 'Unknown error',
            ];
        } catch (\Exception $e) {
            Log::error('Twilio SMS send failed', [
                'connection_id' => $this->connection->id,
                'to' => $to,
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
     * Send MMS with media
     */
    public function sendMms(string $to, string $body, array $mediaUrls, ?string $from = null): array
    {
        $accountSid = $this->connection->account_sid;
        $authToken = $this->connection->getDecryptedAuthToken();
        $fromNumber = $from ?? $this->connection->phone_number;

        $data = [
            'To' => $to,
            'From' => $fromNumber,
            'Body' => $body,
        ];

        foreach ($mediaUrls as $url) {
            $data['MediaUrl'] = $url;
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->asForm()
                ->post("{$this->baseUrl}/Accounts/{$accountSid}/Messages.json", $data);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'message_sid' => $result['sid'],
                    'status' => $result['status'],
                ];
            }

            $error = $response->json();
            return [
                'success' => false,
                'error_code' => $error['code'] ?? 'UNKNOWN',
                'error_message' => $error['message'] ?? 'Unknown error',
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
     * Get message status
     */
    public function getMessageStatus(string $messageSid): array
    {
        $accountSid = $this->connection->account_sid;
        $authToken = $this->connection->getDecryptedAuthToken();

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->get("{$this->baseUrl}/Accounts/{$accountSid}/Messages/{$messageSid}.json");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'status' => $result['status'],
                    'error_code' => $result['error_code'] ?? null,
                    'error_message' => $result['error_message'] ?? null,
                    'price' => $result['price'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error_message' => 'Failed to fetch message status',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify connection credentials
     */
    public function verifyCredentials(): array
    {
        $accountSid = $this->connection->account_sid;
        $authToken = $this->connection->getDecryptedAuthToken();

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->get("{$this->baseUrl}/Accounts/{$accountSid}.json");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'account_name' => $result['friendly_name'] ?? null,
                    'account_status' => $result['status'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error_message' => 'Invalid credentials',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Look up phone number info
     */
    public function lookupNumber(string $phoneNumber): array
    {
        $accountSid = $this->connection->account_sid;
        $authToken = $this->connection->getDecryptedAuthToken();

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->get("https://lookups.twilio.com/v2/PhoneNumbers/{$phoneNumber}");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'valid' => $result['valid'] ?? false,
                    'country_code' => $result['country_code'] ?? null,
                    'national_format' => $result['national_format'] ?? null,
                    'phone_number' => $result['phone_number'] ?? null,
                ];
            }

            return [
                'success' => false,
                'valid' => false,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'error_message' => $e->getMessage(),
            ];
        }
    }
}
