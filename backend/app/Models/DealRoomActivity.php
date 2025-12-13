<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealRoomActivity extends Model
{
    public $timestamps = false;

    public const TYPE_ROOM_CREATED = 'room_created';
    public const TYPE_MEMBER_JOINED = 'member_joined';
    public const TYPE_MEMBER_LEFT = 'member_left';
    public const TYPE_DOCUMENT_UPLOADED = 'document_uploaded';
    public const TYPE_DOCUMENT_VIEWED = 'document_viewed';
    public const TYPE_ACTION_CREATED = 'action_created';
    public const TYPE_ACTION_COMPLETED = 'action_completed';
    public const TYPE_MESSAGE_SENT = 'message_sent';
    public const TYPE_ROOM_ACCESSED = 'room_accessed';

    protected $fillable = [
        'room_id',
        'member_id',
        'activity_type',
        'activity_data',
        'created_at',
    ];

    protected $casts = [
        'activity_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(DealRoom::class, 'room_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(DealRoomMember::class, 'member_id');
    }

    public function getDescription(): string
    {
        $memberName = $this->member?->getName() ?? 'Someone';

        return match ($this->activity_type) {
            self::TYPE_ROOM_CREATED => 'Deal room was created',
            self::TYPE_MEMBER_JOINED => "{$memberName} joined the room",
            self::TYPE_MEMBER_LEFT => "{$memberName} left the room",
            self::TYPE_DOCUMENT_UPLOADED => "{$memberName} uploaded a document",
            self::TYPE_DOCUMENT_VIEWED => "{$memberName} viewed " . ($this->activity_data['document_name'] ?? 'a document'),
            self::TYPE_ACTION_CREATED => "{$memberName} added an action item",
            self::TYPE_ACTION_COMPLETED => "{$memberName} completed an action item",
            self::TYPE_MESSAGE_SENT => "{$memberName} sent a message",
            self::TYPE_ROOM_ACCESSED => "{$memberName} accessed the room",
            default => 'Unknown activity',
        };
    }
}
