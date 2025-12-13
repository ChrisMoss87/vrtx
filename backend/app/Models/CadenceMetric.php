<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CadenceMetric extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'cadence_id',
        'step_id',
        'date',
        'enrollments',
        'completions',
        'replies',
        'meetings_booked',
        'bounces',
        'unsubscribes',
        'emails_sent',
        'emails_opened',
        'emails_clicked',
        'calls_made',
        'calls_connected',
        'sms_sent',
        'sms_replied',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function cadence(): BelongsTo
    {
        return $this->belongsTo(Cadence::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(CadenceStep::class, 'step_id');
    }

    public static function incrementMetric(int $cadenceId, string $metric, ?int $stepId = null): void
    {
        $today = now()->toDateString();

        self::updateOrCreate(
            [
                'cadence_id' => $cadenceId,
                'step_id' => $stepId,
                'date' => $today,
            ],
            []
        )->increment($metric);
    }
}
