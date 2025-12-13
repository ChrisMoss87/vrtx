<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AbTestVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'name',
        'variant_code',
        'content',
        'traffic_percentage',
        'is_control',
        'is_active',
        'is_winner',
    ];

    protected $casts = [
        'content' => 'array',
        'is_control' => 'boolean',
        'is_active' => 'boolean',
        'is_winner' => 'boolean',
    ];

    public function test(): BelongsTo
    {
        return $this->belongsTo(AbTest::class, 'test_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(AbTestResult::class, 'variant_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AbTestEvent::class, 'variant_id');
    }

    public function getImpressions(): int
    {
        return $this->results->sum('impressions');
    }

    public function getConversions(): int
    {
        return $this->results->sum('conversions');
    }

    public function getClicks(): int
    {
        return $this->results->sum('clicks');
    }

    public function getOpens(): int
    {
        return $this->results->sum('opens');
    }

    public function getConversionRate(): float
    {
        $impressions = $this->getImpressions();
        if ($impressions === 0) {
            return 0.0;
        }
        return ($this->getConversions() / $impressions) * 100;
    }

    public function getClickRate(): float
    {
        $impressions = $this->getImpressions();
        if ($impressions === 0) {
            return 0.0;
        }
        return ($this->getClicks() / $impressions) * 100;
    }

    public function getOpenRate(): float
    {
        $impressions = $this->getImpressions();
        if ($impressions === 0) {
            return 0.0;
        }
        return ($this->getOpens() / $impressions) * 100;
    }

    public function declareWinner(): void
    {
        // Mark all other variants as not winner
        $this->test->variants()
            ->where('id', '!=', $this->id)
            ->update(['is_winner' => false]);

        // Mark this variant as winner
        $this->update(['is_winner' => true]);

        // Complete the test
        $this->test->complete($this->id);
    }

    public function recordImpression(?string $visitorId = null, array $metadata = []): void
    {
        $this->recordEvent('impression', $visitorId, $metadata);
        $this->incrementDailyResult('impressions');
    }

    public function recordClick(?string $visitorId = null, array $metadata = []): void
    {
        $this->recordEvent('click', $visitorId, $metadata);
        $this->incrementDailyResult('clicks');
    }

    public function recordConversion(?string $visitorId = null, array $metadata = []): void
    {
        $this->recordEvent('conversion', $visitorId, $metadata);
        $this->incrementDailyResult('conversions');
    }

    public function recordOpen(?string $visitorId = null, array $metadata = []): void
    {
        $this->recordEvent('open', $visitorId, $metadata);
        $this->incrementDailyResult('opens');
    }

    protected function recordEvent(string $type, ?string $visitorId, array $metadata): void
    {
        $this->events()->create([
            'visitor_id' => $visitorId,
            'event_type' => $type,
            'metadata' => $metadata,
        ]);
    }

    protected function incrementDailyResult(string $field): void
    {
        $today = now()->toDateString();

        $result = $this->results()->firstOrCreate(
            ['date' => $today],
            ['impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'opens' => 0, 'revenue' => 0]
        );

        $result->increment($field);
    }
}
