<?php

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsConnection;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SmsWebhookController extends Controller
{
    protected SmsService $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Handle Twilio webhook for incoming messages and status updates
     */
    public function twilio(Request $request): Response
    {
        Log::info('Twilio webhook received', $request->all());

        // Find connection by phone number
        $toNumber = $request->input('To');
        $connection = SmsConnection::where('phone_number', $toNumber)
            ->where('provider', 'twilio')
            ->first();

        if (!$connection) {
            Log::warning('No connection found for Twilio webhook', ['to' => $toNumber]);
            return response('', 200);
        }

        // Handle incoming message
        if ($request->filled('Body')) {
            $this->smsService->processIncoming(
                connection: $connection,
                from: $request->input('From'),
                to: $toNumber,
                content: $request->input('Body'),
                providerMessageId: $request->input('MessageSid')
            );
        }

        // Handle status callback
        if ($request->filled('MessageStatus') && $request->filled('MessageSid')) {
            $this->smsService->updateMessageStatus(
                providerMessageId: $request->input('MessageSid'),
                status: $request->input('MessageStatus'),
                errorCode: $request->input('ErrorCode')
            );
        }

        // Return empty TwiML response
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    /**
     * Handle Vonage (Nexmo) webhook
     */
    public function vonage(Request $request): Response
    {
        Log::info('Vonage webhook received', $request->all());

        // Find connection
        $toNumber = $request->input('to');
        $connection = SmsConnection::where('phone_number', $toNumber)
            ->where('provider', 'vonage')
            ->first();

        if (!$connection) {
            return response('', 200);
        }

        // Handle incoming message
        if ($request->filled('text')) {
            $this->smsService->processIncoming(
                connection: $connection,
                from: $request->input('msisdn'),
                to: $toNumber,
                content: $request->input('text'),
                providerMessageId: $request->input('messageId')
            );
        }

        return response('', 200);
    }

    /**
     * Handle Vonage delivery receipt
     */
    public function vonageDeliveryReceipt(Request $request): Response
    {
        Log::info('Vonage delivery receipt', $request->all());

        $status = $request->input('status');
        $messageId = $request->input('messageId');

        if ($messageId && $status) {
            $statusMap = [
                'delivered' => 'delivered',
                'accepted' => 'sent',
                'buffered' => 'queued',
                'failed' => 'failed',
                'rejected' => 'failed',
            ];

            $this->smsService->updateMessageStatus(
                providerMessageId: $messageId,
                status: $statusMap[$status] ?? $status,
                errorCode: $request->input('err-code')
            );
        }

        return response('', 200);
    }

    /**
     * Handle MessageBird webhook
     */
    public function messagebird(Request $request): Response
    {
        Log::info('MessageBird webhook received', $request->all());

        // MessageBird sends different payloads for different events
        $type = $request->input('type');

        if ($type === 'message.created') {
            // Incoming message
            $payload = $request->input('message', []);
            $recipient = $payload['recipients']['items'][0] ?? null;

            if ($recipient) {
                $connection = SmsConnection::where('phone_number', $payload['originator'])
                    ->where('provider', 'messagebird')
                    ->first();

                if ($connection) {
                    $this->smsService->processIncoming(
                        connection: $connection,
                        from: $recipient,
                        to: $payload['originator'],
                        content: $payload['body'],
                        providerMessageId: $payload['id']
                    );
                }
            }
        } elseif ($type === 'message.updated') {
            // Status update
            $payload = $request->input('message', []);
            $status = $payload['status'] ?? null;
            $messageId = $payload['id'] ?? null;

            if ($messageId && $status) {
                $this->smsService->updateMessageStatus(
                    providerMessageId: $messageId,
                    status: strtolower($status)
                );
            }
        }

        return response('', 200);
    }

    /**
     * Handle Plivo webhook
     */
    public function plivo(Request $request): Response
    {
        Log::info('Plivo webhook received', $request->all());

        $connection = SmsConnection::where('phone_number', $request->input('To'))
            ->where('provider', 'plivo')
            ->first();

        if (!$connection) {
            return response('', 200);
        }

        // Handle incoming message
        if ($request->filled('Text')) {
            $this->smsService->processIncoming(
                connection: $connection,
                from: $request->input('From'),
                to: $request->input('To'),
                content: $request->input('Text'),
                providerMessageId: $request->input('MessageUUID')
            );
        }

        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Handle Plivo delivery report
     */
    public function plivoDeliveryReport(Request $request): Response
    {
        Log::info('Plivo delivery report', $request->all());

        $messageId = $request->input('MessageUUID');
        $status = $request->input('Status');

        if ($messageId && $status) {
            $statusMap = [
                'queued' => 'queued',
                'sent' => 'sent',
                'delivered' => 'delivered',
                'undelivered' => 'undelivered',
                'failed' => 'failed',
            ];

            $this->smsService->updateMessageStatus(
                providerMessageId: $messageId,
                status: $statusMap[$status] ?? $status,
                errorCode: $request->input('ErrorCode')
            );
        }

        return response('', 200);
    }
}
