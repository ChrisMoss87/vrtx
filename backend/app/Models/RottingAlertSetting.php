<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RottingAlertSetting extends Model
{
    use HasFactory;

    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_NONE = 'none';

    protected $fillable = [
        'user_id',
        'pipeline_id',
        'email_digest_enabled',
        'digest_frequency',
        'in_app_notifications',
        'exclude_weekends',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'pipeline_id' => 'integer',
        'email_digest_enabled' => 'boolean',
        'in_app_notifications' => 'boolean',
        'exclude_weekends' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'email_digest_enabled' => true,
        'digest_frequency' => self::FREQUENCY_DAILY,
        'in_app_notifications' => true,
        'exclude_weekends' => false,
    ];

    /**
     * Get the user that owns this setting.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the pipeline this setting applies to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Scope to get global settings (no specific pipeline).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('pipeline_id');
    }

    /**
     * Scope to get settings for a specific pipeline.
     */
    public function scopeForPipeline($query, int $pipelineId)
    {
        return $query->where('pipeline_id', $pipelineId);
    }

    /**
     * Scope to get settings for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the effective setting for a user and pipeline.
     * Returns pipeline-specific setting if exists, otherwise global, otherwise defaults.
     */
    public static function getEffectiveForUser(int $userId, ?int $pipelineId = null): self
    {
        // Try pipeline-specific first
        if ($pipelineId) {
            $setting = static::forUser($userId)->forPipeline($pipelineId)->first();
            if ($setting) {
                return $setting;
            }
        }

        // Try global setting
        $globalSetting = static::forUser($userId)->global()->first();
        if ($globalSetting) {
            return $globalSetting;
        }

        // Return defaults
        return new static([
            'user_id' => $userId,
            'pipeline_id' => $pipelineId,
            'email_digest_enabled' => true,
            'digest_frequency' => self::FREQUENCY_DAILY,
            'in_app_notifications' => true,
            'exclude_weekends' => false,
        ]);
    }
}
