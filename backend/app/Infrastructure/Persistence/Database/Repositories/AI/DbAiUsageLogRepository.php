<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\AI;

use App\Domain\AI\Repositories\AiUsageLogRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DbAiUsageLogRepository implements AiUsageLogRepositoryInterface
{
    private const TABLE = 'ai_usage_logs';
    private const TABLE_USERS = 'users';

    public function create(array $data): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'feature' => $data['feature'],
            'model' => $data['model'],
            'input_tokens' => $data['input_tokens'] ?? 0,
            'output_tokens' => $data['output_tokens'] ?? 0,
            'cost_cents' => $data['cost_cents'] ?? 0,
            'user_id' => $data['user_id'] ?? null,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'created_at' => now(),
        ]);
    }

    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table(self::TABLE);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $totals = $query
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as total_input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as total_output_tokens')
            ->selectRaw('COALESCE(SUM(cost_cents), 0) as total_cost_cents')
            ->first();

        $byFeature = DB::table(self::TABLE)
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->selectRaw('feature, COUNT(*) as count, SUM(cost_cents) as cost')
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get();

        $byModel = DB::table(self::TABLE)
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->selectRaw('model, COUNT(*) as count, SUM(cost_cents) as cost')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get();

        return [
            'total_requests' => (int) $totals->total_requests,
            'total_input_tokens' => (int) $totals->total_input_tokens,
            'total_output_tokens' => (int) $totals->total_output_tokens,
            'total_cost_cents' => (int) $totals->total_cost_cents,
            'by_feature' => $byFeature->map(fn($r) => [
                'feature' => $r->feature,
                'count' => (int) $r->count,
                'cost_cents' => (int) $r->cost,
            ])->all(),
            'by_model' => $byModel->map(fn($r) => [
                'model' => $r->model,
                'count' => (int) $r->count,
                'cost_cents' => (int) $r->cost,
            ])->all(),
        ];
    }

    public function getByUser(?string $startDate = null, ?string $endDate = null): array
    {
        $query = DB::table(self::TABLE . ' as l')
            ->leftJoin(self::TABLE_USERS . ' as u', 'u.id', '=', 'l.user_id')
            ->selectRaw('l.user_id, u.name, u.email, COUNT(*) as request_count, SUM(l.cost_cents) as total_cost, SUM(l.input_tokens + l.output_tokens) as total_tokens')
            ->groupBy('l.user_id', 'u.name', 'u.email');

        if ($startDate) {
            $query->where('l.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('l.created_at', '<=', $endDate);
        }

        return $query
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn($row) => [
                'user_id' => $row->user_id,
                'name' => $row->name,
                'email' => $row->email,
                'request_count' => (int) $row->request_count,
                'total_cost_cents' => (int) $row->total_cost,
                'total_tokens' => (int) $row->total_tokens,
            ])
            ->all();
    }

    public function getTrend(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        return DB::table(self::TABLE)
            ->where('created_at', '>=', $startDate)
            ->selectRaw("DATE(created_at) as date, COUNT(*) as requests, SUM(cost_cents) as cost")
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn($row) => [
                'date' => $row->date,
                'requests' => (int) $row->requests,
                'cost_cents' => (int) $row->cost,
            ])
            ->all();
    }
}
