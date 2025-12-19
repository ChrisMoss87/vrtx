<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LookalikeMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'audience_id',
        'contact_id',
        'contact_module',
        'similarity_score',
        'match_factors',
        'enrichment_data',
        'enriched_at',
        'exported',
        'exported_at',
        'export_destination',
    ];

    protected $casts = [
        'similarity_score' => 'decimal:2',
        'match_factors' => 'array',
        'enrichment_data' => 'array',
        'exported' => 'boolean',
        'enriched_at' => 'datetime',
        'exported_at' => 'datetime',
    ];

    public function audience(): BelongsTo
    {
        return $this->belongsTo(LookalikeAudience::class, 'audience_id');
    }

    public function markAsExported(string $destination): void
    {
        $this->update([
            'exported' => true,
            'exported_at' => now(),
            'export_destination' => $destination,
        ]);
    }

    public function getScoreLabel(): string
    {
        if ($this->similarity_score >= 90) {
            return 'Excellent';
        }
        if ($this->similarity_score >= 80) {
            return 'Very Good';
        }
        if ($this->similarity_score >= 70) {
            return 'Good';
        }
        if ($this->similarity_score >= 60) {
            return 'Fair';
        }
        return 'Low';
    }

    public function getTopMatchFactors(int $limit = 3): array
    {
        $factors = $this->match_factors ?? [];
        arsort($factors);
        return array_slice($factors, 0, $limit, true);
    }
}
