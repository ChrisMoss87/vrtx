<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Competitor;

use App\Domain\Competitor\Entities\Competitor as CompetitorEntity;
use App\Domain\Competitor\Repositories\CompetitorRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class EloquentCompetitorRepository implements CompetitorRepositoryInterface
{
    private const TABLE_COMPETITORS = 'competitors';
    private const TABLE_BATTLECARD_SECTIONS = 'battlecard_sections';
    private const TABLE_OBJECTIONS = 'competitor_objections';
    private const TABLE_NOTES = 'competitor_notes';
    private const TABLE_DEAL_COMPETITORS = 'deal_competitors';
    private const TABLE_USERS = 'users';
    private const TABLE_DEALS = 'deals';

    // Section types
    private const SECTION_TYPES = [
        'strengths' => 'Strengths',
        'weaknesses' => 'Weaknesses',
        'our_advantages' => 'Our Advantages',
        'pricing' => 'Pricing',
        'resources' => 'Resources',
        'win_stories' => 'Win Stories',
    ];

    // ==========================================
    // COMPETITOR QUERIES
    // ==========================================

    public function list(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE_COMPETITORS);

        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->where('is_active', true);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('website', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['market_position'])) {
            $query->where('market_position', $filters['market_position']);
        }

        $sortField = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        $total = $query->count();

        $items = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(fn($row) => $this->rowToArrayWithCounts($row))
            ->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function findById(int $id): ?CompetitorEntity
    {
        $row = DB::table(self::TABLE_COMPETITORS)->where('id', $id)->first();
        return $row ? $this->toDomainEntity($row) : null;
    }

    public function findByIdAsArray(int $id): ?array
    {
        $row = DB::table(self::TABLE_COMPETITORS)->where('id', $id)->first();
        return $row ? $this->rowToArray($row) : null;
    }

    public function findByIdWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE_COMPETITORS)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = $this->rowToArray($row);

        // Load sections
        $result['sections'] = DB::table(self::TABLE_BATTLECARD_SECTIONS)
            ->where('competitor_id', $id)
            ->orderBy('display_order')
            ->get()
            ->map(fn($s) => $this->sectionRowToArray($s))
            ->all();

        // Load objections with creator
        $objections = DB::table(self::TABLE_OBJECTIONS)
            ->where('competitor_id', $id)
            ->orderByDesc('effectiveness_score')
            ->get();

        $result['objections'] = $objections->map(function ($obj) {
            $array = $this->objectionRowToArray($obj);
            if ($obj->created_by) {
                $creator = DB::table(self::TABLE_USERS)
                    ->select('id', 'name', 'email')
                    ->where('id', $obj->created_by)
                    ->first();
                $array['created_by'] = $creator ? (array) $creator : null;
            }
            return $array;
        })->all();

        // Load last updated by
        if ($row->last_updated_by) {
            $updater = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $row->last_updated_by)
                ->first();
            $result['last_updated_by'] = $updater ? (array) $updater : null;
        }

        return $result;
    }

    public function getActiveList(): array
    {
        return DB::table(self::TABLE_COMPETITORS)
            ->where('is_active', true)
            ->orderBy('name')
            ->select('id', 'name', 'logo_url')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function search(string $searchQuery, int $limit = 10): array
    {
        return DB::table(self::TABLE_COMPETITORS)
            ->where('is_active', true)
            ->where(function ($q) use ($searchQuery) {
                $q->where('name', 'ilike', "%{$searchQuery}%")
                    ->orWhere('website', 'ilike', "%{$searchQuery}%")
                    ->orWhere('description', 'ilike', "%{$searchQuery}%");
            })
            ->limit($limit)
            ->select('id', 'name', 'logo_url', 'website')
            ->get()
            ->map(fn($row) => (array) $row)
            ->all();
    }

    public function getStats(int $id): array
    {
        $winRate = $this->calculateWinRate($id);
        $dealStats = $this->getDealStats($id);

        return [
            'win_rate' => $winRate,
            'total_deals' => $dealStats['total'],
            'won_deals' => $dealStats['won'],
            'lost_deals' => $dealStats['lost'],
            'active_deals' => DB::table(self::TABLE_DEAL_COMPETITORS)
                ->where('competitor_id', $id)
                ->where('outcome', 'active')
                ->count(),
            'objection_count' => DB::table(self::TABLE_OBJECTIONS)
                ->where('competitor_id', $id)
                ->count(),
            'note_count' => DB::table(self::TABLE_NOTES)
                ->where('competitor_id', $id)
                ->count(),
            'avg_objection_effectiveness' => DB::table(self::TABLE_OBJECTIONS)
                ->where('competitor_id', $id)
                ->whereNotNull('effectiveness_score')
                ->avg('effectiveness_score'),
        ];
    }

    public function getWinRate(int $id): ?float
    {
        return $this->calculateWinRate($id);
    }

    // ==========================================
    // BATTLECARD SECTIONS
    // ==========================================

    public function getBattlecardSections(int $competitorId): array
    {
        return DB::table(self::TABLE_BATTLECARD_SECTIONS)
            ->where('competitor_id', $competitorId)
            ->orderBy('display_order')
            ->get()
            ->map(fn($row) => $this->sectionRowToArray($row))
            ->all();
    }

    public function getBattlecardSectionByType(int $competitorId, string $type): ?array
    {
        $section = DB::table(self::TABLE_BATTLECARD_SECTIONS)
            ->where('competitor_id', $competitorId)
            ->where('section_type', $type)
            ->first();

        return $section ? $this->sectionRowToArray($section) : null;
    }

    public function findBattlecardSectionById(int $sectionId): ?array
    {
        $section = DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('id', $sectionId)->first();
        return $section ? $this->sectionRowToArray($section) : null;
    }

    public function getCompetitorBattlecard(int $id): array
    {
        $competitor = DB::table(self::TABLE_COMPETITORS)->where('id', $id)->first();

        if (!$competitor) {
            throw new \RuntimeException("Competitor not found: {$id}");
        }

        $sections = DB::table(self::TABLE_BATTLECARD_SECTIONS)
            ->where('competitor_id', $id)
            ->get()
            ->keyBy('section_type');

        $topObjections = DB::table(self::TABLE_OBJECTIONS)
            ->where('competitor_id', $id)
            ->orderByDesc('effectiveness_score')
            ->limit(5)
            ->get()
            ->map(fn($row) => $this->objectionRowToArray($row))
            ->all();

        return [
            'competitor' => $this->rowToArray($competitor),
            'strengths' => $this->sectionRowToArray($sections->get('strengths')),
            'weaknesses' => $this->sectionRowToArray($sections->get('weaknesses')),
            'our_advantages' => $this->sectionRowToArray($sections->get('our_advantages')),
            'pricing' => $this->sectionRowToArray($sections->get('pricing')),
            'resources' => $this->sectionRowToArray($sections->get('resources')),
            'win_stories' => $this->sectionRowToArray($sections->get('win_stories')),
            'top_objections' => $topObjections,
            'win_rate' => $this->calculateWinRate($id),
            'total_deals' => $this->getDealStats($id)['total'],
        ];
    }

    public function updateBattlecardSection(int $sectionId, array $data): array
    {
        $section = DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('id', $sectionId)->first();

        if (!$section) {
            throw new \RuntimeException("Section not found: {$sectionId}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }
        if (isset($data['display_order'])) {
            $updateData['display_order'] = $data['display_order'];
        }

        DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('id', $sectionId)->update($updateData);

        $updated = DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('id', $sectionId)->first();

        return $this->sectionRowToArray($updated);
    }

    public function createBattlecardSection(int $competitorId, array $data): array
    {
        $sectionId = DB::table(self::TABLE_BATTLECARD_SECTIONS)->insertGetId([
            'competitor_id' => $competitorId,
            'section_type' => $data['section_type'],
            'content' => $data['content'] ?? '',
            'display_order' => $data['display_order'] ?? 99,
            'created_by' => $data['created_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $section = DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('id', $sectionId)->first();

        return $this->sectionRowToArray($section);
    }

    public function deleteBattlecardSection(int $sectionId): bool
    {
        return DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('id', $sectionId)->delete() > 0;
    }

    public function reorderBattlecardSections(int $competitorId, array $sectionIds): void
    {
        foreach ($sectionIds as $order => $sectionId) {
            DB::table(self::TABLE_BATTLECARD_SECTIONS)
                ->where('id', $sectionId)
                ->where('competitor_id', $competitorId)
                ->update(['display_order' => $order, 'updated_at' => now()]);
        }
    }

    // ==========================================
    // OBJECTIONS
    // ==========================================

    public function getObjections(int $competitorId): array
    {
        return DB::table(self::TABLE_OBJECTIONS)
            ->where('competitor_id', $competitorId)
            ->orderByDesc('effectiveness_score')
            ->get()
            ->map(function ($row) {
                $array = $this->objectionRowToArray($row);
                if ($row->created_by) {
                    $creator = DB::table(self::TABLE_USERS)
                        ->select('id', 'name', 'email')
                        ->where('id', $row->created_by)
                        ->first();
                    $array['created_by'] = $creator ? (array) $creator : null;
                }
                return $array;
            })
            ->all();
    }

    public function findObjectionById(int $id): ?array
    {
        $objection = DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->first();

        if (!$objection) {
            return null;
        }

        $array = $this->objectionRowToArray($objection);

        // Load competitor
        $competitor = DB::table(self::TABLE_COMPETITORS)->where('id', $objection->competitor_id)->first();
        $array['competitor'] = $competitor ? $this->rowToArray($competitor) : null;

        // Load creator
        if ($objection->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $objection->created_by)
                ->first();
            $array['created_by'] = $creator ? (array) $creator : null;
        }

        return $array;
    }

    public function createObjection(int $competitorId, array $data): array
    {
        $objectionId = DB::table(self::TABLE_OBJECTIONS)->insertGetId([
            'competitor_id' => $competitorId,
            'objection' => $data['objection'],
            'counter_script' => $data['counter_script'],
            'created_by' => $data['created_by'],
            'use_count' => 0,
            'success_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $objection = DB::table(self::TABLE_OBJECTIONS)->where('id', $objectionId)->first();

        return $this->objectionRowToArray($objection);
    }

    public function updateObjection(int $id, array $data): array
    {
        $objection = DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->first();

        if (!$objection) {
            throw new \RuntimeException("Objection not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['objection'])) {
            $updateData['objection'] = $data['objection'];
        }
        if (isset($data['counter_script'])) {
            $updateData['counter_script'] = $data['counter_script'];
        }

        DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->first();

        return $this->objectionRowToArray($updated);
    }

    public function deleteObjection(int $id): bool
    {
        return DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->delete() > 0;
    }

    public function recordObjectionUsage(int $id, bool $wasSuccessful): array
    {
        $objection = DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->first();

        if (!$objection) {
            throw new \RuntimeException("Objection not found: {$id}");
        }

        $useCount = ($objection->use_count ?? 0) + 1;
        $successCount = ($objection->success_count ?? 0) + ($wasSuccessful ? 1 : 0);
        $effectivenessScore = $useCount > 0 ? round(($successCount / $useCount) * 100, 1) : null;

        DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->update([
            'use_count' => $useCount,
            'success_count' => $successCount,
            'effectiveness_score' => $effectivenessScore,
            'updated_at' => now(),
        ]);

        $updated = DB::table(self::TABLE_OBJECTIONS)->where('id', $id)->first();

        return $this->objectionRowToArray($updated);
    }

    public function getTopEffectiveObjections(int $limit = 10): array
    {
        return DB::table(self::TABLE_OBJECTIONS)
            ->join(self::TABLE_COMPETITORS, self::TABLE_OBJECTIONS . '.competitor_id', '=', self::TABLE_COMPETITORS . '.id')
            ->whereNotNull(self::TABLE_OBJECTIONS . '.effectiveness_score')
            ->where(self::TABLE_OBJECTIONS . '.use_count', '>=', 3)
            ->orderByDesc(self::TABLE_OBJECTIONS . '.effectiveness_score')
            ->limit($limit)
            ->select(self::TABLE_OBJECTIONS . '.*', self::TABLE_COMPETITORS . '.name as competitor_name')
            ->get()
            ->map(function ($row) {
                $array = $this->objectionRowToArray($row);
                $array['competitor'] = ['id' => $row->competitor_id, 'name' => $row->competitor_name];
                return $array;
            })
            ->all();
    }

    // ==========================================
    // NOTES
    // ==========================================

    public function getNotes(int $competitorId, array $filters = []): array
    {
        $query = DB::table(self::TABLE_NOTES)
            ->where('competitor_id', $competitorId);

        if (isset($filters['verified_only']) && $filters['verified_only']) {
            $query->where('is_verified', true);
        }

        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($row) {
                $array = $this->noteRowToArray($row);
                if ($row->created_by) {
                    $creator = DB::table(self::TABLE_USERS)
                        ->select('id', 'name', 'email')
                        ->where('id', $row->created_by)
                        ->first();
                    $array['created_by'] = $creator ? (array) $creator : null;
                }
                if ($row->verified_by) {
                    $verifier = DB::table(self::TABLE_USERS)
                        ->select('id', 'name', 'email')
                        ->where('id', $row->verified_by)
                        ->first();
                    $array['verified_by'] = $verifier ? (array) $verifier : null;
                }
                return $array;
            })
            ->all();
    }

    public function findNoteById(int $id): ?array
    {
        $note = DB::table(self::TABLE_NOTES)->where('id', $id)->first();
        return $note ? $this->noteRowToArray($note) : null;
    }

    public function createNote(int $competitorId, array $data): array
    {
        $noteId = DB::table(self::TABLE_NOTES)->insertGetId([
            'competitor_id' => $competitorId,
            'content' => $data['content'],
            'source' => $data['source'] ?? null,
            'created_by' => $data['created_by'],
            'is_verified' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $note = DB::table(self::TABLE_NOTES)->where('id', $noteId)->first();

        return $this->noteRowToArray($note);
    }

    public function updateNote(int $id, array $data): array
    {
        $note = DB::table(self::TABLE_NOTES)->where('id', $id)->first();

        if (!$note) {
            throw new \RuntimeException("Note not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['content'])) {
            $updateData['content'] = $data['content'];
        }
        if (isset($data['source'])) {
            $updateData['source'] = $data['source'];
        }

        DB::table(self::TABLE_NOTES)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_NOTES)->where('id', $id)->first();

        return $this->noteRowToArray($updated);
    }

    public function deleteNote(int $id): bool
    {
        return DB::table(self::TABLE_NOTES)->where('id', $id)->delete() > 0;
    }

    public function verifyNote(int $id, int $userId): array
    {
        $note = DB::table(self::TABLE_NOTES)->where('id', $id)->first();

        if (!$note) {
            throw new \RuntimeException("Note not found: {$id}");
        }

        DB::table(self::TABLE_NOTES)->where('id', $id)->update([
            'is_verified' => true,
            'verified_by' => $userId,
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        $updated = DB::table(self::TABLE_NOTES)->where('id', $id)->first();

        return $this->noteRowToArray($updated);
    }

    public function unverifyNote(int $id): array
    {
        $note = DB::table(self::TABLE_NOTES)->where('id', $id)->first();

        if (!$note) {
            throw new \RuntimeException("Note not found: {$id}");
        }

        DB::table(self::TABLE_NOTES)->where('id', $id)->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            'updated_at' => now(),
        ]);

        $updated = DB::table(self::TABLE_NOTES)->where('id', $id)->first();

        return $this->noteRowToArray($updated);
    }

    // ==========================================
    // DEAL COMPETITORS
    // ==========================================

    public function addCompetitorToDeal(int $dealId, int $competitorId, array $data = []): array
    {
        $dealCompetitorId = DB::table(self::TABLE_DEAL_COMPETITORS)->insertGetId([
            'deal_id' => $dealId,
            'competitor_id' => $competitorId,
            'outcome' => $data['outcome'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'added_by' => $data['added_by'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dealCompetitor = DB::table(self::TABLE_DEAL_COMPETITORS)->where('id', $dealCompetitorId)->first();

        return $this->dealCompetitorRowToArray($dealCompetitor);
    }

    public function updateDealCompetitor(int $id, array $data): array
    {
        $dealCompetitor = DB::table(self::TABLE_DEAL_COMPETITORS)->where('id', $id)->first();

        if (!$dealCompetitor) {
            throw new \RuntimeException("DealCompetitor not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['outcome'])) {
            $updateData['outcome'] = $data['outcome'];
        }
        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
        }

        DB::table(self::TABLE_DEAL_COMPETITORS)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_DEAL_COMPETITORS)->where('id', $id)->first();

        return $this->dealCompetitorRowToArray($updated);
    }

    public function removeCompetitorFromDeal(int $id): bool
    {
        return DB::table(self::TABLE_DEAL_COMPETITORS)->where('id', $id)->delete() > 0;
    }

    public function getCompetitorDeals(int $competitorId, array $filters = []): array
    {
        $query = DB::table(self::TABLE_DEAL_COMPETITORS)
            ->where('competitor_id', $competitorId);

        if (!empty($filters['outcome'])) {
            $query->where('outcome', $filters['outcome']);
        }

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($row) {
                $array = $this->dealCompetitorRowToArray($row);
                // Load deal
                $deal = DB::table(self::TABLE_DEALS)->where('id', $row->deal_id)->first();
                $array['deal'] = $deal ? (array) $deal : null;
                return $array;
            })
            ->all();
    }

    // ==========================================
    // COMPETITOR COMMANDS
    // ==========================================

    public function save(CompetitorEntity $entity): CompetitorEntity
    {
        $data = $this->toRowData($entity);

        if ($entity->getId()) {
            DB::table(self::TABLE_COMPETITORS)
                ->where('id', $entity->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $entity->getId();
        } else {
            $id = DB::table(self::TABLE_COMPETITORS)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function create(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $competitorId = DB::table(self::TABLE_COMPETITORS)->insertGetId([
                'name' => $data['name'],
                'website' => $data['website'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'description' => $data['description'] ?? null,
                'market_position' => $data['market_position'] ?? null,
                'pricing_info' => isset($data['pricing_info']) ? json_encode($data['pricing_info']) : null,
                'is_active' => $data['is_active'] ?? true,
                'last_updated_at' => now(),
                'last_updated_by' => $data['last_updated_by'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create default battlecard sections
            $order = 0;
            foreach (self::SECTION_TYPES as $type => $label) {
                DB::table(self::TABLE_BATTLECARD_SECTIONS)->insert([
                    'competitor_id' => $competitorId,
                    'section_type' => $type,
                    'content' => '',
                    'display_order' => $order++,
                    'created_by' => $data['created_by'] ?? $data['last_updated_by'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $competitor = DB::table(self::TABLE_COMPETITORS)->where('id', $competitorId)->first();

            return $this->rowToArray($competitor);
        });
    }

    public function update(int $id, array $data): array
    {
        $competitor = DB::table(self::TABLE_COMPETITORS)->where('id', $id)->first();

        if (!$competitor) {
            throw new \RuntimeException("Competitor not found: {$id}");
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['website'])) {
            $updateData['website'] = $data['website'];
        }
        if (isset($data['logo_url'])) {
            $updateData['logo_url'] = $data['logo_url'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['market_position'])) {
            $updateData['market_position'] = $data['market_position'];
        }
        if (isset($data['pricing_info'])) {
            $updateData['pricing_info'] = json_encode($data['pricing_info']);
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        DB::table(self::TABLE_COMPETITORS)->where('id', $id)->update($updateData);

        $updated = DB::table(self::TABLE_COMPETITORS)->where('id', $id)->first();

        return $this->rowToArray($updated);
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            DB::table(self::TABLE_BATTLECARD_SECTIONS)->where('competitor_id', $id)->delete();
            DB::table(self::TABLE_OBJECTIONS)->where('competitor_id', $id)->delete();
            DB::table(self::TABLE_NOTES)->where('competitor_id', $id)->delete();
            DB::table(self::TABLE_DEAL_COMPETITORS)->where('competitor_id', $id)->delete();

            return DB::table(self::TABLE_COMPETITORS)->where('id', $id)->delete() > 0;
        });
    }

    public function markUpdated(int $id, int $userId): void
    {
        DB::table(self::TABLE_COMPETITORS)->where('id', $id)->update([
            'last_updated_at' => now(),
            'last_updated_by' => $userId,
            'updated_at' => now(),
        ]);
    }

    // ==========================================
    // ANALYTICS
    // ==========================================

    public function getAnalyticsDashboard(): array
    {
        $competitors = DB::table(self::TABLE_COMPETITORS)
            ->where('is_active', true)
            ->get();

        $totalDeals = DB::table(self::TABLE_DEAL_COMPETITORS)->count();
        $wonDeals = DB::table(self::TABLE_DEAL_COMPETITORS)->where('outcome', 'won')->count();
        $lostDeals = DB::table(self::TABLE_DEAL_COMPETITORS)->where('outcome', 'lost')->count();

        // Get deal counts per competitor
        $dealCounts = DB::table(self::TABLE_DEAL_COMPETITORS)
            ->selectRaw('competitor_id, COUNT(*) as deal_count')
            ->groupBy('competitor_id')
            ->pluck('deal_count', 'competitor_id');

        $competitorsList = $competitors->map(function ($c) use ($dealCounts) {
            $c->deal_count = $dealCounts[$c->id] ?? 0;
            return $c;
        });

        $winRates = [];
        foreach ($competitors as $competitor) {
            $stats = $this->getDealStats($competitor->id);
            $winRate = $this->calculateWinRate($competitor->id);
            if ($stats['total'] >= 3) {
                $winRates[] = [
                    'id' => $competitor->id,
                    'name' => $competitor->name,
                    'win_rate' => $winRate,
                    'total_deals' => $stats['total'],
                ];
            }
        }

        usort($winRates, fn($a, $b) => ($b['win_rate'] ?? 0) <=> ($a['win_rate'] ?? 0));

        return [
            'total_competitors' => $competitors->count(),
            'active_competitors' => $competitors->where('is_active', true)->count(),
            'total_competitive_deals' => $totalDeals,
            'overall_win_rate' => ($wonDeals + $lostDeals) > 0
                ? round(($wonDeals / ($wonDeals + $lostDeals)) * 100, 1)
                : null,
            'most_encountered' => $competitorsList
                ->sortByDesc('deal_count')
                ->take(5)
                ->map(fn($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'deal_count' => $c->deal_count,
                ])
                ->values()
                ->all(),
            'win_rates' => array_slice($winRates, 0, 10),
        ];
    }

    public function getWinLossAnalysis(int $competitorId): array
    {
        $competitor = DB::table(self::TABLE_COMPETITORS)->where('id', $competitorId)->first();

        if (!$competitor) {
            throw new \RuntimeException("Competitor not found: {$competitorId}");
        }

        $deals = DB::table(self::TABLE_DEAL_COMPETITORS)
            ->where('competitor_id', $competitorId)
            ->get();

        $dealIds = $deals->pluck('deal_id')->all();
        $dealsData = DB::table(self::TABLE_DEALS)
            ->whereIn('id', $dealIds)
            ->get()
            ->keyBy('id');

        $wonDeals = $deals->where('outcome', 'won');
        $lostDeals = $deals->where('outcome', 'lost');

        return [
            'competitor' => $competitor->name,
            'total_deals' => $deals->count(),
            'won' => $wonDeals->count(),
            'lost' => $lostDeals->count(),
            'active' => $deals->where('outcome', 'active')->count(),
            'win_rate' => $this->calculateWinRate($competitorId),
            'won_value' => $wonDeals->sum(fn($dc) => $dealsData[$dc->deal_id]->amount ?? 0),
            'lost_value' => $lostDeals->sum(fn($dc) => $dealsData[$dc->deal_id]->amount ?? 0),
            'recent_wins' => $wonDeals->sortByDesc('created_at')->take(5)->map(function ($dc) use ($dealsData) {
                $deal = $dealsData[$dc->deal_id] ?? null;
                return [
                    'deal_id' => $dc->deal_id,
                    'deal_name' => $deal?->name,
                    'amount' => $deal?->amount,
                    'closed_at' => $dc->updated_at,
                ];
            })->values()->all(),
            'recent_losses' => $lostDeals->sortByDesc('created_at')->take(5)->map(function ($dc) use ($dealsData) {
                $deal = $dealsData[$dc->deal_id] ?? null;
                return [
                    'deal_id' => $dc->deal_id,
                    'deal_name' => $deal?->name,
                    'amount' => $deal?->amount,
                    'closed_at' => $dc->updated_at,
                ];
            })->values()->all(),
        ];
    }

    public function compareCompetitors(array $competitorIds): array
    {
        $competitors = DB::table(self::TABLE_COMPETITORS)
            ->whereIn('id', $competitorIds)
            ->get();

        $comparison = [];

        foreach ($competitors as $competitor) {
            $sections = DB::table(self::TABLE_BATTLECARD_SECTIONS)
                ->where('competitor_id', $competitor->id)
                ->get()
                ->keyBy('section_type');

            $comparison[] = [
                'id' => $competitor->id,
                'name' => $competitor->name,
                'market_position' => $competitor->market_position,
                'win_rate' => $this->calculateWinRate($competitor->id),
                'total_deals' => $this->getDealStats($competitor->id)['total'],
                'strengths' => $this->getContentLines($sections->get('strengths')?->content),
                'weaknesses' => $this->getContentLines($sections->get('weaknesses')?->content),
                'our_advantages' => $this->getContentLines($sections->get('our_advantages')?->content),
            ];
        }

        return $comparison;
    }

    public function getMarketPositionDistribution(): array
    {
        return DB::table(self::TABLE_COMPETITORS)
            ->where('is_active', true)
            ->selectRaw('market_position, count(*) as count')
            ->groupBy('market_position')
            ->pluck('count', 'market_position')
            ->all();
    }

    // ==========================================
    // PRIVATE HELPER METHODS
    // ==========================================

    private function rowToArray(stdClass $row): array
    {
        $array = (array) $row;

        // Handle JSON fields
        if (isset($array['pricing_info']) && is_string($array['pricing_info'])) {
            $array['pricing_info'] = json_decode($array['pricing_info'], true);
        }
        if (isset($array['strengths']) && is_string($array['strengths'])) {
            $array['strengths'] = json_decode($array['strengths'], true);
        }
        if (isset($array['weaknesses']) && is_string($array['weaknesses'])) {
            $array['weaknesses'] = json_decode($array['weaknesses'], true);
        }

        return $array;
    }

    private function rowToArrayWithCounts(stdClass $row): array
    {
        $array = $this->rowToArray($row);

        // Add deal count
        $array['deal_competitors_count'] = DB::table(self::TABLE_DEAL_COMPETITORS)
            ->where('competitor_id', $row->id)
            ->count();

        // Load last updated by
        if ($row->last_updated_by) {
            $updater = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $row->last_updated_by)
                ->first();
            $array['last_updated_by'] = $updater ? (array) $updater : null;
        }

        return $array;
    }

    private function sectionRowToArray(?stdClass $section): ?array
    {
        if (!$section) {
            return null;
        }

        return (array) $section;
    }

    private function objectionRowToArray(stdClass $objection): array
    {
        return (array) $objection;
    }

    private function noteRowToArray(stdClass $note): array
    {
        return (array) $note;
    }

    private function dealCompetitorRowToArray(stdClass $dealCompetitor): array
    {
        return (array) $dealCompetitor;
    }

    private function calculateWinRate(int $competitorId): ?float
    {
        $stats = $this->getDealStats($competitorId);

        $closedDeals = $stats['won'] + $stats['lost'];

        if ($closedDeals === 0) {
            return null;
        }

        return round(($stats['won'] / $closedDeals) * 100, 1);
    }

    private function getDealStats(int $competitorId): array
    {
        $outcomes = DB::table(self::TABLE_DEAL_COMPETITORS)
            ->where('competitor_id', $competitorId)
            ->selectRaw("outcome, COUNT(*) as count")
            ->groupBy('outcome')
            ->pluck('count', 'outcome');

        return [
            'total' => $outcomes->sum(),
            'won' => $outcomes['won'] ?? 0,
            'lost' => $outcomes['lost'] ?? 0,
            'active' => $outcomes['active'] ?? 0,
        ];
    }

    private function getContentLines(?string $content): array
    {
        if (!$content) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $content)),
            fn($line) => !empty($line)
        ));
    }

    private function toDomainEntity(stdClass $row): CompetitorEntity
    {
        return CompetitorEntity::reconstitute(
            id: (int) $row->id,
            name: $row->name,
            website: $row->website,
            description: $row->description,
            logoUrl: $row->logo_url,
            strengths: isset($row->strengths) && $row->strengths
                ? (is_string($row->strengths) ? json_decode($row->strengths, true) : $row->strengths)
                : [],
            weaknesses: isset($row->weaknesses) && $row->weaknesses
                ? (is_string($row->weaknesses) ? json_decode($row->weaknesses, true) : $row->weaknesses)
                : [],
            pricing: isset($row->pricing_info) && $row->pricing_info
                ? (is_string($row->pricing_info) ? json_decode($row->pricing_info, true) : $row->pricing_info)
                : [],
            isActive: (bool) ($row->is_active ?? true),
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : new DateTimeImmutable(),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    private function toRowData(CompetitorEntity $entity): array
    {
        return [
            'name' => $entity->getName(),
            'website' => $entity->getWebsite(),
            'description' => $entity->getDescription(),
            'logo_url' => $entity->getLogoUrl(),
            'strengths' => json_encode($entity->getStrengths()),
            'weaknesses' => json_encode($entity->getWeaknesses()),
            'pricing_info' => json_encode($entity->getPricing()),
            'is_active' => $entity->isActive(),
        ];
    }
}
