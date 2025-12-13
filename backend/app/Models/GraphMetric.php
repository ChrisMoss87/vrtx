<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GraphMetric extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'degree_centrality',
        'betweenness_centrality',
        'closeness_centrality',
        'cluster_id',
        'total_connected_revenue',
        'calculated_at',
    ];

    protected $casts = [
        'degree_centrality' => 'float',
        'betweenness_centrality' => 'float',
        'closeness_centrality' => 'float',
        'cluster_id' => 'integer',
        'total_connected_revenue' => 'float',
        'calculated_at' => 'datetime',
    ];

    /**
     * Scope to get metrics for a specific entity.
     */
    public function scopeForEntity($query, string $entityType, int $entityId)
    {
        return $query->where('entity_type', $entityType)
            ->where('entity_id', $entityId);
    }

    /**
     * Update or create metrics for an entity.
     */
    public static function upsertMetrics(
        string $entityType,
        int $entityId,
        array $metrics
    ): self {
        return static::updateOrCreate(
            ['entity_type' => $entityType, 'entity_id' => $entityId],
            array_merge($metrics, ['calculated_at' => now()])
        );
    }
}
