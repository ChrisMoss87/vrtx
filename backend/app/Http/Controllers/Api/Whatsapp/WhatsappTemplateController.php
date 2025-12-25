<?php

namespace App\Http\Controllers\Api\Whatsapp;

use App\Application\Services\WhatsApp\WhatsAppApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappTemplateController extends Controller
{
    public function __construct(
        protected WhatsAppApplicationService $whatsAppApplicationService
    ) {}
    public function index(Request $request): JsonResponse
    {
        $query = WhatsappTemplate::with(['connection:id,name', 'creator:id,name']);

        if ($request->filled('connection_id')) {
            $query->where('connection_id', $request->connection_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $templates = $query->orderBy('name')->get();

        return response()->json(['data' => $templates]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connection_id' => 'required|exists:whatsapp_connections,id',
            'name' => 'required|string|max:512|regex:/^[a-z0-9_]+$/',
            'language' => 'required|string|max:10',
            'category' => 'required|in:UTILITY,MARKETING,AUTHENTICATION',
            'components' => 'required|array',
            'components.*.type' => 'required|in:HEADER,BODY,FOOTER,BUTTONS',
            'example' => 'nullable|array',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = 'PENDING';

        $template = DB::table('whatsapp_templates')->insertGetId($validated);

        // Submit to Meta if requested
        if ($request->boolean('submit_to_meta')) {
            $this->submitToMeta($template);
        }

        return response()->json(['data' => $template->fresh()], 201);
    }

    public function show(WhatsappTemplate $template): JsonResponse
    {
        $template->load(['connection:id,name', 'creator:id,name']);
        $template->loadCount('messages');

        return response()->json(['data' => $template]);
    }

    public function update(Request $request, WhatsappTemplate $template): JsonResponse
    {
        // Can only update pending or rejected templates
        if (!in_array($template->status, ['PENDING', 'REJECTED'])) {
            return response()->json([
                'message' => 'Cannot update approved templates. Create a new version instead.',
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:512|regex:/^[a-z0-9_]+$/',
            'language' => 'sometimes|string|max:10',
            'category' => 'sometimes|in:UTILITY,MARKETING,AUTHENTICATION',
            'components' => 'sometimes|array',
            'example' => 'nullable|array',
        ]);

        $template->update($validated);

        return response()->json(['data' => $template->fresh()]);
    }

    public function destroy(WhatsappTemplate $template): JsonResponse
    {
        // If submitted to Meta, delete from there too
        if ($template->template_id) {
            try {
                $api = WhatsappApiService::for($template->connection);
                $api->deleteTemplate($template->name);
            } catch (\Exception $e) {
                // Log but continue with local deletion
            }
        }

        $template->delete();

        return response()->json(null, 204);
    }

    public function submit(WhatsappTemplate $template): JsonResponse
    {
        if ($template->status === 'APPROVED') {
            return response()->json([
                'message' => 'Template is already approved.',
            ], 400);
        }

        $result = $this->submitToMeta($template);

        if ($result['success']) {
            return response()->json([
                'data' => $template->fresh(),
                'message' => 'Template submitted for approval.',
            ]);
        }

        return response()->json([
            'message' => 'Failed to submit template: ' . ($result['error'] ?? 'Unknown error'),
        ], 400);
    }

    public function syncStatus(WhatsappTemplate $template): JsonResponse
    {
        try {
            $api = WhatsappApiService::for($template->connection);
            $status = $api->getTemplateStatus($template->name);

            if ($status) {
                $template->update([
                    'template_id' => $status['id'] ?? $template->template_id,
                    'status' => $status['status'] ?? $template->status,
                    'rejection_reason' => $status['rejected_reason'] ?? null,
                    'approved_at' => $status['status'] === 'APPROVED' ? now() : null,
                ]);

                return response()->json([
                    'data' => $template->fresh(),
                    'message' => 'Template status synced.',
                ]);
            }

            return response()->json([
                'message' => 'Template not found in Meta. It may not have been submitted yet.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync status: ' . $e->getMessage(),
            ], 400);
        }
    }

    public function preview(Request $request, WhatsappTemplate $template): JsonResponse
    {
        $params = $request->validate([
            'body_params' => 'nullable|array',
            'header_params' => 'nullable|array',
        ]);

        $bodyText = $template->renderBody($params['body_params'] ?? []);

        $header = $template->header_component;
        $headerText = null;
        if ($header && isset($header['text'])) {
            $headerText = $header['text'];
            foreach (($params['header_params'] ?? []) as $index => $value) {
                $headerText = str_replace('{{' . ($index + 1) . '}}', $value, $headerText);
            }
        }

        $footer = $template->footer_component;

        return response()->json([
            'data' => [
                'header' => $headerText,
                'body' => $bodyText,
                'footer' => $footer['text'] ?? null,
                'buttons' => $template->buttons_component['buttons'] ?? [],
            ],
        ]);
    }

    private function submitToMeta(WhatsappTemplate $template): array
    {
        try {
            $api = WhatsappApiService::for($template->connection);

            $templateData = [
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
                'components' => $template->components,
            ];

            if ($template->example) {
                $templateData['example'] = $template->example;
            }

            $result = $api->createTemplate($templateData);

            if ($result['success']) {
                $template->update([
                    'template_id' => $result['data']['id'] ?? null,
                    'status' => 'PENDING',
                    'submitted_at' => now(),
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
