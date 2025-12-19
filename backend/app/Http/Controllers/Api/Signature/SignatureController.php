<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Signature;

use App\Application\Services\Document\DocumentApplicationService;
use App\Http\Controllers\Controller;
use App\Models\GeneratedDocument;
use App\Models\SignatureField;
use App\Models\SignatureRequest;
use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    public function __construct(
        protected SignatureService $service,
        protected DocumentApplicationService $appService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SignatureRequest::with(['signers', 'createdBy']);

        if ($request->has('status')) {
            $query->status($request->status);
        }

        if ($request->has('source_type') && $request->has('source_id')) {
            $query->where('source_type', $request->source_type)
                  ->where('source_id', $request->integer('source_id'));
        }

        if ($request->has('search')) {
            $query->where('title', 'ilike', '%' . $request->search . '%');
        }

        $requests = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_id' => 'nullable|exists:generated_documents,id',
            'file_path' => 'nullable|string',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|integer',
            'expires_at' => 'nullable|date',
            'settings' => 'nullable|array',
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string',
            'signers.*.role' => 'nullable|string|in:' . implode(',', SignatureSigner::ROLES),
            'signers.*.sign_order' => 'nullable|integer|min:1',
            'fields' => 'nullable|array',
            'fields.*.field_type' => 'required|string|in:' . implode(',', SignatureField::TYPES),
            'fields.*.signer_order' => 'required|integer|min:1',
            'fields.*.page_number' => 'nullable|integer|min:1',
            'fields.*.x_position' => 'required|numeric',
            'fields.*.y_position' => 'required|numeric',
            'fields.*.width' => 'nullable|numeric',
            'fields.*.height' => 'nullable|numeric',
            'fields.*.required' => 'nullable|boolean',
            'fields.*.label' => 'nullable|string',
        ]);

        $signatureRequest = $this->service->create($validated);

        return response()->json($signatureRequest, 201);
    }

    public function storeFromDocument(Request $request, GeneratedDocument $generatedDocument): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string',
            'signers.*.role' => 'nullable|string|in:' . implode(',', SignatureSigner::ROLES),
            'signers.*.sign_order' => 'nullable|integer|min:1',
            'fields' => 'nullable|array',
        ]);

        $signatureRequest = $this->service->createFromDocument($generatedDocument, $validated);

        return response()->json($signatureRequest, 201);
    }

    public function show(SignatureRequest $signatureRequest): JsonResponse
    {
        $signatureRequest->load(['signers.fields', 'fields', 'document', 'createdBy', 'auditLogs']);

        return response()->json($signatureRequest);
    }

    public function update(Request $request, SignatureRequest $signatureRequest): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'expires_at' => 'nullable|date',
            'settings' => 'nullable|array',
            'signers' => 'nullable|array',
            'fields' => 'nullable|array',
        ]);

        $signatureRequest = $this->service->update($signatureRequest, $validated);

        return response()->json($signatureRequest);
    }

    public function destroy(SignatureRequest $signatureRequest): JsonResponse
    {
        if (!$signatureRequest->isEditable()) {
            return response()->json(['message' => 'Cannot delete a signature request that has been sent'], 422);
        }

        $signatureRequest->delete();

        return response()->json(['message' => 'Signature request deleted']);
    }

    public function send(SignatureRequest $signatureRequest): JsonResponse
    {
        try {
            $this->service->send($signatureRequest);
            return response()->json(['message' => 'Signature request sent']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function void(Request $request, SignatureRequest $signatureRequest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->service->void($signatureRequest, $validated['reason']);
            return response()->json(['message' => 'Signature request voided']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function remind(SignatureRequest $signatureRequest): JsonResponse
    {
        $this->service->remind($signatureRequest);

        return response()->json(['message' => 'Reminder sent']);
    }

    public function auditLog(SignatureRequest $signatureRequest): JsonResponse
    {
        $logs = $signatureRequest->auditLogs()->with('signer')->get();

        return response()->json($logs);
    }

    // Signature Templates
    public function templates(Request $request): JsonResponse
    {
        $templates = SignatureTemplate::active()
            ->with('createdBy')
            ->orderBy('name')
            ->get();

        return response()->json($templates);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'signers' => 'nullable|array',
            'fields' => 'nullable|array',
        ]);

        $validated['created_by'] = auth()->id();

        $template = SignatureTemplate::create($validated);

        return response()->json($template, 201);
    }

    public function showTemplate(SignatureTemplate $signatureTemplate): JsonResponse
    {
        return response()->json($signatureTemplate);
    }

    public function updateTemplate(Request $request, SignatureTemplate $signatureTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'signers' => 'nullable|array',
            'fields' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $signatureTemplate->update($validated);

        return response()->json($signatureTemplate);
    }

    public function destroyTemplate(SignatureTemplate $signatureTemplate): JsonResponse
    {
        $signatureTemplate->delete();

        return response()->json(['message' => 'Template deleted']);
    }
}
