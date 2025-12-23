<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Infrastructure\Repositories;

use App\Models\WhatsappConnection;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Support\Facades\DB;
use Plugins\WhatsApp\Domain\Repositories\WhatsAppRepositoryInterface;

class EloquentWhatsAppRepository implements WhatsAppRepositoryInterface
{
    // =========================================================================
    // CONNECTIONS
    // =========================================================================

    public function listConnections(array $filters = []): array
    {
        $query = WhatsappConnection::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('name')->get()->toArray();
    }

    public function findConnectionById(int $id): ?array
    {
        $connection = WhatsappConnection::find($id);

        return $connection?->toArray();
    }

    public function getActiveConnection(): ?array
    {
        $connection = WhatsappConnection::where('status', 'active')->first();

        return $connection?->toArray();
    }

    public function createConnection(array $data): array
    {
        $connection = WhatsappConnection::create($data);

        return $connection->toArray();
    }

    public function updateConnection(int $id, array $data): array
    {
        $connection = WhatsappConnection::findOrFail($id);
        $connection->update($data);

        return $connection->fresh()->toArray();
    }

    public function deleteConnection(int $id): bool
    {
        return WhatsappConnection::destroy($id) > 0;
    }

    // =========================================================================
    // CONVERSATIONS
    // =========================================================================

    public function listConversations(array $filters = [], int $perPage = 20): array
    {
        $query = WhatsappConversation::with([
            'connection:id,name',
            'assignedUser:id,name',
        ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            if ($filters['assigned_to'] === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $filters['assigned_to']);
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['module_api_name']) && !empty($filters['record_id'])) {
            $query->where('linked_module_api_name', $filters['module_api_name'])
                ->where('linked_record_id', $filters['record_id']);
        }

        $paginated = $query->orderByDesc('last_message_at')->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    public function findConversationById(int $id): ?array
    {
        $conversation = WhatsappConversation::with([
            'connection:id,name',
            'assignedUser:id,name',
        ])->find($id);

        return $conversation?->toArray();
    }

    public function findConversationByPhone(string $phoneNumber): ?array
    {
        $conversation = WhatsappConversation::where('contact_phone', $phoneNumber)
            ->orWhere('contact_wa_id', $phoneNumber)
            ->first();

        return $conversation?->toArray();
    }

    public function getOrCreateConversation(string $phoneNumber, ?string $contactName = null): array
    {
        $conversation = WhatsappConversation::firstOrCreate(
            ['contact_phone' => $phoneNumber],
            [
                'contact_wa_id' => $phoneNumber,
                'contact_name' => $contactName,
                'status' => 'open',
                'connection_id' => $this->getActiveConnection()['id'] ?? null,
            ]
        );

        return $conversation->toArray();
    }

    public function updateConversation(int $id, array $data): array
    {
        $conversation = WhatsappConversation::findOrFail($id);
        $conversation->update($data);

        return $conversation->fresh()->toArray();
    }

    public function getConversationsForRecord(string $moduleApiName, int $recordId): array
    {
        return WhatsappConversation::where('linked_module_api_name', $moduleApiName)
            ->where('linked_record_id', $recordId)
            ->with(['connection:id,name', 'assignedUser:id,name'])
            ->orderByDesc('last_message_at')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // MESSAGES
    // =========================================================================

    public function listMessages(int $conversationId, int $perPage = 50): array
    {
        $paginated = WhatsappMessage::where('conversation_id', $conversationId)
            ->with(['sender:id,name'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    public function findMessageById(int $id): ?array
    {
        $message = WhatsappMessage::with(['conversation', 'sender:id,name'])->find($id);

        return $message?->toArray();
    }

    public function findMessageByWhatsAppId(string $whatsappMessageId): ?array
    {
        $message = WhatsappMessage::where('whatsapp_message_id', $whatsappMessageId)
            ->orWhere('wa_message_id', $whatsappMessageId)
            ->first();

        return $message?->toArray();
    }

    public function createMessage(array $data): array
    {
        $message = WhatsappMessage::create($data);

        return $message->toArray();
    }

    public function updateMessageStatus(int $id, string $status): array
    {
        $message = WhatsappMessage::findOrFail($id);

        $updateData = ['status' => $status];

        if ($status === 'delivered') {
            $updateData['delivered_at'] = now();
        } elseif ($status === 'read') {
            $updateData['read_at'] = now();
        } elseif ($status === 'failed') {
            $updateData['failed_at'] = now();
        }

        $message->update($updateData);

        return $message->fresh()->toArray();
    }

    // =========================================================================
    // TEMPLATES
    // =========================================================================

    public function listTemplates(array $filters = []): array
    {
        $query = WhatsappTemplate::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name')->get()->toArray();
    }

    public function findTemplateById(int $id): ?array
    {
        $template = WhatsappTemplate::find($id);

        return $template?->toArray();
    }

    public function findTemplateBySlug(string $slug): ?array
    {
        $template = WhatsappTemplate::where('slug', $slug)
            ->orWhere('name', $slug)
            ->first();

        return $template?->toArray();
    }

    public function createTemplate(array $data): array
    {
        $template = WhatsappTemplate::create($data);

        return $template->toArray();
    }

    public function updateTemplate(int $id, array $data): array
    {
        $template = WhatsappTemplate::findOrFail($id);
        $template->update($data);

        return $template->fresh()->toArray();
    }

    public function deleteTemplate(int $id): bool
    {
        return WhatsappTemplate::destroy($id) > 0;
    }

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function getMessageStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = WhatsappMessage::query();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $total = $query->count();
        $inbound = (clone $query)->where('direction', 'inbound')->count();
        $outbound = (clone $query)->where('direction', 'outbound')->count();
        $delivered = (clone $query)->where('status', 'delivered')->count();
        $read = (clone $query)->where('status', 'read')->count();
        $failed = (clone $query)->where('status', 'failed')->count();

        return [
            'total' => $total,
            'inbound' => $inbound,
            'outbound' => $outbound,
            'delivered' => $delivered,
            'read' => $read,
            'failed' => $failed,
            'delivery_rate' => $outbound > 0 ? round(($delivered / $outbound) * 100, 2) : 0,
            'read_rate' => $delivered > 0 ? round(($read / $delivered) * 100, 2) : 0,
        ];
    }

    public function getConversationStats(): array
    {
        $total = WhatsappConversation::count();
        $open = WhatsappConversation::where('status', 'open')->count();
        $closed = WhatsappConversation::where('status', 'closed')->count();
        $unassigned = WhatsappConversation::whereNull('assigned_to')->where('status', 'open')->count();

        $avgResponseTime = DB::table('whatsapp_messages')
            ->where('direction', 'outbound')
            ->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total' => $total,
            'open' => $open,
            'closed' => $closed,
            'unassigned' => $unassigned,
            'avg_response_time_seconds' => $avgResponseTime ? round($avgResponseTime) : null,
        ];
    }
}
