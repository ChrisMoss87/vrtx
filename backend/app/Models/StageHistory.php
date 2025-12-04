<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageHistory extends Model
{
    use HasFactory;

    protected $table = 'stage_history';

    protected $fillable = [
        'module_record_id',
        'pipeline_id',
        'from_stage_id',
        'to_stage_id',
        'changed_by',
        'reason',
        'duration_in_stage',
    ];

    protected $casts = [
        'module_record_id' => 'integer',
        'pipeline_id' => 'integer',
        'from_stage_id' => 'integer',
        'to_stage_id' => 'integer',
        'changed_by' => 'integer',
        'duration_in_stage' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the record this history entry belongs to.
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'module_record_id');
    }

    /**
     * Get the pipeline this history entry belongs to.
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Get the previous stage.
     */
    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'from_stage_id');
    }

    /**
     * Get the new stage.
     */
    public function toStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'to_stage_id');
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get stage history for a record.
     */
    public static function getForRecord(int $recordId, int $pipelineId = null)
    {
        $query = static::where('module_record_id', $recordId)
            ->with(['fromStage', 'toStage', 'changedBy'])
            ->orderBy('created_at', 'desc');

        if ($pipelineId) {
            $query->where('pipeline_id', $pipelineId);
        }

        return $query->get();
    }

    /**
     * Record a stage transition.
     */
    public static function recordTransition(
        int $recordId,
        int $pipelineId,
        ?int $fromStageId,
        int $toStageId,
        int $userId,
        ?string $reason = null
    ): self {
        // Calculate duration in previous stage
        $durationInStage = null;
        if ($fromStageId) {
            $lastEntry = static::where('module_record_id', $recordId)
                ->where('pipeline_id', $pipelineId)
                ->where('to_stage_id', $fromStageId)
                ->latest()
                ->first();

            if ($lastEntry) {
                $durationInStage = now()->diffInSeconds($lastEntry->created_at);
            }
        }

        return static::create([
            'module_record_id' => $recordId,
            'pipeline_id' => $pipelineId,
            'from_stage_id' => $fromStageId,
            'to_stage_id' => $toStageId,
            'changed_by' => $userId,
            'reason' => $reason,
            'duration_in_stage' => $durationInStage,
        ]);
    }
}
