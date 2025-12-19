<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalView extends Model
{
    protected $fillable = [
        'proposal_id',
        'viewer_email',
        'viewer_name',
        'session_id',
        'started_at',
        'ended_at',
        'time_spent',
        'sections_viewed',
        'ip_address',
        'user_agent',
        'device_type',
        'referrer',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'time_spent' => 'integer',
        'sections_viewed' => 'array',
    ];

    protected $attributes = [
        'time_spent' => 0,
    ];

    // Relationships
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    // Helpers
    public function endSession(): void
    {
        $this->ended_at = now();
        $this->time_spent = $this->started_at->diffInSeconds($this->ended_at);
        $this->save();

        // Update proposal total time spent
        $this->proposal->total_time_spent += $this->time_spent;
        $this->proposal->save();
    }

    public function trackSectionView(int $sectionId, int $seconds): void
    {
        $views = $this->sections_viewed ?? [];

        if (isset($views[$sectionId])) {
            $views[$sectionId] += $seconds;
        } else {
            $views[$sectionId] = $seconds;
        }

        $this->sections_viewed = $views;
        $this->save();
    }

    public function detectDeviceType(): string
    {
        $userAgent = strtolower($this->user_agent ?? '');

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }
        if (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
            return 'tablet';
        }

        return 'desktop';
    }
}
