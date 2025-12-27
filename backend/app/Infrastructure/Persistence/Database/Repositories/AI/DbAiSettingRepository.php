<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\AI;

use App\Domain\AI\Repositories\AiSettingRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DbAiSettingRepository implements AiSettingRepositoryInterface
{
    private const TABLE = 'ai_settings';

    private const PROVIDER_OPENAI = 'openai';
    private const PROVIDER_ANTHROPIC = 'anthropic';

    private const COST_PER_1K_TOKENS = [
        'gpt-4' => ['input' => 3, 'output' => 6],
        'gpt-4-turbo' => ['input' => 1, 'output' => 3],
        'gpt-3.5-turbo' => ['input' => 0.05, 'output' => 0.15],
        'claude-3-opus' => ['input' => 1.5, 'output' => 7.5],
        'claude-3-sonnet' => ['input' => 0.3, 'output' => 1.5],
        'claude-3-haiku' => ['input' => 0.025, 'output' => 0.125],
    ];

    public function get(): ?array
    {
        $row = DB::table(self::TABLE)->first();
        return $row ? $this->formatRow($row) : null;
    }

    public function firstOrCreate(array $defaults = []): array
    {
        $existing = DB::table(self::TABLE)->first();

        if ($existing) {
            return $this->formatRow($existing);
        }

        $id = DB::table(self::TABLE)->insertGetId([
            'provider' => $defaults['provider'] ?? self::PROVIDER_OPENAI,
            'model' => $defaults['model'] ?? 'gpt-3.5-turbo',
            'api_key' => $defaults['api_key'] ?? null,
            'max_tokens' => $defaults['max_tokens'] ?? 1000,
            'temperature' => $defaults['temperature'] ?? 0.7,
            'is_enabled' => $defaults['is_enabled'] ?? false,
            'monthly_budget_cents' => $defaults['monthly_budget_cents'] ?? 0,
            'monthly_usage_cents' => 0,
            'budget_reset_at' => now()->startOfMonth()->addMonth(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->formatRow($row);
    }

    public function update(array $data): array
    {
        $settings = $this->firstOrCreate();

        $updateData = ['updated_at' => now()];

        if (isset($data['provider'])) {
            $updateData['provider'] = $data['provider'];
        }
        if (isset($data['model'])) {
            $updateData['model'] = $data['model'];
        }
        if (isset($data['api_key'])) {
            $updateData['api_key'] = encrypt($data['api_key']);
        }
        if (isset($data['max_tokens'])) {
            $updateData['max_tokens'] = $data['max_tokens'];
        }
        if (isset($data['temperature'])) {
            $updateData['temperature'] = $data['temperature'];
        }
        if (isset($data['is_enabled'])) {
            $updateData['is_enabled'] = $data['is_enabled'];
        }
        if (isset($data['monthly_budget_cents'])) {
            $updateData['monthly_budget_cents'] = $data['monthly_budget_cents'];
        }

        DB::table(self::TABLE)
            ->where('id', $settings['id'])
            ->update($updateData);

        return $this->get();
    }

    public function isAvailable(): bool
    {
        $settings = $this->get();

        if (!$settings || !$settings['is_enabled']) {
            return false;
        }

        if ($this->isBudgetExceeded()) {
            return false;
        }

        return true;
    }

    public function isBudgetExceeded(): bool
    {
        $settings = $this->get();

        if (!$settings) {
            return true;
        }

        if (!$settings['monthly_budget_cents']) {
            return false;
        }

        return $settings['monthly_usage_cents'] >= $settings['monthly_budget_cents'];
    }

    public function getRemainingBudgetCents(): int
    {
        $settings = $this->get();

        if (!$settings || !$settings['monthly_budget_cents']) {
            return 0;
        }

        return max(0, $settings['monthly_budget_cents'] - $settings['monthly_usage_cents']);
    }

    public function recordUsage(int $costCents): void
    {
        DB::table(self::TABLE)->increment('monthly_usage_cents', $costCents);
    }

    public function resetMonthlyUsage(): void
    {
        DB::table(self::TABLE)->update([
            'monthly_usage_cents' => 0,
            'budget_reset_at' => now()->startOfMonth()->addMonth(),
            'updated_at' => now(),
        ]);
    }

    public function calculateCost(int $inputTokens, int $outputTokens): int
    {
        $settings = $this->get();

        if (!$settings) {
            return 0;
        }

        $model = $settings['model'];
        $rates = self::COST_PER_1K_TOKENS[$model] ?? ['input' => 0.1, 'output' => 0.1];

        $inputCost = ($inputTokens / 1000) * $rates['input'];
        $outputCost = ($outputTokens / 1000) * $rates['output'];

        return (int) ceil(($inputCost + $outputCost) * 100);
    }

    private function formatRow(object $row): array
    {
        return [
            'id' => $row->id,
            'provider' => $row->provider,
            'model' => $row->model,
            'api_key' => $row->api_key ? decrypt($row->api_key) : null,
            'max_tokens' => $row->max_tokens,
            'temperature' => (float) $row->temperature,
            'is_enabled' => (bool) $row->is_enabled,
            'monthly_budget_cents' => (int) $row->monthly_budget_cents,
            'monthly_usage_cents' => (int) $row->monthly_usage_cents,
            'budget_reset_at' => $row->budget_reset_at ? Carbon::parse($row->budget_reset_at) : null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }
}
