<?php

declare(strict_types=1);

namespace Plugins\SMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\SMS\Application\Services\SMSApplicationService;

class SMSTemplateController extends Controller
{
    public function __construct(
        private readonly SMSApplicationService $smsService,
    ) {}

    /**
     * List SMS templates.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'active', 'search']);

        $templates = $this->smsService->listTemplates($filters);

        return response()->json(['data' => $templates]);
    }

    /**
     * Create a new template.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:1600',
            'category' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $template = $this->smsService->createTemplate($validated);

        return response()->json(['data' => $template], 201);
    }

    /**
     * Get a specific template.
     */
    public function show(int $template): JsonResponse
    {
        $data = $this->smsService->getTemplate($template);

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
            'content' => 'sometimes|string|max:1600',
            'category' => 'sometimes|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $this->smsService->updateTemplate($template, $validated);

        return response()->json(['data' => $data]);
    }

    /**
     * Delete a template.
     */
    public function destroy(int $template): JsonResponse
    {
        $this->smsService->deleteTemplate($template);

        return response()->json(null, 204);
    }

    /**
     * Preview template with sample data.
     */
    public function preview(Request $request, int $template): JsonResponse
    {
        $validated = $request->validate([
            'merge_data' => 'required|array',
        ]);

        $templateData = $this->smsService->getTemplate($template);

        if (!$templateData) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Render template
        $content = $templateData['content'];
        foreach ($validated['merge_data'] as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }

        // Calculate segments
        $length = strlen($content);
        $isUnicode = preg_match('/[^\x00-\x7F]/', $content);
        $segments = $isUnicode
            ? ($length <= 70 ? 1 : (int) ceil($length / 67))
            : ($length <= 160 ? 1 : (int) ceil($length / 153));

        return response()->json([
            'data' => [
                'rendered' => $content,
                'character_count' => $length,
                'segment_count' => $segments,
            ],
        ]);
    }
}
