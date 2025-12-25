<?php

namespace App\Http\Controllers\Api\Sms;

use App\Domain\Sms\Repositories\SmsMessageRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SmsTemplateController extends Controller
{
    public function __construct(
        protected SmsMessageRepositoryInterface $messageRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->filled('category')) {
            $filters['category'] = $request->category;
        }

        if ($request->boolean('active_only', true)) {
            $filters['is_active'] = true;
        }

        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }

        $templates = $this->messageRepository->listTemplates($filters);

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:1600',
            'category' => 'nullable|string|in:marketing,transactional,support',
            'is_active' => 'boolean',
        ]);

        $validated['created_by'] = auth()->id();

        // Calculate character count and segment count
        $validated['character_count'] = strlen($validated['content']);
        $validated['segment_count'] = SmsTemplate::calculateSegments($validated['content']);
        $validated['merge_fields'] = SmsTemplate::extractMergeFields($validated['content']);

        $template = $this->messageRepository->createTemplate($validated);

        return response()->json(['data' => $template], 201);
    }

    public function show(int $id): JsonResponse
    {
        $template = $this->messageRepository->findTemplateById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        // Get message count for this template
        $messageCount = DB::table('sms_messages')
            ->where('template_id', $id)
            ->count();

        $template['messages_count'] = $messageCount;

        return response()->json(['data' => $template]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:1600',
            'category' => 'nullable|string|in:marketing,transactional,support',
            'is_active' => 'sometimes|boolean',
        ]);

        // Calculate character count and segment count if content is being updated
        if (isset($validated['content'])) {
            $validated['character_count'] = strlen($validated['content']);
            $validated['segment_count'] = SmsTemplate::calculateSegments($validated['content']);
            $validated['merge_fields'] = SmsTemplate::extractMergeFields($validated['content']);
        }

        $template = $this->messageRepository->updateTemplate($id, $validated);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json(['data' => $template]);
    }

    public function destroy(int $id): JsonResponse
    {
        $result = $this->messageRepository->deleteTemplate($id);

        if (!$result) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json(null, 204);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $template = $this->messageRepository->findTemplateById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $sampleData = $request->input('sample_data', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'company' => 'Acme Inc',
            'email' => 'john@example.com',
        ]);

        // Render template with data
        $content = $template['content'];
        foreach ($sampleData as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value ?? '', $content);
        }
        // Remove any unmatched merge fields
        $content = preg_replace('/\{\{\w+\}\}/', '', $content);
        $rendered = trim($content);

        return response()->json([
            'data' => [
                'original' => $template['content'],
                'rendered' => $rendered,
                'character_count' => strlen($rendered),
                'segment_count' => SmsTemplate::calculateSegments($rendered),
                'merge_fields' => $template['merge_fields'] ?? [],
            ],
        ]);
    }

    public function duplicate(int $id): JsonResponse
    {
        $template = $this->messageRepository->findTemplateById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $newTemplate = [
            'name' => $template['name'] . ' (Copy)',
            'content' => $template['content'],
            'category' => $template['category'] ?? null,
            'is_active' => $template['is_active'] ?? true,
            'created_by' => auth()->id(),
            'character_count' => $template['character_count'],
            'segment_count' => $template['segment_count'],
            'merge_fields' => $template['merge_fields'] ?? [],
            'usage_count' => 0,
            'last_used_at' => null,
        ];

        $created = $this->messageRepository->createTemplate($newTemplate);

        return response()->json(['data' => $created], 201);
    }
}
