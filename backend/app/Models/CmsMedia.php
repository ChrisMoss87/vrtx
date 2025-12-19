<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class CmsMedia extends Model
{
    use SoftDeletes;

    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_OTHER = 'other';

    protected $table = 'cms_media';

    protected $fillable = [
        'name',
        'filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'type',
        'width',
        'height',
        'alt_text',
        'caption',
        'description',
        'metadata',
        'folder_id',
        'tags',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
        'tags' => 'array',
        'folder_id' => 'integer',
        'uploaded_by' => 'integer',
    ];

    protected $attributes = [
        'disk' => 'public',
        'type' => self::TYPE_OTHER,
    ];

    protected $appends = ['url', 'thumbnail_url'];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(CmsMediaFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeImages($query)
    {
        return $query->where('type', self::TYPE_IMAGE);
    }

    public function scopeDocuments($query)
    {
        return $query->where('type', self::TYPE_DOCUMENT);
    }

    public function scopeVideos($query)
    {
        return $query->where('type', self::TYPE_VIDEO);
    }

    public function scopeInFolder($query, ?int $folderId)
    {
        if ($folderId === null) {
            return $query->whereNull('folder_id');
        }
        return $query->where('folder_id', $folderId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('filename', 'like', "%{$search}%")
              ->orWhere('alt_text', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->type !== self::TYPE_IMAGE) {
            return null;
        }
        // Could implement thumbnail generation here
        return $this->url;
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    public function isDocument(): bool
    {
        return $this->type === self::TYPE_DOCUMENT;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    public static function determineType(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => self::TYPE_IMAGE,
            str_starts_with($mimeType, 'video/') => self::TYPE_VIDEO,
            str_starts_with($mimeType, 'audio/') => self::TYPE_AUDIO,
            in_array($mimeType, [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ]) => self::TYPE_DOCUMENT,
            default => self::TYPE_OTHER,
        };
    }
}
