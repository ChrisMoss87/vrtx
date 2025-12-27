<?php

namespace App\Services\Competitor;

use App\Domain\User\Entities\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ObjectionService
{
    public function getObjections(Competitor $competitor): Collection
    {
        return $competitor->objections()
            ->with('createdBy')
            ->orderByDesc('effectiveness_score')
            ->orderByDesc('use_count')
            ->get();
    }

    public function createObjection(
        Competitor $competitor,
        string $objection,
        string $counterScript,
        User $user
    ): CompetitorObjection {
        return DB::table('competitor_objections')->insertGetId([
            'competitor_id' => $competitor->id,
            'objection' => $objection,
            'counter_script' => $counterScript,
            'created_by' => $user->id,
        ]);
    }

    public function updateObjection(
        CompetitorObjection $objection,
        array $data
    ): CompetitorObjection {
        $objection->update(array_filter([
            'objection' => $data['objection'] ?? null,
            'counter_script' => $data['counter_script'] ?? null,
        ], fn ($v) => $v !== null));

        return $objection->fresh();
    }

    public function deleteObjection(CompetitorObjection $objection): void
    {
        $objection->delete();
    }

    public function recordFeedback(
        CompetitorObjection $objection,
        bool $wasSuccessful,
        User $user,
        ?int $dealId = null,
        ?string $feedbackText = null
    ): ObjectionFeedback {
        return DB::table('objection_feedbacks')->insertGetId([
            'objection_id' => $objection->id,
            'deal_id' => $dealId,
            'was_successful' => $wasSuccessful,
            'feedback' => $feedbackText,
            'created_by' => $user->id,
        ]);
    }

    public function getTopObjections(Competitor $competitor, int $limit = 5): Collection
    {
        return $competitor->objections()
            ->where('use_count', '>=', 1)
            ->orderByDesc('effectiveness_score')
            ->take($limit)
            ->get();
    }

    public function getMostUsedObjections(Competitor $competitor, int $limit = 5): Collection
    {
        return $competitor->objections()
            ->orderByDesc('use_count')
            ->take($limit)
            ->get();
    }

    public function getObjectionFeedbackHistory(CompetitorObjection $objection): Collection
    {
        return $objection->feedback()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'was_successful' => $f->was_successful,
                'feedback' => $f->feedback,
                'deal_id' => $f->deal_id,
                'created_by' => $f->createdBy?->name,
                'created_at' => $f->created_at->toISOString(),
            ]);
    }
}
