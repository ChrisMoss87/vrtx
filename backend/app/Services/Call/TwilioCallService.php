<?php

namespace App\Services\Call;

use App\Models\Call;
use App\Models\CallProvider;
use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;
use Illuminate\Support\Facades\Log;

class TwilioCallService
{
    protected ?Client $client = null;
    protected CallProvider $provider;

    public function __construct(CallProvider $provider)
    {
        $this->provider = $provider;
    }

    protected function getClient(): Client
    {
        if (!$this->client) {
            $this->client = new Client(
                $this->provider->account_sid,
                $this->provider->auth_token
            );
        }

        return $this->client;
    }

    public function makeCall(string $to, string $from, array $options = []): array
    {
        try {
            $callParams = [
                'to' => $to,
                'from' => $from ?: $this->provider->phone_number,
                'url' => $options['twiml_url'] ?? route('api.calls.twiml.outbound'),
                'statusCallback' => route('api.calls.webhook.status'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
            ];

            if ($this->provider->recording_enabled) {
                $callParams['record'] = true;
                $callParams['recordingStatusCallback'] = route('api.calls.webhook.recording');
                $callParams['recordingStatusCallbackMethod'] = 'POST';
            }

            if (isset($options['timeout'])) {
                $callParams['timeout'] = $options['timeout'];
            }

            if (isset($options['caller_id'])) {
                $callParams['callerId'] = $options['caller_id'];
            }

            $call = $this->getClient()->calls->create(
                $callParams['to'],
                $callParams['from'],
                $callParams
            );

            return [
                'success' => true,
                'external_call_id' => $call->sid,
                'status' => $call->status,
            ];
        } catch (\Exception $e) {
            Log::error('Twilio call failed', [
                'to' => $to,
                'from' => $from,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function endCall(string $callSid): bool
    {
        try {
            $this->getClient()->calls($callSid)->update(['status' => 'completed']);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to end Twilio call', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function transferCall(string $callSid, string $toNumber): bool
    {
        try {
            $twiml = new VoiceResponse();
            $twiml->dial($toNumber);

            $this->getClient()->calls($callSid)->update([
                'twiml' => $twiml->asXML(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to transfer Twilio call', [
                'call_sid' => $callSid,
                'to' => $toNumber,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function holdCall(string $callSid, ?string $holdMusicUrl = null): bool
    {
        try {
            $twiml = new VoiceResponse();

            if ($holdMusicUrl) {
                $twiml->play($holdMusicUrl, ['loop' => 0]);
            } else {
                $twiml->play('http://com.twilio.music.classical.s3.amazonaws.com/BusssiessJazz.mp3', ['loop' => 0]);
            }

            $this->getClient()->calls($callSid)->update([
                'twiml' => $twiml->asXML(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to hold Twilio call', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function muteCall(string $callSid, bool $muted = true): bool
    {
        try {
            // Get the participant and mute them
            $conferences = $this->getClient()->conferences->read(['friendlyName' => $callSid]);

            if (!empty($conferences)) {
                $conference = $conferences[0];
                $participants = $this->getClient()
                    ->conferences($conference->sid)
                    ->participants
                    ->read();

                foreach ($participants as $participant) {
                    $this->getClient()
                        ->conferences($conference->sid)
                        ->participants($participant->callSid)
                        ->update(['muted' => $muted]);
                }

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to mute Twilio call', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getRecording(string $recordingSid): ?array
    {
        try {
            $recording = $this->getClient()->recordings($recordingSid)->fetch();

            return [
                'sid' => $recording->sid,
                'duration' => $recording->duration,
                'url' => "https://api.twilio.com" . $recording->uri . ".mp3",
                'status' => $recording->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Twilio recording', [
                'recording_sid' => $recordingSid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function deleteRecording(string $recordingSid): bool
    {
        try {
            $this->getClient()->recordings($recordingSid)->delete();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete Twilio recording', [
                'recording_sid' => $recordingSid,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getCallDetails(string $callSid): ?array
    {
        try {
            $call = $this->getClient()->calls($callSid)->fetch();

            return [
                'sid' => $call->sid,
                'status' => $call->status,
                'direction' => $call->direction,
                'from' => $call->from,
                'to' => $call->to,
                'duration' => $call->duration,
                'start_time' => $call->startTime?->format('Y-m-d H:i:s'),
                'end_time' => $call->endTime?->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Twilio call details', [
                'call_sid' => $callSid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function generateInboundTwiml(array $options = []): string
    {
        $twiml = new VoiceResponse();

        if (isset($options['greeting'])) {
            $twiml->say($options['greeting'], ['voice' => 'alice']);
        }

        if (isset($options['gather'])) {
            $gather = $twiml->gather([
                'input' => 'dtmf speech',
                'timeout' => 3,
                'numDigits' => 1,
                'action' => $options['gather']['action_url'] ?? route('api.calls.twiml.menu'),
            ]);
            $gather->say($options['gather']['prompt'] ?? 'Press 1 for sales, 2 for support.');
        }

        if (isset($options['dial'])) {
            $dial = $twiml->dial([
                'callerId' => $options['dial']['caller_id'] ?? null,
                'timeout' => $options['dial']['timeout'] ?? 30,
                'record' => $this->provider->recording_enabled ? 'record-from-answer' : 'do-not-record',
            ]);

            if (isset($options['dial']['number'])) {
                $dial->number($options['dial']['number']);
            } elseif (isset($options['dial']['queue'])) {
                $dial->queue($options['dial']['queue']);
            }
        }

        if (isset($options['voicemail']) && $options['voicemail']) {
            $twiml->say('Please leave a message after the beep.', ['voice' => 'alice']);
            $twiml->record([
                'maxLength' => 120,
                'transcribe' => $this->provider->transcription_enabled,
                'transcribeCallback' => route('api.calls.webhook.transcription'),
                'action' => route('api.calls.twiml.voicemail-complete'),
            ]);
        }

        return $twiml->asXML();
    }

    public function generateOutboundTwiml(array $options = []): string
    {
        $twiml = new VoiceResponse();

        $dial = $twiml->dial([
            'callerId' => $options['caller_id'] ?? $this->provider->phone_number,
            'timeout' => $options['timeout'] ?? 30,
            'record' => $this->provider->recording_enabled ? 'record-from-answer' : 'do-not-record',
            'recordingStatusCallback' => route('api.calls.webhook.recording'),
        ]);

        $dial->number($options['to']);

        return $twiml->asXML();
    }

    public function validateWebhookSignature(string $signature, string $url, array $params): bool
    {
        try {
            $validator = new \Twilio\Security\RequestValidator($this->provider->auth_token);
            return $validator->validate($signature, $url, $params);
        } catch (\Exception $e) {
            Log::error('Twilio webhook validation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getAccountBalance(): ?array
    {
        try {
            $account = $this->getClient()->api->v2010->accounts($this->provider->account_sid)->fetch();
            $balance = $this->getClient()->balance->fetch();

            return [
                'balance' => $balance->balance,
                'currency' => $balance->currency,
                'account_status' => $account->status,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get Twilio account balance', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function listPhoneNumbers(): array
    {
        try {
            $numbers = $this->getClient()->incomingPhoneNumbers->read([], 20);

            return array_map(fn($num) => [
                'sid' => $num->sid,
                'phone_number' => $num->phoneNumber,
                'friendly_name' => $num->friendlyName,
                'capabilities' => [
                    'voice' => $num->capabilities['voice'] ?? false,
                    'sms' => $num->capabilities['sms'] ?? false,
                    'mms' => $num->capabilities['mms'] ?? false,
                ],
            ], $numbers);
        } catch (\Exception $e) {
            Log::error('Failed to list Twilio phone numbers', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
