<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealRoomDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'name',
        'file_path',
        'file_size',
        'mime_type',
        'version',
        'description',
        'is_visible_to_external',
        'uploaded_by',
    ];

    protected $casts = [
        'is_visible_to_external' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(DealRoom::class, 'room_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function views(): HasMany
    {
        return $this->hasMany(DealRoomDocumentView::class, 'document_id');
    }

    public function getViewCount(): int
    {
        return $this->views()->count();
    }

    public function getUniqueViewerCount(): int
    {
        return $this->views()->distinct('member_id')->count('member_id');
    }

    public function getTotalTimeSpent(): int
    {
        return $this->views()->sum('time_spent_seconds') ?? 0;
    }

    public function recordView(int $memberId, int $timeSpentSeconds = 0): DealRoomDocumentView
    {
        return $this->views()->create([
            'member_id' => $memberId,
            'time_spent_seconds' => $timeSpentSeconds,
        ]);
    }

    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size ?? 0;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    public function scopeVisibleToExternal($query)
    {
        return $query->where('is_visible_to_external', true);
    }
}
