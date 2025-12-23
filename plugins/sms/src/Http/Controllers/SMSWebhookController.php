<?php

declare(strict_types=1);

namespace Plugins\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Plugins\SMS\Application\Services\SMSApplicationService;

class SMSWebhookController extends Controller
{
    public function __construct(
        private readonly SMSApplicationService $smsService,
    ) {}

    /**
     * Handle Twilio webhook for incoming SMS.
     */
    public function twilio(Request $request): Response
    {
        Log::info('Twilio SMS webhook received', [
            'from' => $request->input('From'),
            'to' => $request->input('To'),
        ]);

        try {
            $this->smsService->handleIncomingMessage([
                'From' => $request->input('From'),
                'To' => $request->input('To'),
                'Body' => $request->input('Body'),
                'MessageSid' => $request->input('MessageSid'),
                'NumMedia' => $request->input('NumMedia', 0),
                'FromCity' => $request->input('FromCity'),
                'FromState' => $request->input('FromState'),
                'FromCountry' => $request->input('FromCountry'),
            ]);

            // Return TwiML response
            return response(
                '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
                200,
                ['Content-Type' => 'application/xml']
            );
        } catch (\Exception $e) {
            Log::error('Twilio webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response(
                '<?xml version="1.0" encoding="UTF-8"?><Response></Response>',
                200,
                ['Content-Type' => 'application/xml']
            );
        }
    }

    /**
     * Handle Twilio status callback webhook.
     */
    public function status(Request $request): Response
    {
        Log::info('Twilio status webhook received', [
            'message_sid' => $request->input('MessageSid'),
            'status' => $request->input('MessageStatus'),
        ]);

        try {
            $this->smsService->handleStatusUpdate([
                'MessageSid' => $request->input('MessageSid'),
                'MessageStatus' => $request->input('MessageStatus'),
                'ErrorCode' => $request->input('ErrorCode'),
                'ErrorMessage' => $request->input('ErrorMessage'),
            ]);

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Twilio status webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response('OK', 200);
        }
    }
}
