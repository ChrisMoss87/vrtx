<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioDeal extends Model
{
    use HasFactory;

    protected $fillable = [
        'scenario_id',
        'deal_record_id',
        'stage_id',
        'amount',
        'probability',
        'close_date',
        'is_committed',
        'is_excluded',
        'notes',
        'original_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'close_date' => 'date',
        'is_committed' => 'boolean',
        'is_excluded' => 'boolean',
        'original_data' => 'array',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ForecastScenario::class, 'scenario_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function dealRecord(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'deal_record_id');
    }

    public function getWeightedAmount(): float
    {
        $probability = $this->probability ?? 50;
        return (float) $this->amount * ($probability / 100);
    }

    public function hasChanges(): bool
    {
        if (!$this->original_data) {
            return false;
        }

        return $this->amount != ($this->original_data['amount'] ?? 0)
            || $this->probability != ($this->original_data['probability'] ?? null)
            || $this->stage_id != ($this->original_data['stage_id'] ?? null)
            || ($this->close_date?->format('Y-m-d')) != ($this->original_data['close_date'] ?? null);
    }

    public function getChangeSummary(): array
    {
        if (!$this->original_data) {
            return [];
        }

        $changes = [];

        if ($this->amount != ($this->original_data['amount'] ?? 0)) {
            $changes['amount'] = [
                'from' => $this->original_data['amount'] ?? 0,
                'to' => $this->amount,
            ];
        }

        if ($this->probability != ($this->original_data['probability'] ?? null)) {
            $changes['probability'] = [
                'from' => $this->original_data['probability'] ?? null,
                'to' => $this->probability,
            ];
        }

        if ($this->stage_id != ($this->original_data['stage_id'] ?? null)) {
            $changes['stage_id'] = [
                'from' => $this->original_data['stage_id'] ?? null,
                'to' => $this->stage_id,
            ];
        }

        $originalDate = $this->original_data['close_date'] ?? null;
        $currentDate = $this->close_date?->format('Y-m-d');
        if ($currentDate != $originalDate) {
            $changes['close_date'] = [
                'from' => $originalDate,
                'to' => $currentDate,
            ];
        }

        return $changes;
    }

    public function resetToOriginal(): self
    {
        if (!$this->original_data) {
            return $this;
        }

        $this->amount = $this->original_data['amount'] ?? $this->amount;
        $this->probability = $this->original_data['probability'] ?? $this->probability;
        $this->stage_id = $this->original_data['stage_id'] ?? $this->stage_id;
        $this->close_date = $this->original_data['close_date'] ?? $this->close_date;

        return $this;
    }
}
