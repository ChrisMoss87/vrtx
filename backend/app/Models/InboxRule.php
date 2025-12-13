<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboxRule extends Model
{
    protected $fillable = [
        'inbox_id',
        'name',
        'description',
        'priority',
        'conditions',
        'condition_match',
        'actions',
        'is_active',
        'stop_processing',
        'triggered_count',
        'last_triggered_at',
        'created_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'stop_processing' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(SharedInbox::class, 'inbox_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc');
    }

    public function matches(InboxConversation $conversation, ?InboxMessage $message = null): bool
    {
        $conditions = $this->conditions ?? [];

        if (empty($conditions)) {
            return false;
        }

        $results = [];

        foreach ($conditions as $condition) {
            $results[] = $this->evaluateCondition($condition, $conversation, $message);
        }

        if ($this->condition_match === 'all') {
            return !in_array(false, $results, true);
        }

        // any
        return in_array(true, $results, true);
    }

    protected function evaluateCondition(array $condition, InboxConversation $conversation, ?InboxMessage $message): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';

        $actualValue = $this->getFieldValue($field, $conversation, $message);

        return match ($operator) {
            'equals' => strtolower((string) $actualValue) === strtolower((string) $value),
            'not_equals' => strtolower((string) $actualValue) !== strtolower((string) $value),
            'contains' => str_contains(strtolower((string) $actualValue), strtolower((string) $value)),
            'not_contains' => !str_contains(strtolower((string) $actualValue), strtolower((string) $value)),
            'starts_with' => str_starts_with(strtolower((string) $actualValue), strtolower((string) $value)),
            'ends_with' => str_ends_with(strtolower((string) $actualValue), strtolower((string) $value)),
            'is_empty' => empty($actualValue),
            'is_not_empty' => !empty($actualValue),
            'matches_regex' => (bool) preg_match('/' . $value . '/i', (string) $actualValue),
            default => false,
        };
    }

    protected function getFieldValue(string $field, InboxConversation $conversation, ?InboxMessage $message): mixed
    {
        return match ($field) {
            'subject' => $conversation->subject,
            'from_email' => $message?->from_email ?? $conversation->contact_email,
            'from_name' => $message?->from_name ?? $conversation->contact_name,
            'body' => $message?->body_text ?? '',
            'channel' => $conversation->channel,
            'contact_email' => $conversation->contact_email,
            'contact_name' => $conversation->contact_name,
            'has_attachments' => $message?->hasAttachments() ? 'true' : 'false',
            default => '',
        };
    }

    public function execute(InboxConversation $conversation): void
    {
        foreach ($this->actions ?? [] as $action) {
            $this->executeAction($action, $conversation);
        }

        $this->update([
            'triggered_count' => $this->triggered_count + 1,
            'last_triggered_at' => now(),
        ]);
    }

    protected function executeAction(array $action, InboxConversation $conversation): void
    {
        $type = $action['type'] ?? '';
        $value = $action['value'] ?? null;

        match ($type) {
            'assign' => $conversation->assignTo((int) $value),
            'set_priority' => $conversation->update(['priority' => $value]),
            'set_status' => $conversation->update(['status' => $value]),
            'add_tag' => $conversation->addTag($value),
            'mark_spam' => $conversation->markAsSpam(),
            'star' => $conversation->update(['is_starred' => true]),
            default => null,
        };
    }
}
