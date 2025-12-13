<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class SendTimePrediction extends Model
{
    use HasFactory, BelongsToTenant;

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_CALL = 'call';

    protected $fillable = [
        'record_id',
        'channel',
        'optimal_hour',
        'optimal_day',
        'timezone',
        'confidence',
        'data_points',
        'last_updated_at',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
        'confidence' => 'decimal:4',
    ];

    /**
     * Update or create a prediction based on engagement data
     */
    public static function updatePrediction(
        int $recordId,
        string $channel,
        int $hour,
        ?int $day = null,
        string $timezone = 'UTC'
    ): self {
        $prediction = self::firstOrCreate(
            ['record_id' => $recordId, 'channel' => $channel],
            [
                'optimal_hour' => $hour,
                'optimal_day' => $day,
                'timezone' => $timezone,
                'confidence' => 0.1,
                'data_points' => 0,
            ]
        );

        // Simple weighted average for updating predictions
        $newDataPoints = $prediction->data_points + 1;
        $weight = 1 / $newDataPoints;

        $prediction->optimal_hour = (int) round(
            ($prediction->optimal_hour * (1 - $weight)) + ($hour * $weight)
        );

        if ($day !== null) {
            $prediction->optimal_day = (int) round(
                (($prediction->optimal_day ?? $day) * (1 - $weight)) + ($day * $weight)
            );
        }

        $prediction->data_points = $newDataPoints;
        $prediction->confidence = min(0.95, 0.1 + ($newDataPoints * 0.05));
        $prediction->last_updated_at = now();
        $prediction->save();

        return $prediction;
    }

    /**
     * Get the best send time for a contact
     */
    public static function getBestSendTime(int $recordId, string $channel = 'email'): ?array
    {
        $prediction = self::where('record_id', $recordId)
            ->where('channel', $channel)
            ->where('confidence', '>=', 0.3)
            ->first();

        if (!$prediction) {
            return null;
        }

        return [
            'hour' => $prediction->optimal_hour,
            'day' => $prediction->optimal_day,
            'timezone' => $prediction->timezone,
            'confidence' => $prediction->confidence,
        ];
    }
}
