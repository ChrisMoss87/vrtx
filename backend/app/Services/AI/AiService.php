<?php

namespace App\Services\AI;

use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Models\AiPrompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    protected ?AiSetting $settings = null;

    /**
     * Get AI settings for current tenant
     */
    public function getSettings(): ?AiSetting
    {
        if ($this->settings === null) {
            $this->settings = AiSetting::first();
        }

        return $this->settings;
    }

    /**
     * Check if AI is enabled and configured
     */
    public function isEnabled(): bool
    {
        $settings = $this->getSettings();

        return $settings
            && $settings->is_enabled
            && $settings->getDecryptedApiKey();
    }

    /**
     * Check if budget allows usage
     */
    public function canUse(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $settings = $this->getSettings();

        return !$settings->isBudgetExceeded();
    }

    /**
     * Generate completion using configured LLM
     */
    public function complete(
        array $messages,
        string $feature,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): array {
        $settings = $this->getSettings();

        if (!$this->canUse()) {
            throw new \Exception('AI service is not available or budget exceeded');
        }

        $maxTokens = $maxTokens ?? $settings->max_tokens;
        $temperature = $temperature ?? (float) $settings->temperature;

        $response = match ($settings->provider) {
            AiSetting::PROVIDER_OPENAI => $this->callOpenAI($messages, $maxTokens, $temperature),
            AiSetting::PROVIDER_ANTHROPIC => $this->callAnthropic($messages, $maxTokens, $temperature),
            default => throw new \Exception("Unsupported AI provider: {$settings->provider}"),
        };

        // Log usage
        $this->logUsage($feature, $response, $userId, $entityType, $entityId);

        return $response;
    }

    /**
     * Call OpenAI API
     */
    protected function callOpenAI(array $messages, int $maxTokens, float $temperature): array
    {
        $settings = $this->getSettings();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $settings->getDecryptedApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $settings->model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('OpenAI API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'input_tokens' => $data['usage']['prompt_tokens'] ?? 0,
            'output_tokens' => $data['usage']['completion_tokens'] ?? 0,
            'model' => $data['model'] ?? $settings->model,
            'finish_reason' => $data['choices'][0]['finish_reason'] ?? null,
        ];
    }

    /**
     * Call Anthropic API
     */
    protected function callAnthropic(array $messages, int $maxTokens, float $temperature): array
    {
        $settings = $this->getSettings();

        // Convert messages format for Anthropic
        $systemMessage = '';
        $anthropicMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $anthropicMessages[] = [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ];
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => $settings->getDecryptedApiKey(),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $settings->model,
            'max_tokens' => $maxTokens,
            'system' => $systemMessage,
            'messages' => $anthropicMessages,
        ]);

        if (!$response->successful()) {
            Log::error('Anthropic API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Anthropic API request failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'input_tokens' => $data['usage']['input_tokens'] ?? 0,
            'output_tokens' => $data['usage']['output_tokens'] ?? 0,
            'model' => $data['model'] ?? $settings->model,
            'finish_reason' => $data['stop_reason'] ?? null,
        ];
    }

    /**
     * Log AI usage
     */
    protected function logUsage(
        string $feature,
        array $response,
        ?int $userId,
        ?string $entityType,
        ?int $entityId
    ): void {
        $settings = $this->getSettings();

        $costCents = $settings->calculateCost(
            $response['input_tokens'],
            $response['output_tokens']
        );

        AiUsageLog::create([
            'feature' => $feature,
            'model' => $response['model'],
            'input_tokens' => $response['input_tokens'],
            'output_tokens' => $response['output_tokens'],
            'cost_cents' => $costCents,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        // Update monthly usage
        $settings->recordUsage($costCents);
    }

    /**
     * Use a saved prompt template
     */
    public function usePrompt(
        string $promptSlug,
        array $variables,
        string $feature,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): array {
        $prompt = AiPrompt::findBySlug($promptSlug);

        if (!$prompt) {
            throw new \Exception("Prompt not found: {$promptSlug}");
        }

        $messages = $prompt->getMessages($variables);

        return $this->complete($messages, $feature, null, null, $userId, $entityType, $entityId);
    }

    /**
     * Get usage statistics
     */
    public function getUsageStats(?string $startDate = null, ?string $endDate = null): array
    {
        return AiUsageLog::getSummary($startDate, $endDate);
    }

    /**
     * Get current month usage
     */
    public function getCurrentMonthUsage(): array
    {
        $settings = $this->getSettings();

        return [
            'used_cents' => $settings?->monthly_usage_cents ?? 0,
            'budget_cents' => $settings?->monthly_budget_cents,
            'remaining_cents' => $settings?->getRemainingBudgetCents(),
            'is_exceeded' => $settings?->isBudgetExceeded() ?? false,
        ];
    }
}
