<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Competitor;

use App\Domain\Competitor\Entities\Battlecard;
use App\Domain\Competitor\Repositories\BattlecardRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

class DbBattlecardRepository implements BattlecardRepositoryInterface
{
    private const TABLE = 'battlecards';

    public function findById(int $id): ?Battlecard
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntity($row);
    }

    public function findByCompetitorId(int $competitorId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('competitor_id', $competitorId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function findPublished(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('is_published', true)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn ($row) => $this->toDomainEntity($row))->all();
    }

    public function save(Battlecard $battlecard): Battlecard
    {
        $data = $this->toRowData($battlecard);

        if ($battlecard->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $battlecard->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $battlecard->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    /**
     * Convert a database row to a domain entity.
     */
    private function toDomainEntity(stdClass $row): Battlecard
    {
        return Battlecard::reconstitute(
            id: (int) $row->id,
            competitorId: (int) $row->competitor_id,
            title: $row->title,
            sections: $row->sections ? (is_string($row->sections) ? json_decode($row->sections, true) : $row->sections) : [],
            talkingPoints: $row->talking_points ? (is_string($row->talking_points) ? json_decode($row->talking_points, true) : $row->talking_points) : [],
            objectionHandlers: $row->objection_handlers ? (is_string($row->objection_handlers) ? json_decode($row->objection_handlers, true) : $row->objection_handlers) : [],
            isPublished: (bool) $row->is_published,
            createdBy: $row->created_by ? (int) $row->created_by : null,
            createdAt: new DateTimeImmutable($row->created_at),
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(Battlecard $battlecard): array
    {
        return [
            'competitor_id' => $battlecard->getCompetitorId(),
            'title' => $battlecard->getTitle(),
            'sections' => json_encode($battlecard->getSections()),
            'talking_points' => json_encode($battlecard->getTalkingPoints()),
            'objection_handlers' => json_encode($battlecard->getObjectionHandlers()),
            'is_published' => $battlecard->isPublished(),
            'created_by' => $battlecard->getCreatedBy(),
        ];
    }
}
