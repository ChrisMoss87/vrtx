<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncedMeeting extends Model
{
    protected $fillable = [
        'user_id',
        'calendar_provider',
        'external_event_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'location',
        'is_online',
        'meeting_url',
        'organizer_email',
        'attendees',
        'status',
        'deal_id',
        'company_id',
        'outcome',
        'outcome_notes',
        'synced_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'synced_at' => 'datetime',
        'attendees' => 'array',
        'is_online' => 'boolean',
    ];

    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_OUTLOOK = 'outlook';

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_CANCELLED = 'cancelled';

    public const OUTCOME_COMPLETED = 'completed';
    public const OUTCOME_NO_SHOW = 'no_show';
    public const OUTCOME_RESCHEDULED = 'rescheduled';
    public const OUTCOME_CANCELLED = 'cancelled';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class, 'meeting_id');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now())
            ->where('status', '!=', self::STATUS_CANCELLED)
            ->orderBy('start_time');
    }

    public function scopePast($query)
    {
        return $query->where('end_time', '<', now())
            ->orderByDesc('start_time');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDeal($query, int $dealId)
    {
        return $query->where('deal_id', $dealId);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('start_time', [$from, $to]);
    }

    public function getDurationMinutes(): int
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    public function isUpcoming(): bool
    {
        return $this->start_time->isFuture() && $this->status !== self::STATUS_CANCELLED;
    }

    public function isPast(): bool
    {
        return $this->end_time->isPast();
    }

    public function isToday(): bool
    {
        return $this->start_time->isToday();
    }

    public function linkToDeal(int $dealId): void
    {
        $this->update(['deal_id' => $dealId]);
    }

    public function linkToCompany(int $companyId): void
    {
        $this->update(['company_id' => $companyId]);
    }

    public function recordOutcome(string $outcome, ?string $notes = null): void
    {
        $this->update([
            'outcome' => $outcome,
            'outcome_notes' => $notes,
        ]);
    }

    public function getUniqueParticipantEmails(): array
    {
        return $this->participants->pluck('email')->unique()->values()->toArray();
    }

    public function getExternalParticipants(): \Illuminate\Support\Collection
    {
        return $this->participants->filter(fn ($p) => !$p->is_organizer && $p->email !== $this->user->email);
    }
}
