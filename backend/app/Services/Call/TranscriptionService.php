<?php

namespace App\Services\Call;

use App\Models\Call;
use App\Models\CallTranscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TranscriptionService
{
    public function transcribe(Call $call): ?CallTranscription
    {
        if (!$call->recording_url) {
            Log::warning('Cannot transcribe call without recording', ['call_id' => $call->id]);
            return null;
        }

        $transcription = $call->transcription;

        if (!$transcription) {
            $transcription = $call->transcription()->create([
                'status' => 'pending',
                'provider' => $this->getProvider($call),
            ]);
        }

        $transcription->markAsProcessing();

        try {
            $result = $this->processTranscription($call, $transcription);

            if ($result) {
                $transcription->markAsCompleted($result);
                return $transcription->fresh();
            }

            $transcription->markAsFailed('Transcription returned empty result');
            return null;
        } catch (\Exception $e) {
            Log::error('Transcription failed', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);

            $transcription->markAsFailed($e->getMessage());
            return null;
        }
    }

    protected function processTranscription(Call $call, CallTranscription $transcription): ?array
    {
        $provider = $transcription->provider;

        return match ($provider) {
            'openai' => $this->transcribeWithOpenAI($call),
            'deepgram' => $this->transcribeWithDeepgram($call),
            'assembly' => $this->transcribeWithAssemblyAI($call),
            default => $this->transcribeWithOpenAI($call),
        };
    }

    protected function transcribeWithOpenAI(Call $call): ?array
    {
        $apiKey = config('services.openai.api_key');

        if (!$apiKey) {
            throw new \Exception('OpenAI API key not configured');
        }

        // Download audio file
        $audioContent = $this->downloadRecording($call->recording_url);
        $tempFile = tempnam(sys_get_temp_dir(), 'audio_') . '.mp3';
        file_put_contents($tempFile, $audioContent);

        try {
            // Transcribe with Whisper
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
            ])
            ->attach('file', file_get_contents($tempFile), 'audio.mp3')
            ->attach('model', 'whisper-1')
            ->attach('response_format', 'verbose_json')
            ->attach('timestamp_granularities[]', 'segment')
            ->post('https://api.openai.com/v1/audio/transcriptions');

            if (!$response->successful()) {
                throw new \Exception('OpenAI transcription failed: ' . $response->body());
            }

            $data = $response->json();
            $fullText = $data['text'] ?? '';
            $segments = $this->formatOpenAISegments($data['segments'] ?? []);

            // Analyze with GPT
            $analysis = $this->analyzeWithGPT($fullText, $apiKey);

            return [
                'full_text' => $fullText,
                'segments' => $segments,
                'language' => $data['language'] ?? 'en',
                'confidence' => 0.95, // OpenAI doesn't provide confidence
                'word_count' => str_word_count($fullText),
                'summary' => $analysis['summary'] ?? null,
                'key_points' => $analysis['key_points'] ?? [],
                'action_items' => $analysis['action_items'] ?? [],
                'sentiment' => $analysis['sentiment'] ?? null,
                'entities' => $analysis['entities'] ?? [],
            ];
        } finally {
            @unlink($tempFile);
        }
    }

    protected function transcribeWithDeepgram(Call $call): ?array
    {
        $apiKey = config('services.deepgram.api_key');

        if (!$apiKey) {
            throw new \Exception('Deepgram API key not configured');
        }

        $response = Http::withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->post('https://api.deepgram.com/v1/listen?' . http_build_query([
            'model' => 'nova-2',
            'smart_format' => 'true',
            'diarize' => 'true',
            'punctuate' => 'true',
            'summarize' => 'v2',
            'detect_topics' => 'true',
            'detect_entities' => 'true',
            'sentiment' => 'true',
        ]), [
            'url' => $call->recording_url,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Deepgram transcription failed: ' . $response->body());
        }

        $data = $response->json();
        $result = $data['results']['channels'][0]['alternatives'][0] ?? [];

        $fullText = $result['transcript'] ?? '';
        $segments = $this->formatDeepgramSegments($result['words'] ?? []);

        return [
            'full_text' => $fullText,
            'segments' => $segments,
            'language' => $data['results']['channels'][0]['detected_language'] ?? 'en',
            'confidence' => $result['confidence'] ?? 0,
            'word_count' => str_word_count($fullText),
            'summary' => $data['results']['summary']['short'] ?? null,
            'key_points' => $this->extractTopics($data),
            'action_items' => [],
            'sentiment' => $this->extractSentiment($data),
            'entities' => $this->extractEntities($data),
        ];
    }

    protected function transcribeWithAssemblyAI(Call $call): ?array
    {
        $apiKey = config('services.assemblyai.api_key');

        if (!$apiKey) {
            throw new \Exception('AssemblyAI API key not configured');
        }

        // Submit transcription job
        $submitResponse = Http::withHeaders([
            'Authorization' => $apiKey,
            'Content-Type' => 'application/json',
        ])
        ->post('https://api.assemblyai.com/v2/transcript', [
            'audio_url' => $call->recording_url,
            'speaker_labels' => true,
            'auto_chapters' => true,
            'entity_detection' => true,
            'sentiment_analysis' => true,
            'summarization' => true,
            'summary_model' => 'informative',
            'summary_type' => 'bullets',
        ]);

        if (!$submitResponse->successful()) {
            throw new \Exception('AssemblyAI submission failed: ' . $submitResponse->body());
        }

        $transcriptId = $submitResponse->json('id');

        // Poll for completion
        $maxAttempts = 60;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(5);

            $statusResponse = Http::withHeaders([
                'Authorization' => $apiKey,
            ])
            ->get("https://api.assemblyai.com/v2/transcript/{$transcriptId}");

            $status = $statusResponse->json('status');

            if ($status === 'completed') {
                $data = $statusResponse->json();

                return [
                    'full_text' => $data['text'] ?? '',
                    'segments' => $this->formatAssemblySegments($data['words'] ?? [], $data['utterances'] ?? []),
                    'language' => $data['language_code'] ?? 'en',
                    'confidence' => $data['confidence'] ?? 0,
                    'word_count' => str_word_count($data['text'] ?? ''),
                    'summary' => $data['summary'] ?? null,
                    'key_points' => $this->extractChapterPoints($data['chapters'] ?? []),
                    'action_items' => [],
                    'sentiment' => $this->aggregateSentiment($data['sentiment_analysis_results'] ?? []),
                    'entities' => $data['entities'] ?? [],
                ];
            }

            if ($status === 'error') {
                throw new \Exception('AssemblyAI transcription error: ' . ($data['error'] ?? 'Unknown error'));
            }

            $attempt++;
        }

        throw new \Exception('AssemblyAI transcription timed out');
    }

    protected function analyzeWithGPT(string $transcript, string $apiKey): array
    {
        if (strlen($transcript) < 50) {
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])
        ->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an AI assistant that analyzes call transcripts. Extract key information and return it as JSON.',
                ],
                [
                    'role' => 'user',
                    'content' => "Analyze this call transcript and return a JSON object with these fields:
                    - summary: A brief 2-3 sentence summary
                    - key_points: Array of up to 5 key points discussed
                    - action_items: Array of action items or follow-ups mentioned
                    - sentiment: Overall sentiment (positive, negative, neutral)
                    - entities: Object with arrays for people, companies, products mentioned

                    Transcript:
                    {$transcript}",
                ],
            ],
            'response_format' => ['type' => 'json_object'],
            'max_tokens' => 1000,
        ]);

        if (!$response->successful()) {
            Log::warning('GPT analysis failed', ['error' => $response->body()]);
            return [];
        }

        $content = $response->json('choices.0.message.content');

        return json_decode($content, true) ?? [];
    }

    protected function downloadRecording(string $url): string
    {
        $response = Http::get($url);

        if (!$response->successful()) {
            throw new \Exception('Failed to download recording');
        }

        return $response->body();
    }

    protected function formatOpenAISegments(array $segments): array
    {
        return array_map(fn($seg) => [
            'start' => $seg['start'] ?? 0,
            'end' => $seg['end'] ?? 0,
            'text' => $seg['text'] ?? '',
            'speaker' => 'Speaker', // OpenAI doesn't provide speaker diarization
        ], $segments);
    }

    protected function formatDeepgramSegments(array $words): array
    {
        $segments = [];
        $currentSegment = null;
        $currentSpeaker = null;

        foreach ($words as $word) {
            $speaker = $word['speaker'] ?? 0;

            if ($speaker !== $currentSpeaker) {
                if ($currentSegment) {
                    $segments[] = $currentSegment;
                }

                $currentSegment = [
                    'start' => $word['start'] ?? 0,
                    'end' => $word['end'] ?? 0,
                    'text' => $word['word'] ?? '',
                    'speaker' => "Speaker {$speaker}",
                ];
                $currentSpeaker = $speaker;
            } else {
                $currentSegment['end'] = $word['end'] ?? $currentSegment['end'];
                $currentSegment['text'] .= ' ' . ($word['word'] ?? '');
            }
        }

        if ($currentSegment) {
            $segments[] = $currentSegment;
        }

        return $segments;
    }

    protected function formatAssemblySegments(array $words, array $utterances): array
    {
        if (!empty($utterances)) {
            return array_map(fn($u) => [
                'start' => ($u['start'] ?? 0) / 1000,
                'end' => ($u['end'] ?? 0) / 1000,
                'text' => $u['text'] ?? '',
                'speaker' => $u['speaker'] ?? 'Speaker',
            ], $utterances);
        }

        return [];
    }

    protected function extractTopics(array $data): array
    {
        $topics = $data['results']['topics'] ?? [];
        return array_column($topics, 'topic');
    }

    protected function extractSentiment(array $data): ?string
    {
        $sentiment = $data['results']['sentiments'] ?? [];

        if (empty($sentiment)) {
            return null;
        }

        $avgScore = array_sum(array_column($sentiment, 'score')) / count($sentiment);

        if ($avgScore > 0.3) return 'positive';
        if ($avgScore < -0.3) return 'negative';
        return 'neutral';
    }

    protected function extractEntities(array $data): array
    {
        return $data['results']['entities'] ?? [];
    }

    protected function extractChapterPoints(array $chapters): array
    {
        return array_map(fn($ch) => $ch['headline'] ?? $ch['summary'] ?? '', $chapters);
    }

    protected function aggregateSentiment(array $sentiments): ?string
    {
        if (empty($sentiments)) {
            return null;
        }

        $counts = ['POSITIVE' => 0, 'NEGATIVE' => 0, 'NEUTRAL' => 0];

        foreach ($sentiments as $s) {
            $sentiment = $s['sentiment'] ?? 'NEUTRAL';
            $counts[$sentiment] = ($counts[$sentiment] ?? 0) + 1;
        }

        $max = max($counts);
        $dominant = array_search($max, $counts);

        return strtolower($dominant);
    }

    protected function getProvider(Call $call): string
    {
        return $call->provider->getSetting('transcription_provider', 'openai');
    }
}
