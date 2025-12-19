<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id',
        'email',
        'name',
        'contact_id',
        'is_organizer',
        'response_status',
    ];

    protected $casts = [
        'is_organizer' => 'boolean',
    ];

    public const RESPONSE_ACCEPTED = 'accepted';
    public const RESPONSE_DECLINED = 'declined';
    public const RESPONSE_TENTATIVE = 'tentative';
    public const RESPONSE_NEEDS_ACTION = 'needsAction';

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(SyncedMeeting::class, 'meeting_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'contact_id');
    }

    public function isMatched(): bool
    {
        return $this->contact_id !== null;
    }

    public function hasAccepted(): bool
    {
        return $this->response_status === self::RESPONSE_ACCEPTED;
    }

    public function hasDeclined(): bool
    {
        return $this->response_status === self::RESPONSE_DECLINED;
    }

    public function matchToContact(int $contactId): void
    {
        $this->update(['contact_id' => $contactId]);
    }
}
