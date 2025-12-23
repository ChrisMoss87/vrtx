<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integration\ValueObjects\SyncDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationFieldMapping extends Model
{
    protected $fillable = [
        'connection_id',
        'crm_entity',
        'crm_field',
        'external_entity',
        'external_field',
        'direction',
        'transform',
        'transform_options',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'transform_options' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'direction' => SyncDirection::class,
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $crmEntity)
    {
        return $query->where('crm_entity', $crmEntity);
    }

    public function scopeForPush($query)
    {
        return $query->whereIn('direction', [SyncDirection::PUSH, SyncDirection::BOTH]);
    }

    public function scopeForPull($query)
    {
        return $query->whereIn('direction', [SyncDirection::PULL, SyncDirection::BOTH]);
    }

    public function shouldPush(): bool
    {
        return $this->is_active && in_array($this->direction, [SyncDirection::PUSH, SyncDirection::BOTH]);
    }

    public function shouldPull(): bool
    {
        return $this->is_active && in_array($this->direction, [SyncDirection::PULL, SyncDirection::BOTH]);
    }

    public function transformValue(mixed $value): mixed
    {
        if ($this->transform === null || $value === null) {
            return $value;
        }

        return match ($this->transform) {
            'uppercase' => strtoupper((string) $value),
            'lowercase' => strtolower((string) $value),
            'trim' => trim((string) $value),
            'date_format' => $this->transformDate($value),
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'json_encode' => json_encode($value),
            'json_decode' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    private function transformDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $format = $this->transform_options['format'] ?? 'Y-m-d';
        $inputFormat = $this->transform_options['input_format'] ?? null;

        try {
            if ($inputFormat) {
                $date = \DateTimeImmutable::createFromFormat($inputFormat, $value);
            } else {
                $date = new \DateTimeImmutable($value);
            }

            return $date->format($format);
        } catch (\Exception $e) {
            return null;
        }
    }
}
