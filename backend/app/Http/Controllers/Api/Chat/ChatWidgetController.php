<?php

namespace App\Http\Controllers\Api\Chat;

use App\Application\Services\Chat\ChatApplicationService;
use App\Http\Controllers\Controller;
use App\Models\ChatWidget;
use App\Services\Chat\ChatService;
use App\Services\Chat\ChatAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatWidgetController extends Controller
{
    public function __construct(
        protected ChatApplicationService $chatApplicationService,
        protected ChatService $chatService,
        protected ChatAnalyticsService $analyticsService
    ) {}

    public function index(): JsonResponse
    {
        $widgets = ChatWidget::withCount(['conversations', 'visitors'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $widgets->map(fn($w) => $this->formatWidget($w)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
            'styling' => 'nullable|array',
            'routing_rules' => 'nullable|array',
            'business_hours' => 'nullable|array',
            'allowed_domains' => 'nullable|array',
        ]);

        $widget = $this->chatService->createWidget($validated);

        return response()->json([
            'data' => $this->formatWidget($widget),
            'message' => 'Chat widget created',
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $widget = ChatWidget::withCount(['conversations', 'visitors'])->findOrFail($id);

        return response()->json([
            'data' => $this->formatWidget($widget, true),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $widget = ChatWidget::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'settings' => 'nullable|array',
            'styling' => 'nullable|array',
            'routing_rules' => 'nullable|array',
            'business_hours' => 'nullable|array',
            'allowed_domains' => 'nullable|array',
        ]);

        $widget = $this->chatService->updateWidget($widget, $validated);

        return response()->json([
            'data' => $this->formatWidget($widget),
            'message' => 'Widget updated',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $widget = ChatWidget::findOrFail($id);
        $widget->delete();

        return response()->json(['message' => 'Widget deleted']);
    }

    public function embedCode(int $id): JsonResponse
    {
        $widget = ChatWidget::findOrFail($id);

        return response()->json([
            'data' => [
                'embed_code' => $widget->getEmbedCode(),
                'widget_key' => $widget->widget_key,
            ],
        ]);
    }

    public function analytics(Request $request, int $id): JsonResponse
    {
        $widget = ChatWidget::findOrFail($id);
        $period = $request->query('period', 'month');

        return response()->json([
            'data' => [
                'overview' => $this->analyticsService->getOverview($id, $period),
                'by_hour' => $this->analyticsService->getConversationsByHour($id),
                'ratings' => $this->analyticsService->getRatingDistribution($id, $period),
                'visitors' => $this->analyticsService->getVisitorInsights($id, $period),
            ],
        ]);
    }

    private function formatWidget(ChatWidget $widget, bool $includeDetails = false): array
    {
        $data = [
            'id' => $widget->id,
            'name' => $widget->name,
            'widget_key' => $widget->widget_key,
            'is_active' => $widget->is_active,
            'is_online' => $widget->isOnline(),
            'conversations_count' => $widget->conversations_count ?? 0,
            'visitors_count' => $widget->visitors_count ?? 0,
            'created_at' => $widget->created_at->toISOString(),
        ];

        if ($includeDetails) {
            $data['settings'] = $widget->settings ?? $widget->getDefaultSettings();
            $data['styling'] = $widget->styling ?? $widget->getDefaultStyling();
            $data['routing_rules'] = $widget->routing_rules;
            $data['business_hours'] = $widget->business_hours;
            $data['allowed_domains'] = $widget->allowed_domains;
            $data['embed_code'] = $widget->getEmbedCode();
        }

        return $data;
    }
}
