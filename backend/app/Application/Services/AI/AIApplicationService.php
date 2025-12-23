<?php

declare(strict_types=1);

namespace App\Application\Services\AI;

use App\Domain\AI\Repositories\AiPromptRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Models\AiEmailDraft;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class AIApplicationService
{
    public function __construct(
        private AiPromptRepositoryInterface $promptRepository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // SETTINGS USE CASES
    // =========================================================================

    /**
     * Get AI settings
     */
    public function getSettings(): ?AiSetting
    {
        return AiSetting::first();
    }

    /**
     * Update AI settings
     */
    public function updateSettings(array $data): AiSetting
    {
        $settings = AiSetting::firstOrCreate([]);

        $settings->update([
            'provider' => $data['provider'] ?? $settings->provider,
            'model' => $data['model'] ?? $settings->model,
            'max_tokens' => $data['max_tokens'] ?? $settings->max_tokens,
            'temperature' => $data['temperature'] ?? $settings->temperature,
            'is_enabled' => $data['is_enabled'] ?? $settings->is_enabled,
            'monthly_budget_cents' => $data['monthly_budget_cents'] ?? $settings->monthly_budget_cents,
        ]);

        // Only update API key if provided
        if (!empty($data['api_key'])) {
            $settings->api_key = $data['api_key'];
            $settings->save();
        }

        return $settings->fresh();
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        $settings = $this->getSettings();

        if (!$settings || !$settings->is_enabled) {
            return ['success' => false, 'error' => 'AI is not enabled'];
        }

        if (!$settings->api_key) {
            return ['success' => false, 'error' => 'No API key configured'];
        }

        try {
            $response = $this->callProvider($settings, [
                ['role' => 'user', 'content' => 'Say "Connection successful" in exactly those words.'],
            ], maxTokens: 50);

            return [
                'success' => true,
                'provider' => $settings->provider,
                'model' => $settings->model,
                'response' => $response['content'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get available providers and models
     */
    public function getAvailableModels(): array
    {
        return AiSetting::MODELS;
    }

    /**
     * Check if AI is available and has budget
     */
    public function isAvailable(): bool
    {
        $settings = $this->getSettings();

        if (!$settings || !$settings->is_enabled) {
            return false;
        }

        if ($settings->isBudgetExceeded()) {
            return false;
        }

        return true;
    }

    // =========================================================================
    // PROMPT USE CASES
    // =========================================================================

    /**
     * List prompts
     */
    public function listPrompts(array $filters = []): array
    {
        return $this->promptRepository->findByFilters($filters);
    }

    /**
     * Get a prompt by ID
     */
    public function getPrompt(int $promptId): ?array
    {
        return $this->promptRepository->findById($promptId);
    }

    /**
     * Get a prompt by slug
     */
    public function getPromptBySlug(string $slug): ?array
    {
        return $this->promptRepository->findBySlug($slug);
    }

    /**
     * Create a prompt
     */
    public function createPrompt(array $data): array
    {
        return $this->promptRepository->create($data);
    }

    /**
     * Update a prompt
     */
    public function updatePrompt(int $promptId, array $data): array
    {
        return $this->promptRepository->update($promptId, $data);
    }

    /**
     * Delete a prompt
     */
    public function deletePrompt(int $promptId): bool
    {
        return $this->promptRepository->delete($promptId);
    }

    // =========================================================================
    // EMAIL COMPOSITION USE CASES
    // =========================================================================

    /**
     * Compose an email
     */
    public function composeEmail(array $data): AiEmailDraft
    {
        $settings = $this->ensureAvailable();

        $prompt = $this->promptRepository->findBySlug('email-compose');

        if (!$prompt) {
            throw new \RuntimeException('Email compose prompt not configured');
        }

        $variables = [
            'context' => $data['context'] ?? '',
            'recipient_name' => $data['recipient_name'] ?? 'the recipient',
            'sender_name' => $this->authContext->userName() ?? 'the sender',
            'tone' => $data['tone'] ?? AiEmailDraft::TONE_PROFESSIONAL,
            'key_points' => $data['key_points'] ?? '',
        ];

        $messages = $this->promptRepository->getMessages($prompt, $variables);

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            AiUsageLog::FEATURE_EMAIL_COMPOSE,
            'email_draft',
            null
        );

        return AiEmailDraft::create([
            'user_id' => $this->authContext->userId(),
            'type' => AiEmailDraft::TYPE_COMPOSE,
            'tone' => $data['tone'] ?? AiEmailDraft::TONE_PROFESSIONAL,
            'context' => $data,
            'prompt' => $data['key_points'] ?? '',
            'generated_content' => $response['content'],
            'model_used' => $settings->model,
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
        ]);
    }

    /**
     * Improve an existing email
     */
    public function improveEmail(array $data): AiEmailDraft
    {
        $settings = $this->ensureAvailable();

        $prompt = $this->promptRepository->findBySlug('email-improve');

        if (!$prompt) {
            throw new \RuntimeException('Email improve prompt not configured');
        }

        $variables = [
            'original_email' => $data['original_content'],
            'improvement_instructions' => $data['instructions'] ?? 'Make it more professional and clear',
            'tone' => $data['tone'] ?? AiEmailDraft::TONE_PROFESSIONAL,
        ];

        $messages = $this->promptRepository->getMessages($prompt, $variables);

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            AiUsageLog::FEATURE_EMAIL_IMPROVE,
            'email_draft',
            null
        );

        return AiEmailDraft::create([
            'user_id' => $this->authContext->userId(),
            'type' => AiEmailDraft::TYPE_IMPROVE,
            'tone' => $data['tone'] ?? AiEmailDraft::TONE_PROFESSIONAL,
            'context' => $data,
            'original_content' => $data['original_content'],
            'generated_content' => $response['content'],
            'model_used' => $settings->model,
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
        ]);
    }

    /**
     * Generate email reply
     */
    public function generateReply(array $data): AiEmailDraft
    {
        $settings = $this->ensureAvailable();

        $prompt = $this->promptRepository->findBySlug('email-reply');

        if (!$prompt) {
            throw new \RuntimeException('Email reply prompt not configured');
        }

        $variables = [
            'original_email' => $data['original_email'],
            'reply_intent' => $data['reply_intent'] ?? '',
            'tone' => $data['tone'] ?? AiEmailDraft::TONE_PROFESSIONAL,
            'sender_name' => $this->authContext->userName() ?? 'the sender',
        ];

        $messages = $this->promptRepository->getMessages($prompt, $variables);

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            AiUsageLog::FEATURE_EMAIL_REPLY,
            'email_draft',
            null
        );

        return AiEmailDraft::create([
            'user_id' => $this->authContext->userId(),
            'type' => AiEmailDraft::TYPE_REPLY,
            'tone' => $data['tone'] ?? AiEmailDraft::TONE_PROFESSIONAL,
            'context' => $data,
            'original_content' => $data['original_email'],
            'prompt' => $data['reply_intent'] ?? '',
            'generated_content' => $response['content'],
            'model_used' => $settings->model,
            'tokens_used' => $response['usage']['total_tokens'] ?? 0,
        ]);
    }

    /**
     * Suggest subject lines
     */
    public function suggestSubjectLines(string $emailContent, int $count = 3): array
    {
        $settings = $this->ensureAvailable();

        $prompt = $this->promptRepository->findBySlug('subject-suggest');

        if (!$prompt) {
            // Fallback inline prompt
            $messages = [
                ['role' => 'system', 'content' => 'You are an email marketing expert. Generate catchy, professional email subject lines.'],
                ['role' => 'user', 'content' => "Generate {$count} different subject lines for this email content. Return only the subject lines, one per line, no numbering:\n\n{$emailContent}"],
            ];
        } else {
            $messages = $this->promptRepository->getMessages($prompt, [
                'email_content' => $emailContent,
                'count' => $count,
            ]);
        }

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            AiUsageLog::FEATURE_SUBJECT_SUGGEST,
            null,
            null
        );

        // Parse response into array of subject lines
        $lines = array_filter(
            array_map('trim', explode("\n", $response['content'])),
            fn($line) => !empty($line)
        );

        return array_values(array_slice($lines, 0, $count));
    }

    /**
     * Get email drafts for current user
     */
    public function getUserEmailDrafts(int $limit = 20): array
    {
        return AiEmailDraft::where('user_id', $this->authContext->userId())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($draft) => $draft->toArray())
            ->all();
    }

    /**
     * Mark draft as used
     */
    public function markDraftAsUsed(int $draftId): AiEmailDraft
    {
        $draft = AiEmailDraft::findOrFail($draftId);
        $draft->markAsUsed();
        return $draft;
    }

    // =========================================================================
    // ANALYSIS USE CASES
    // =========================================================================

    /**
     * Analyze sentiment of text
     */
    public function analyzeSentiment(string $text, ?string $entityType = null, ?int $entityId = null): array
    {
        $settings = $this->ensureAvailable();

        $messages = [
            ['role' => 'system', 'content' => 'You are a sentiment analysis expert. Analyze the sentiment of the provided text and return a JSON object with: sentiment (positive, negative, neutral), confidence (0-1), and key_phrases (array of notable phrases).'],
            ['role' => 'user', 'content' => "Analyze this text:\n\n{$text}"],
        ];

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            AiUsageLog::FEATURE_SENTIMENT,
            $entityType,
            $entityId
        );

        // Try to parse JSON response
        try {
            $result = json_decode($response['content'], true);
            if (!$result) {
                throw new \Exception('Failed to parse response');
            }
            return $result;
        } catch (\Exception $e) {
            // Return basic structure if parsing fails
            return [
                'sentiment' => 'neutral',
                'confidence' => 0.5,
                'raw_response' => $response['content'],
            ];
        }
    }

    /**
     * Generate meeting summary
     */
    public function summarizeMeeting(string $transcript, ?string $entityType = null, ?int $entityId = null): array
    {
        $settings = $this->ensureAvailable();

        $messages = [
            ['role' => 'system', 'content' => 'You are an expert at summarizing business meetings. Create a concise summary with: key_points (array), action_items (array with assignee if mentioned), decisions_made (array), and next_steps (array).'],
            ['role' => 'user', 'content' => "Summarize this meeting transcript:\n\n{$transcript}"],
        ];

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            AiUsageLog::FEATURE_MEETING_SUMMARY,
            $entityType,
            $entityId,
            maxTokens: 2000
        );

        try {
            $result = json_decode($response['content'], true);
            if (!$result) {
                throw new \Exception('Failed to parse response');
            }
            return $result;
        } catch (\Exception $e) {
            return ['summary' => $response['content']];
        }
    }

    /**
     * Run a custom prompt
     */
    public function runPrompt(string $promptSlug, array $variables, ?string $entityType = null, ?int $entityId = null): string
    {
        $settings = $this->ensureAvailable();

        $prompt = $this->promptRepository->findBySlug($promptSlug);

        if (!$prompt) {
            throw new \RuntimeException("Prompt '{$promptSlug}' not found");
        }

        $messages = $this->promptRepository->getMessages($prompt, $variables);

        $response = $this->callProviderWithLogging(
            $settings,
            $messages,
            $prompt['category'],
            $entityType,
            $entityId
        );

        return $response['content'];
    }

    // =========================================================================
    // USAGE & ANALYTICS USE CASES
    // =========================================================================

    /**
     * Get usage statistics
     */
    public function getUsageStats(?string $startDate = null, ?string $endDate = null): array
    {
        return AiUsageLog::getSummary($startDate, $endDate);
    }

    /**
     * Get usage by user
     */
    public function getUsageByUser(?string $startDate = null, ?string $endDate = null): array
    {
        $query = AiUsageLog::query()
            ->selectRaw('user_id, COUNT(*) as request_count, SUM(cost_cents) as total_cost, SUM(input_tokens + output_tokens) as total_tokens')
            ->groupBy('user_id')
            ->with(['user:id,name,email']);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->orderByDesc('total_cost')
            ->get()
            ->map(fn($item) => $item->toArray())
            ->all();
    }

    /**
     * Get usage trend
     */
    public function getUsageTrend(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        $usage = AiUsageLog::where('created_at', '>=', $startDate)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as requests, SUM(cost_cents) as cost")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        return $usage->map(fn($row) => [
            'date' => $row->date,
            'requests' => $row->requests,
            'cost_cents' => $row->cost,
        ])->toArray();
    }

    /**
     * Get budget status
     */
    public function getBudgetStatus(): array
    {
        $settings = $this->getSettings();

        if (!$settings) {
            return ['configured' => false];
        }

        return [
            'configured' => true,
            'is_enabled' => $settings->is_enabled,
            'budget_cents' => $settings->monthly_budget_cents,
            'used_cents' => $settings->monthly_usage_cents,
            'remaining_cents' => $settings->getRemainingBudgetCents(),
            'budget_exceeded' => $settings->isBudgetExceeded(),
            'budget_reset_at' => $settings->budget_reset_at?->toIso8601String(),
            'usage_percent' => $settings->monthly_budget_cents
                ? round(($settings->monthly_usage_cents / $settings->monthly_budget_cents) * 100, 1)
                : null,
        ];
    }

    /**
     * Reset monthly usage (called by scheduler)
     */
    public function resetMonthlyUsage(): void
    {
        $settings = $this->getSettings();
        if ($settings) {
            $settings->resetMonthlyUsage();
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Ensure AI is available, throw if not
     */
    private function ensureAvailable(): AiSetting
    {
        $settings = $this->getSettings();

        if (!$settings || !$settings->is_enabled) {
            throw new \RuntimeException('AI features are not enabled');
        }

        if (!$settings->api_key) {
            throw new \RuntimeException('AI API key not configured');
        }

        if ($settings->isBudgetExceeded()) {
            throw new \RuntimeException('Monthly AI budget exceeded');
        }

        return $settings;
    }

    /**
     * Call provider and log usage
     */
    private function callProviderWithLogging(
        AiSetting $settings,
        array $messages,
        string $feature,
        ?string $entityType,
        ?int $entityId,
        ?int $maxTokens = null
    ): array {
        $response = $this->callProvider($settings, $messages, $maxTokens);

        // Calculate cost
        $inputTokens = $response['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $response['usage']['completion_tokens'] ?? 0;
        $costCents = $settings->calculateCost($inputTokens, $outputTokens);

        // Log usage
        AiUsageLog::create([
            'feature' => $feature,
            'model' => $settings->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_cents' => $costCents,
            'user_id' => $this->authContext->userId(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        // Update monthly usage
        $settings->recordUsage($costCents);

        return $response;
    }

    /**
     * Call the AI provider
     */
    private function callProvider(AiSetting $settings, array $messages, ?int $maxTokens = null): array
    {
        $maxTokens = $maxTokens ?? $settings->max_tokens ?? 1000;

        return match ($settings->provider) {
            AiSetting::PROVIDER_OPENAI => $this->callOpenAI($settings, $messages, $maxTokens),
            AiSetting::PROVIDER_ANTHROPIC => $this->callAnthropic($settings, $messages, $maxTokens),
            default => throw new \RuntimeException("Unsupported provider: {$settings->provider}"),
        };
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(AiSetting $settings, array $messages, int $maxTokens): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$settings->api_key}",
            'Content-Type' => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => $settings->model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => (float) $settings->temperature,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("OpenAI API error: " . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
        ];
    }

    /**
     * Call Anthropic API
     */
    private function callAnthropic(AiSetting $settings, array $messages, int $maxTokens): array
    {
        // Extract system prompt if present
        $systemPrompt = null;
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemPrompt = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $payload = [
            'model' => $settings->model,
            'max_tokens' => $maxTokens,
            'messages' => $filteredMessages,
        ];

        if ($systemPrompt) {
            $payload['system'] = $systemPrompt;
        }

        $response = Http::withHeaders([
            'x-api-key' => $settings->api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \RuntimeException("Anthropic API error: " . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }
}
