<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Document;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Services\Document\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    public function __construct(
        protected DocumentTemplateService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = DocumentTemplate::query()
            ->accessibleBy(auth()->id())
            ->with('createdBy');

        if ($request->has('category')) {
            $query->category($request->category);
        }

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        $templates = $query->orderBy('name')->paginate($request->integer('per_page', 25));

        return response()->json($templates);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|in:' . implode(',', DocumentTemplate::CATEGORIES),
            'description' => 'nullable|string',
            'content' => 'required|string',
            'output_format' => 'nullable|string|in:' . implode(',', DocumentTemplate::OUTPUT_FORMATS),
            'page_settings' => 'nullable|array',
            'header_settings' => 'nullable|array',
            'footer_settings' => 'nullable|array',
            'conditional_blocks' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
        ]);

        $template = $this->service->create($validated);

        return response()->json($template, 201);
    }

    public function show(DocumentTemplate $documentTemplate): JsonResponse
    {
        $documentTemplate->load(['createdBy', 'updatedBy']);

        return response()->json($documentTemplate);
    }

    public function update(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|in:' . implode(',', DocumentTemplate::CATEGORIES),
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'output_format' => 'nullable|string|in:' . implode(',', DocumentTemplate::OUTPUT_FORMATS),
            'page_settings' => 'nullable|array',
            'header_settings' => 'nullable|array',
            'footer_settings' => 'nullable|array',
            'conditional_blocks' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
        ]);

        $template = $this->service->update($documentTemplate, $validated);

        return response()->json($template);
    }

    public function destroy(DocumentTemplate $documentTemplate): JsonResponse
    {
        $this->service->delete($documentTemplate);

        return response()->json(['message' => 'Template deleted']);
    }

    public function duplicate(DocumentTemplate $documentTemplate): JsonResponse
    {
        $copy = $this->service->duplicate($documentTemplate);

        return response()->json($copy, 201);
    }

    public function generate(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $validated = $request->validate([
            'record_type' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        $document = $this->service->generate(
            $documentTemplate,
            $validated['record_type'],
            $validated['record_id']
        );

        return response()->json($document, 201);
    }

    public function preview(Request $request, DocumentTemplate $documentTemplate): JsonResponse
    {
        $validated = $request->validate([
            'record_type' => 'nullable|string',
            'record_id' => 'nullable|integer',
        ]);

        if (isset($validated['record_type']) && isset($validated['record_id'])) {
            $html = $this->service->preview(
                $documentTemplate,
                $validated['record_type'],
                $validated['record_id']
            );
        } else {
            $html = $this->service->previewWithSampleData($documentTemplate);
        }

        return response()->json(['html' => $html]);
    }

    public function variables(): JsonResponse
    {
        $variables = $this->service->getAvailableVariables();

        return response()->json($variables);
    }

    public function generatedDocuments(Request $request): JsonResponse
    {
        $query = GeneratedDocument::with(['template', 'createdBy']);

        if ($request->has('record_type') && $request->has('record_id')) {
            $query->forRecord($request->record_type, $request->integer('record_id'));
        }

        if ($request->has('template_id')) {
            $query->where('template_id', $request->integer('template_id'));
        }

        $documents = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($documents);
    }

    public function showGeneratedDocument(GeneratedDocument $generatedDocument): JsonResponse
    {
        $generatedDocument->load(['template', 'createdBy', 'sendLogs']);

        return response()->json($generatedDocument);
    }

    public function deleteGeneratedDocument(GeneratedDocument $generatedDocument): JsonResponse
    {
        $generatedDocument->delete();

        return response()->json(['message' => 'Document deleted']);
    }
}
