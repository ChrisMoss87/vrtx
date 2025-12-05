<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlueprintSlaEscalationLog extends Model
{
    use HasFactory;

    protected $table = 'blueprint_sla_escalation_logs';

    protected $fillable = [
        'sla_instance_id',
        'escalation_id',
        'executed_at',
        'status',
        'result',
    ];

    protected $casts = [
        'sla_instance_id' => 'integer',
        'escalation_id' => 'integer',
        'executed_at' => 'datetime',
        'result' => 'array',
    ];

    /**
     * Get the SLA instance.
     */
    public function slaInstance(): BelongsTo
    {
        return $this->belongsTo(BlueprintSlaInstance::class, 'sla_instance_id');
    }

    /**
     * Get the escalation.
     */
    public function escalation(): BelongsTo
    {
        return $this->belongsTo(BlueprintSlaEscalation::class, 'escalation_id');
    }

    /**
     * Check if escalation succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if escalation failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
