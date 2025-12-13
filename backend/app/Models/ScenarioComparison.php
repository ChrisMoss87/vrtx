<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioComparison extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
        'scenario_ids',
        'metrics',
    ];

    protected $casts = [
        'scenario_ids' => 'array',
        'metrics' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getScenarios()
    {
        return ForecastScenario::whereIn('id', $this->scenario_ids)->get();
    }
}
