<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Competitor;

use App\Domain\Competitor\Entities\Battlecard;
use App\Domain\Competitor\Repositories\BattlecardRepositoryInterface;
use App\Models\Battlecard as BattlecardModel;
use DateTimeImmutable;

class EloquentBattlecardRepository implements BattlecardRepositoryInterface
{
    public function findById(int $id): ?Battlecard
    {
        $model = BattlecardModel::find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByCompetitorId(int $competitorId): array
    {
        $models = BattlecardModel::where('competitor_id', $competitorId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findPublished(): array
    {
        $models = BattlecardModel::where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(Battlecard $battlecard): Battlecard
    {
        $data = $this->toModelData($battlecard);

        if ($battlecard->getId() !== null) {
            $model = BattlecardModel::findOrFail($battlecard->getId());
            $model->update($data);
        } else {
            $model = BattlecardModel::create($data);
        }

        return $this->toDomainEntity($model->fresh());
    }

    public function delete(int $id): bool
    {
        $model = BattlecardModel::find($id);

        if (!$model) {
            return false;
        }

        return $model->delete() ?? false;
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(BattlecardModel $model): Battlecard
    {
        return Battlecard::reconstitute(
            id: $model->id,
            competitorId: $model->competitor_id,
            title: $model->title,
            sections: $model->sections ?? [],
            talkingPoints: $model->talking_points ?? [],
            objectionHandlers: $model->objection_handlers ?? [],
            isPublished: $model->is_published,
            createdBy: $model->created_by,
            createdAt: new DateTimeImmutable($model->created_at->toDateTimeString()),
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Battlecard $battlecard): array
    {
        return [
            'competitor_id' => $battlecard->getCompetitorId(),
            'title' => $battlecard->getTitle(),
            'sections' => $battlecard->getSections(),
            'talking_points' => $battlecard->getTalkingPoints(),
            'objection_handlers' => $battlecard->getObjectionHandlers(),
            'is_published' => $battlecard->isPublished(),
        ];
    }
}
