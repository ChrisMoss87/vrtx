<?php

namespace App\Services\Competitor;

use App\Domain\User\Entities\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitorService
{
    public function getCompetitors(?string $search = null, bool $activeOnly = true): Collection
    {
        $query = Competitor::with(['sections', 'lastUpdatedBy']);

        if ($activeOnly) {
            $query->active();
        }

        if ($search) {
            $query->search($search);
        }

        return $query->orderBy('name')->get();
    }

    public function getCompetitor(int $id): ?Competitor
    {
        return Competitor::with([
            'sections',
            'objections.createdBy',
            'notes.createdBy',
            'notes.verifiedBy',
            'lastUpdatedBy',
        ])->find($id);
    }

    public function createCompetitor(array $data, User $user): Competitor
    {
        $competitor = DB::table('competitors')->insertGetId([
            'name' => $data['name'],
            'website' => $data['website'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'description' => $data['description'] ?? null,
            'market_position' => $data['market_position'] ?? null,
            'pricing_info' => $data['pricing_info'] ?? null,
            'last_updated_at' => now(),
            'last_updated_by' => $user->id,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Create default sections
        $defaultSections = [
            BattlecardSection::TYPE_STRENGTHS,
            BattlecardSection::TYPE_WEAKNESSES,
            BattlecardSection::TYPE_OUR_ADVANTAGES,
        ];

        foreach ($defaultSections as $order => $type) {
            DB::table('battlecard_sections')->insertGetId([
                'competitor_id' => $competitor->id,
                'section_type' => $type,
                'content' => '',
                'display_order' => $order,
                'created_by' => $user->id,
            ]);
        }

        return $competitor->fresh(['sections']);
    }

    public function updateCompetitor(Competitor $competitor, array $data, User $user): Competitor
    {
        $competitor->update(array_filter([
            'name' => $data['name'] ?? null,
            'website' => $data['website'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'description' => $data['description'] ?? null,
            'market_position' => $data['market_position'] ?? null,
            'pricing_info' => $data['pricing_info'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        $competitor->markUpdated($user->id);

        return $competitor->fresh(['sections']);
    }

    public function deleteCompetitor(Competitor $competitor): void
    {
        $competitor->delete();
    }

    public function updateSection(
        Competitor $competitor,
        int $sectionId,
        string $content,
        User $user
    ): BattlecardSection {
        $section = $competitor->sections()->findOrFail($sectionId);

        $section->update([
            'content' => $content,
        ]);

        $competitor->markUpdated($user->id);

        return $section;
    }

    public function createSection(
        Competitor $competitor,
        string $type,
        string $content,
        User $user
    ): BattlecardSection {
        $maxOrder = $competitor->sections()->max('display_order') ?? 0;

        $section = DB::table('battlecard_sections')->insertGetId([
            'competitor_id' => $competitor->id,
            'section_type' => $type,
            'content' => $content,
            'display_order' => $maxOrder + 1,
            'created_by' => $user->id,
        ]);

        $competitor->markUpdated($user->id);

        return $section;
    }

    public function deleteSection(Competitor $competitor, int $sectionId, User $user): void
    {
        $competitor->sections()->findOrFail($sectionId)->delete();
        $competitor->markUpdated($user->id);
    }

    public function reorderSections(Competitor $competitor, array $sectionIds, User $user): void
    {
        foreach ($sectionIds as $order => $sectionId) {
            DB::table('battlecard_sections')->where('id', $sectionId)
                ->where('competitor_id', $competitor->id)
                ->update(['display_order' => $order]);
        }

        $competitor->markUpdated($user->id);
    }

    public function addNote(
        Competitor $competitor,
        string $content,
        User $user,
        ?string $source = null
    ): CompetitorNote {
        $note = DB::table('competitor_notes')->insertGetId([
            'competitor_id' => $competitor->id,
            'content' => $content,
            'source' => $source,
            'created_by' => $user->id,
        ]);

        $competitor->markUpdated($user->id);

        return $note->fresh(['createdBy']);
    }

    public function verifyNote(CompetitorNote $note, User $user): CompetitorNote
    {
        $note->verify($user->id);
        return $note->fresh(['verifiedBy']);
    }

    public function deleteNote(Competitor $competitor, int $noteId): void
    {
        $competitor->notes()->findOrFail($noteId)->delete();
    }

    public function getBattlecard(Competitor $competitor): array
    {
        $competitor->loadMissing([
            'sections',
            'objections.createdBy',
            'notes.createdBy',
        ]);

        return [
            'id' => $competitor->id,
            'name' => $competitor->name,
            'logo_url' => $competitor->logo_url,
            'website' => $competitor->website,
            'description' => $competitor->description,
            'market_position' => $competitor->market_position,
            'pricing_info' => $competitor->pricing_info,
            'win_rate' => $competitor->getWinRate(),
            'total_deals' => $competitor->getTotalDeals(),
            'won_deals' => $competitor->getWonDeals(),
            'lost_deals' => $competitor->getLostDeals(),
            'sections' => $competitor->sections->map(fn ($s) => [
                'id' => $s->id,
                'type' => $s->section_type,
                'type_label' => $s->getTypeLabel(),
                'content' => $s->content,
                'content_lines' => $s->getContentLines(),
            ]),
            'objections' => $competitor->objections->take(5)->map(fn ($o) => [
                'id' => $o->id,
                'objection' => $o->objection,
                'counter_script' => $o->counter_script,
                'effectiveness_score' => $o->effectiveness_score,
                'effectiveness_label' => $o->getEffectivenessLabel(),
                'use_count' => $o->use_count,
            ]),
            'recent_notes' => $competitor->notes->take(5)->map(fn ($n) => [
                'id' => $n->id,
                'content' => $n->content,
                'source' => $n->source,
                'is_verified' => $n->is_verified,
                'created_by' => $n->createdBy?->name,
                'created_at' => $n->created_at->toISOString(),
            ]),
            'last_updated' => $competitor->last_updated_at?->toISOString(),
        ];
    }
}
