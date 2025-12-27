<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Document;

use App\Application\Services\Document\DocumentApplicationService;
use App\Http\Controllers\Controller;
use App\Services\Document\DocumentTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentTemplateController extends Controller
{
    // Document template categories
    private const CATEGORIES = ['quote', 'invoice', 'contract', 'proposal', 'letter', 'report', 'other'];

    // Supported output formats
    private const OUTPUT_FORMATS = ['pdf', 'docx', 'html'];

    public function __construct(
        protected DocumentTemplateService $service,
        protected DocumentApplicationService $appService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();

        $query = DB::table('document_templates')
            ->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhere('is_shared', true);
            });

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        if ($request->has('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        // Get paginated results
        $perPage = $request->integer('per_page', 25);
        $page = $request->integer('page', 1);
        $total = $query->count();

        $templates = $query->orderBy('name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($template) {
                if ($template->created_by) {
                    $template->createdBy = DB::table('users')
                        ->where('id', $template->created_by)
                        ->select(['id', 'name'])
                        ->first();
                }
                return $template;
            });

        return response()->json([
            'data' => $templates,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|in:' . implode(',', self::CATEGORIES),
            'description' => 'nullable|string',
            'content' => 'required|string',
            'output_format' => 'nullable|string|in:' . implode(',', self::OUTPUT_FORMATS),
            'page_settings' => 'nullable|array',
            'header_settings' => 'nullable|array',
            'footer_settings' => 'nullable|array',
            'conditional_blocks' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
        ]);

        $template = $this->service->create($validated);

        return response()->json($template, 201);
    }

    public function show(int $id): JsonResponse
    {
        $template = $this->service->findById($id);

        if (!$template) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json($template);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category' => 'nullable|string|in:' . implode(',', self::CATEGORIES),
            'description' => 'nullable|string',
            'content' => 'sometimes|string',
            'output_format' => 'nullable|string|in:' . implode(',', self::OUTPUT_FORMATS),
            'page_settings' => 'nullable|array',
            'header_settings' => 'nullable|array',
            'footer_settings' => 'nullable|array',
            'conditional_blocks' => 'nullable|array',
            'is_active' => 'nullable|boolean',
            'is_shared' => 'nullable|boolean',
        ]);

        $template = $this->service->updateById($id, $validated);

        return response()->json($template);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->service->deleteById($id);

        return response()->json(['message' => 'Template deleted']);
    }

    public function duplicate(int $id): JsonResponse
    {
        $copy = $this->service->duplicateById($id);

        return response()->json($copy, 201);
    }

    public function generate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'record_type' => 'required|string',
            'record_id' => 'required|integer',
        ]);

        $document = $this->service->generateById(
            $id,
            $validated['record_type'],
            $validated['record_id']
        );

        return response()->json($document, 201);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'record_type' => 'nullable|string',
            'record_id' => 'nullable|integer',
        ]);

        if (isset($validated['record_type']) && isset($validated['record_id'])) {
            $html = $this->service->previewById(
                $id,
                $validated['record_type'],
                $validated['record_id']
            );
        } else {
            $html = $this->service->previewWithSampleDataById($id);
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
        $query = DB::table('generated_documents');

        if ($request->has('record_type') && $request->has('record_id')) {
            $query->where('record_type', $request->record_type)
                ->where('record_id', $request->integer('record_id'));
        }

        if ($request->has('template_id')) {
            $query->where('template_id', $request->integer('template_id'));
        }

        $perPage = $request->integer('per_page', 25);
        $page = $request->integer('page', 1);
        $total = $query->count();

        $documents = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'data' => $documents,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function showGeneratedDocument(int $id): JsonResponse
    {
        $document = DB::table('generated_documents')->where('id', $id)->first();

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        return response()->json($document);
    }

    public function deleteGeneratedDocument(int $id): JsonResponse
    {
        DB::table('generated_documents')->where('id', $id)->delete();

        return response()->json(['message' => 'Document deleted']);
    }
}
