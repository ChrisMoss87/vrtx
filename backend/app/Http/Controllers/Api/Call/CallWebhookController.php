<?php

namespace App\Http\Controllers\Api\Call;

use App\Application\Services\Call\CallApplicationService;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallProvider;
use App\Models\CallQueue;
use App\Services\Call\CallService;
use App\Services\Call\TwilioCallService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CallWebhookController extends Controller
{
    public function __construct(
        protected CallApplicationService $callApplicationService,
        protected CallService $callService
    ) {}

    /**
     * Handle incoming call webhook from Twilio
     */
    public function inbound(Request $request): Response
    {
        Log::info('Inbound call webhook', $request->all());

        $fromNumber = $request->input('From');
        $toNumber = $request->input('To');
        $callSid = $request->input('CallSid');

        // Find provider by phone number
        $provider = CallProvider::where('phone_number', $toNumber)
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            Log::error('No active provider found for number', ['to' => $toNumber]);
            return $this->twimlResponse('<Response><Say>Sorry, this number is not configured.</Say><Hangup/></Response>');
        }

        // Create call record
        $call = $this->callService->handleInboundCall(
            $provider,
            $fromNumber,
            $toNumber,
            $callSid,
            [
                'caller_city' => $request->input('CallerCity'),
                'caller_state' => $request->input('CallerState'),
                'caller_country' => $request->input('CallerCountry'),
            ]
        );

        // Find queue for this number
        $queue = CallQueue::where('phone_number', $toNumber)
            ->where('is_active', true)
            ->first();

        if ($queue) {
            return $this->handleQueueRouting($call, $queue, $provider);
        }

        // Default behavior - ring through
        $service = new TwilioCallService($provider);
        $twiml = $service->generateInboundTwiml([
            'greeting' => $provider->getSetting('default_greeting', 'Thank you for calling. Please hold while we connect you.'),
        ]);

        return $this->twimlResponse($twiml);
    }

    /**
     * Handle call status updates
     */
    public function status(Request $request): Response
    {
        Log::info('Call status webhook', $request->all());

        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus');

        $data = [];

        if ($request->has('CallDuration')) {
            $data['duration_seconds'] = (int) $request->input('CallDuration');
        }

        $this->callService->updateCallStatus($callSid, $callStatus, $data);

        return response('', 200);
    }

    /**
     * Handle recording completed webhook
     */
    public function recording(Request $request): Response
    {
        Log::info('Recording webhook', $request->all());

        $callSid = $request->input('CallSid');
        $recordingSid = $request->input('RecordingSid');
        $recordingUrl = $request->input('RecordingUrl');
        $duration = (int) $request->input('RecordingDuration', 0);

        if ($recordingUrl) {
            $this->callService->attachRecording(
                $callSid,
                $recordingUrl . '.mp3',
                $recordingSid,
                $duration
            );
        }

        return response('', 200);
    }

    /**
     * Handle transcription webhook (Twilio built-in)
     */
    public function transcription(Request $request): Response
    {
        Log::info('Transcription webhook', $request->all());

        $callSid = $request->input('CallSid');
        $transcriptionText = $request->input('TranscriptionText');
        $transcriptionStatus = $request->input('TranscriptionStatus');

        if ($transcriptionStatus === 'completed' && $transcriptionText) {
            $call = Call::where('external_call_id', $callSid)->first();

            if ($call) {
                $call->transcription()->updateOrCreate(
                    ['call_id' => $call->id],
                    [
                        'status' => 'completed',
                        'full_text' => $transcriptionText,
                        'provider' => 'twilio',
                        'processed_at' => now(),
                    ]
                );
            }
        }

        return response('', 200);
    }

    /**
     * Generate TwiML for outbound calls
     */
    public function outboundTwiml(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $call = Call::where('external_call_id', $callSid)->first();

        if (!$call) {
            return $this->twimlResponse('<Response><Say>Call not found.</Say><Hangup/></Response>');
        }

        $service = new TwilioCallService($call->provider);
        $twiml = $service->generateOutboundTwiml([
            'to' => $call->to_number,
            'caller_id' => $call->from_number,
        ]);

        return $this->twimlResponse($twiml);
    }

    /**
     * Handle IVR menu input
     */
    public function menu(Request $request): Response
    {
        $digits = $request->input('Digits');
        $callSid = $request->input('CallSid');

        $call = Call::where('external_call_id', $callSid)->first();

        if (!$call) {
            return $this->twimlResponse('<Response><Say>Call not found.</Say><Hangup/></Response>');
        }

        // Update call metadata with menu selection
        $call->update([
            'metadata' => array_merge($call->metadata ?? [], [
                'menu_selection' => $digits,
            ]),
        ]);

        // Handle menu routing based on digits
        $queueName = match ($digits) {
            '1' => 'sales',
            '2' => 'support',
            '3' => 'billing',
            default => null,
        };

        if ($queueName) {
            $queue = CallQueue::where('name', 'like', "%{$queueName}%")
                ->where('is_active', true)
                ->first();

            if ($queue) {
                return $this->handleQueueRouting($call, $queue, $call->provider);
            }
        }

        // Invalid selection
        $twiml = '<Response>
            <Say>Invalid selection. Please try again.</Say>
            <Redirect>' . route('api.calls.webhook.inbound') . '</Redirect>
        </Response>';

        return $this->twimlResponse($twiml);
    }

    /**
     * Handle voicemail completion
     */
    public function voicemailComplete(Request $request): Response
    {
        $callSid = $request->input('CallSid');
        $recordingUrl = $request->input('RecordingUrl');

        $call = Call::where('external_call_id', $callSid)->first();

        if ($call) {
            $call->update([
                'status' => 'voicemail',
                'metadata' => array_merge($call->metadata ?? [], [
                    'voicemail_url' => $recordingUrl,
                ]),
            ]);
        }

        return $this->twimlResponse('<Response><Say>Thank you for your message. Goodbye.</Say><Hangup/></Response>');
    }

    /**
     * Handle fallback for errors
     */
    public function fallback(Request $request): Response
    {
        Log::error('Call fallback triggered', $request->all());

        return $this->twimlResponse('<Response><Say>We are experiencing technical difficulties. Please try again later.</Say><Hangup/></Response>');
    }

    /**
     * Route call to queue
     */
    protected function handleQueueRouting(Call $call, CallQueue $queue, CallProvider $provider): Response
    {
        // Check business hours
        if (!$queue->isWithinBusinessHours()) {
            $message = $queue->after_hours_message ?? 'We are currently closed. Please call back during business hours.';

            if ($queue->voicemail_enabled) {
                $twiml = "<Response>
                    <Say>{$message}</Say>
                    <Say>Please leave a message after the beep.</Say>
                    <Record maxLength=\"120\" action=\"" . route('api.calls.webhook.voicemail') . "\"/>
                </Response>";
            } else {
                $twiml = "<Response><Say>{$message}</Say><Hangup/></Response>";
            }

            return $this->twimlResponse($twiml);
        }

        // Try to route to agent
        $agent = $this->callService->routeToQueue($call, $queue);

        if ($agent) {
            // Build TwiML to dial agent
            $twiml = '<Response>';

            if ($queue->welcome_message) {
                $twiml .= "<Say>{$queue->welcome_message}</Say>";
            }

            $twiml .= '<Dial callerId="' . $call->from_number . '" timeout="30" action="' . route('api.calls.webhook.dial-result') . '">';

            // Get agent's phone number from user profile
            $agentPhone = $agent->phone ?? null;

            if ($agentPhone) {
                $twiml .= "<Number>{$agentPhone}</Number>";
            } else {
                // Use client/browser calling
                $twiml .= "<Client>{$agent->id}</Client>";
            }

            $twiml .= '</Dial></Response>';

            return $this->twimlResponse($twiml);
        }

        // No agents available
        if ($queue->voicemail_enabled) {
            $greeting = $queue->voicemail_greeting ?? 'All our agents are busy. Please leave a message.';

            $twiml = "<Response>
                <Say>{$greeting}</Say>
                <Record maxLength=\"120\" action=\"" . route('api.calls.webhook.voicemail') . "\"/>
            </Response>";
        } else {
            $twiml = '<Response>';
            $twiml .= '<Say>All our agents are currently busy. Please try again later.</Say>';

            if ($queue->hold_music_url) {
                $twiml .= "<Play loop=\"10\">{$queue->hold_music_url}</Play>";
            }

            $twiml .= '<Hangup/></Response>';
        }

        return $this->twimlResponse($twiml);
    }

    /**
     * Handle dial result (agent answered or not)
     */
    public function dialResult(Request $request): Response
    {
        $dialStatus = $request->input('DialCallStatus');
        $callSid = $request->input('CallSid');

        $call = Call::where('external_call_id', $callSid)->first();

        if (!$call) {
            return $this->twimlResponse('<Response><Hangup/></Response>');
        }

        if ($dialStatus !== 'completed') {
            // Agent didn't answer - check for voicemail
            $queue = null;

            if (isset($call->metadata['queue_id'])) {
                $queue = CallQueue::find($call->metadata['queue_id']);
            }

            if ($queue && $queue->voicemail_enabled) {
                $greeting = $queue->voicemail_greeting ?? 'The agent is unavailable. Please leave a message.';

                $twiml = "<Response>
                    <Say>{$greeting}</Say>
                    <Record maxLength=\"120\" action=\"" . route('api.calls.webhook.voicemail') . "\"/>
                </Response>";

                return $this->twimlResponse($twiml);
            }

            return $this->twimlResponse('<Response><Say>Unable to connect. Please try again later.</Say><Hangup/></Response>');
        }

        return $this->twimlResponse('<Response></Response>');
    }

    /**
     * Create TwiML response
     */
    protected function twimlResponse(string $twiml): Response
    {
        return response($twiml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
