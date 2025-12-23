<?php

declare(strict_types=1);

namespace Plugins\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\WhatsApp\Application\Services\WhatsAppApplicationService;

class WhatsAppTemplateController extends Controller
{
    public function __construct(
        private readonly WhatsAppApplicationService $whatsAppService,
    ) {}

    /**
     * List message templates.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'category', 'search']);

        $templates = $this->whatsAppService->listTemplates($filters);

        return response()->json(['data' => $templates]);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:whatsapp_templates,slug',
            'content' => 'required|string|max:4096',
            'category' => 'required|in:marketing,utility,authentication',
            'language' => 'required|string|size:2',
            'components' => 'nullable|array',
        ]);

        $template = $this->whatsAppService->createTemplate($validated);

        return response()->json(['data' => $template], 201);
    }

    /**
     * Get a specific template.
     */
    public function show(int $template): JsonResponse
    {
        $data = $this->whatsAppService->getTemplate($template);

        if (!$data) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Update a template.
     */
    public function update(Request $request, int $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:4096',
            'status' => 'sometimes|in:pending,approved,rejected',
        ]);

        $data = $this->whatsAppService->updateTemplate($template, $validated);

        return response()->json(['data' => $data]);
    }

    /**
     * Delete a template.
     */
    public function destroy(int $template): JsonResponse
    {
        $this->whatsAppService->deleteTemplate($template);

        return response()->json(null, 204);
    }

    /**
     * Sync template status with WhatsApp.
     */
    public function sync(int $template): JsonResponse
    {
        // Implementation would call WhatsApp API to get current template status
        $data = $this->whatsAppService->getTemplate($template);

        return response()->json(['data' => $data]);
    }
}
