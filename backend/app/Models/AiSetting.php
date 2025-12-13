<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiSetting extends Model
{
    protected $fillable = [
        'provider',
        'api_key',
        'model',
        'settings',
        'max_tokens',
        'temperature',
        'is_enabled',
        'monthly_budget_cents',
        'monthly_usage_cents',
        'budget_reset_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'temperature' => 'decimal:2',
        'is_enabled' => 'boolean',
        'budget_reset_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    // Providers
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_AZURE = 'azure';

    // Available models per provider
    public const MODELS = [
        self::PROVIDER_OPENAI => [
            'gpt-4o' => ['name' => 'GPT-4o', 'input_cost' => 2.50, 'output_cost' => 10.00],
            'gpt-4o-mini' => ['name' => 'GPT-4o Mini', 'input_cost' => 0.15, 'output_cost' => 0.60],
            'gpt-4-turbo' => ['name' => 'GPT-4 Turbo', 'input_cost' => 10.00, 'output_cost' => 30.00],
        ],
        self::PROVIDER_ANTHROPIC => [
            'claude-sonnet-4-20250514' => ['name' => 'Claude Sonnet 4', 'input_cost' => 3.00, 'output_cost' => 15.00],
            'claude-3-5-haiku-20241022' => ['name' => 'Claude 3.5 Haiku', 'input_cost' => 0.80, 'output_cost' => 4.00],
        ],
    ];

    /**
     * Encrypt API key when setting
     */
    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    /**
     * Decrypt API key when getting
     */
    public function getApiKeyAttribute(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the decrypted API key for use in services
     */
    public function getDecryptedApiKey(): ?string
    {
        return $this->api_key;
    }

    /**
     * Check if budget is exceeded
     */
    public function isBudgetExceeded(): bool
    {
        if ($this->monthly_budget_cents === null) {
            return false;
        }

        return $this->monthly_usage_cents >= $this->monthly_budget_cents;
    }

    /**
     * Get remaining budget
     */
    public function getRemainingBudgetCents(): ?int
    {
        if ($this->monthly_budget_cents === null) {
            return null;
        }

        return max(0, $this->monthly_budget_cents - $this->monthly_usage_cents);
    }

    /**
     * Record usage and add to monthly total
     */
    public function recordUsage(int $costCents): void
    {
        $this->increment('monthly_usage_cents', $costCents);
    }

    /**
     * Reset monthly usage (called by scheduler)
     */
    public function resetMonthlyUsage(): void
    {
        $this->update([
            'monthly_usage_cents' => 0,
            'budget_reset_at' => now(),
        ]);
    }

    /**
     * Get model cost per 1M tokens
     */
    public function getModelCosts(): array
    {
        return self::MODELS[$this->provider][$this->model] ?? [
            'name' => $this->model,
            'input_cost' => 0,
            'output_cost' => 0,
        ];
    }

    /**
     * Calculate cost in cents for token usage
     */
    public function calculateCost(int $inputTokens, int $outputTokens): int
    {
        $costs = $this->getModelCosts();

        // Costs are per 1M tokens
        $inputCost = ($inputTokens / 1_000_000) * $costs['input_cost'];
        $outputCost = ($outputTokens / 1_000_000) * $costs['output_cost'];

        // Convert to cents
        return (int) round(($inputCost + $outputCost) * 100);
    }
}
