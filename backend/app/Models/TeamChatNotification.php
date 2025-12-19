<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamChatNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'channel_id',
        'name',
        'description',
        'trigger_event',
        'trigger_module',
        'trigger_conditions',
        'message_template',
        'include_mentions',
        'mention_field',
        'is_active',
        'triggered_count',
        'last_triggered_at',
        'created_by',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'include_mentions' => 'boolean',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TeamChatConnection::class, 'connection_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TeamChatChannel::class, 'channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TeamChatMessage::class, 'notification_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEvent($query, string $event)
    {
        return $query->where('trigger_event', $event);
    }

    public function scopeByModule($query, string $module)
    {
        return $query->where('trigger_module', $module);
    }

    /**
     * Render message template with record data
     */
    public function renderMessage(array $data): string
    {
        $message = $this->message_template;

        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $message = str_replace('{{' . $key . '}}', (string) $value, $message);
            }
        }

        // Remove unmatched placeholders
        $message = preg_replace('/\{\{\w+\}\}/', '', $message);

        return trim($message);
    }

    /**
     * Check if record matches trigger conditions
     */
    public function matchesConditions(array $recordData): bool
    {
        if (empty($this->trigger_conditions)) {
            return true;
        }

        foreach ($this->trigger_conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;

            if (!$field) {
                continue;
            }

            $recordValue = $recordData[$field] ?? null;

            $matches = match ($operator) {
                '=' => $recordValue == $value,
                '!=' => $recordValue != $value,
                '>' => $recordValue > $value,
                '<' => $recordValue < $value,
                '>=' => $recordValue >= $value,
                '<=' => $recordValue <= $value,
                'contains' => is_string($recordValue) && str_contains($recordValue, $value),
                'not_contains' => is_string($recordValue) && !str_contains($recordValue, $value),
                'is_empty' => empty($recordValue),
                'is_not_empty' => !empty($recordValue),
                default => false,
            };

            if (!$matches) {
                return false;
            }
        }

        return true;
    }

    public function incrementTriggered(): void
    {
        $this->increment('triggered_count');
        $this->update(['last_triggered_at' => now()]);
    }
}
