<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookalikeExportLog extends Model
{
    use HasFactory;

    // Export destinations
    public const DEST_GOOGLE_ADS = 'google_ads';
    public const DEST_FACEBOOK = 'facebook';
    public const DEST_LINKEDIN = 'linkedin';
    public const DEST_CSV = 'csv';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'audience_id',
        'destination',
        'status',
        'records_exported',
        'export_config',
        'error_message',
        'exported_by',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'export_config' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function audience(): BelongsTo
    {
        return $this->belongsTo(LookalikeAudience::class, 'audience_id');
    }

    public function exporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    public function start(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    public function complete(int $count): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'records_exported' => $count,
            'completed_at' => now(),
        ]);
    }

    public function fail(string $message): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public static function getDestinations(): array
    {
        return [
            self::DEST_GOOGLE_ADS => 'Google Ads',
            self::DEST_FACEBOOK => 'Facebook Ads',
            self::DEST_LINKEDIN => 'LinkedIn Ads',
            self::DEST_CSV => 'CSV Download',
        ];
    }
}
