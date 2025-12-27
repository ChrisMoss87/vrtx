<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Chat;

use App\Domain\Chat\Repositories\ChatWidgetRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DbChatWidgetRepository implements ChatWidgetRepositoryInterface
{
    private const TABLE = 'chat_widgets';
    private const TABLE_AGENT_STATUS = 'chat_agent_statuses';
    private const TABLE_USERS = 'users';

    public function findById(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $row ? (array) $row : null;
    }

    public function findByKey(string $key): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('widget_key', $key)
            ->where('is_active', true)
            ->first();

        return $row ? (array) $row : null;
    }

    public function findAll(bool $activeOnly = false): Collection
    {
        $query = DB::table(self::TABLE);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')->get()->map(fn($row) => (array) $row);
    }

    public function create(array $data): int
    {
        return DB::table(self::TABLE)->insertGetId([
            'name' => $data['name'],
            'widget_key' => $data['widget_key'] ?? bin2hex(random_bytes(16)),
            'is_active' => $data['is_active'] ?? true,
            'settings' => json_encode($data['settings'] ?? $this->getDefaultSettings()),
            'styling' => json_encode($data['styling'] ?? $this->getDefaultStyling()),
            'routing_rules' => $data['routing_rules'] ? json_encode($data['routing_rules']) : null,
            'business_hours' => $data['business_hours'] ? json_encode($data['business_hours']) : null,
            'allowed_domains' => $data['allowed_domains'] ? json_encode($data['allowed_domains']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function update(int $id, array $data): array
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }
        if (isset($data['styling'])) {
            $updateData['styling'] = json_encode($data['styling']);
        }
        if (array_key_exists('routing_rules', $data)) {
            $updateData['routing_rules'] = $data['routing_rules'] ? json_encode($data['routing_rules']) : null;
        }
        if (array_key_exists('business_hours', $data)) {
            $updateData['business_hours'] = $data['business_hours'] ? json_encode($data['business_hours']) : null;
        }
        if (array_key_exists('allowed_domains', $data)) {
            $updateData['allowed_domains'] = $data['allowed_domains'] ? json_encode($data['allowed_domains']) : null;
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return (array) $row;
    }

    public function delete(int $id): bool
    {
        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function getStatus(int $id): array
    {
        $widget = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$widget) {
            return ['widget_active' => false, 'is_online' => false];
        }

        $onlineAgents = DB::table(self::TABLE_AGENT_STATUS . ' as s')
            ->join(self::TABLE_USERS . ' as u', 'u.id', '=', 's.user_id')
            ->where('s.status', 'online')
            ->select('s.user_id', 'u.name', 's.status', 's.active_conversations')
            ->get();

        $availableAgents = DB::table(self::TABLE_AGENT_STATUS)
            ->where('status', 'online')
            ->whereRaw('active_conversations < max_conversations')
            ->count();

        $businessHours = $widget->business_hours ? json_decode($widget->business_hours, true) : null;
        $isOnline = $this->isWithinBusinessHours($businessHours) && $availableAgents > 0;

        return [
            'widget_active' => (bool) $widget->is_active,
            'is_online' => $isOnline,
            'online_agents' => $onlineAgents->count(),
            'available_agents' => $availableAgents,
            'agents' => $onlineAgents->map(fn($a) => [
                'user_id' => $a->user_id,
                'name' => $a->name,
                'status' => $a->status,
                'active_conversations' => $a->active_conversations,
            ])->toArray(),
        ];
    }

    public function getEmbedCode(int $id): string
    {
        $widget = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$widget) {
            return '';
        }

        $key = $widget->widget_key;
        $baseUrl = config('app.url');

        return <<<HTML
<script>
(function() {
    var w = document.createElement('script');
    w.type = 'text/javascript';
    w.async = true;
    w.src = '{$baseUrl}/chat/widget.js?key={$key}';
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(w, s);
})();
</script>
HTML;
    }

    private function getDefaultSettings(): array
    {
        return [
            'greeting_message' => 'Hi! How can we help you today?',
            'offline_message' => 'We are currently offline. Leave a message and we\'ll get back to you.',
            'require_email' => false,
            'require_name' => false,
            'show_agent_name' => true,
            'show_agent_avatar' => true,
            'sound_enabled' => true,
        ];
    }

    private function getDefaultStyling(): array
    {
        return [
            'primary_color' => '#4F46E5',
            'text_color' => '#FFFFFF',
            'position' => 'right',
            'button_icon' => 'chat',
        ];
    }

    private function isWithinBusinessHours(?array $businessHours): bool
    {
        if (!$businessHours) {
            return true;
        }

        $now = now();
        $dayOfWeek = strtolower($now->format('l'));

        if (!isset($businessHours[$dayOfWeek])) {
            return false;
        }

        $hours = $businessHours[$dayOfWeek];

        if (!$hours['enabled']) {
            return false;
        }

        $start = \Carbon\Carbon::parse($hours['start']);
        $end = \Carbon\Carbon::parse($hours['end']);

        return $now->between($start, $end);
    }
}
