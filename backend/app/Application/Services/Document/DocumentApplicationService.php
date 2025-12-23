<?php

declare(strict_types=1);

namespace App\Application\Services\Document;

use App\Domain\Document\Repositories\SignatureRequestRepositoryInterface;
use App\Domain\Shared\Contracts\AuthContextInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use App\Models\DocumentSendLog;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\ModuleRecord;
use App\Models\SignatureTemplate;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentApplicationService
{
    public function __construct(
        private SignatureRequestRepositoryInterface $signatureRequestRepository,
        private AuthContextInterface $authContext,
    ) {}

    // =========================================================================
    // DOCUMENT TEMPLATE USE CASES
    // =========================================================================

    /**
     * List document templates
     */
    public function listTemplates(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DocumentTemplate::query()->with(['createdBy']);

        // Filter by active status
        if (!empty($filters['active_only'])) {
            $query->active();
        }

        // Filter by category
        if (!empty($filters['category'])) {
            $query->category($filters['category']);
        }

        // Filter by accessibility
        if (!empty($filters['accessible_by'])) {
            $query->accessibleBy($filters['accessible_by']);
        }

        // Filter shared only
        if (!empty($filters['shared_only'])) {
            $query->shared();
        }

        // Search
        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a document template
     */
    public function getTemplate(int $templateId): ?DocumentTemplate
    {
        return DocumentTemplate::with(['createdBy', 'updatedBy'])->find($templateId);
    }

    /**
     * Create a document template
     */
    public function createTemplate(array $data): DocumentTemplate
    {
        return DocumentTemplate::create([
            'name' => $data['name'],
            'category' => $data['category'] ?? DocumentTemplate::CATEGORY_OTHER,
            'description' => $data['description'] ?? null,
            'content' => $data['content'],
            'merge_fields' => $data['merge_fields'] ?? [],
            'conditional_blocks' => $data['conditional_blocks'] ?? [],
            'output_format' => $data['output_format'] ?? DocumentTemplate::OUTPUT_PDF,
            'page_settings' => $data['page_settings'] ?? null,
            'header_settings' => $data['header_settings'] ?? null,
            'footer_settings' => $data['footer_settings'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'is_shared' => $data['is_shared'] ?? false,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    /**
     * Update a document template
     */
    public function updateTemplate(int $templateId, array $data): DocumentTemplate
    {
        $template = DocumentTemplate::findOrFail($templateId);

        $template->update([
            'name' => $data['name'] ?? $template->name,
            'category' => $data['category'] ?? $template->category,
            'description' => $data['description'] ?? $template->description,
            'content' => $data['content'] ?? $template->content,
            'merge_fields' => $data['merge_fields'] ?? $template->merge_fields,
            'conditional_blocks' => $data['conditional_blocks'] ?? $template->conditional_blocks,
            'output_format' => $data['output_format'] ?? $template->output_format,
            'page_settings' => $data['page_settings'] ?? $template->page_settings,
            'header_settings' => $data['header_settings'] ?? $template->header_settings,
            'footer_settings' => $data['footer_settings'] ?? $template->footer_settings,
            'is_active' => $data['is_active'] ?? $template->is_active,
            'is_shared' => $data['is_shared'] ?? $template->is_shared,
            'updated_by' => Auth::id(),
        ]);

        if (!empty($data['content']) && $data['content'] !== $template->getOriginal('content')) {
            $template->incrementVersion();
        }

        return $template->fresh();
    }

    /**
     * Delete a document template
     */
    public function deleteTemplate(int $templateId): bool
    {
        return DocumentTemplate::findOrFail($templateId)->delete();
    }

    /**
     * Duplicate a document template
     */
    public function duplicateTemplate(int $templateId): DocumentTemplate
    {
        $template = DocumentTemplate::findOrFail($templateId);
        return $template->duplicate(Auth::id());
    }

    /**
     * Preview template with sample data
     */
    public function previewTemplate(int $templateId, array $sampleData = []): string
    {
        $template = DocumentTemplate::findOrFail($templateId);
        return $this->mergeContent($template->content, $sampleData);
    }

    // =========================================================================
    // DOCUMENT GENERATION USE CASES
    // =========================================================================

    /**
     * Generate a document from template
     */
    public function generateDocument(int $templateId, array $data, ?string $recordType = null, ?int $recordId = null): GeneratedDocument
    {
        $template = DocumentTemplate::findOrFail($templateId);

        // Get merge data from record if provided
        $mergeData = $data['merge_data'] ?? [];
        if ($recordType && $recordId) {
            $record = ModuleRecord::with(['module'])->find($recordId);
            if ($record) {
                $mergeData = array_merge($record->data ?? [], $mergeData);
            }
        }

        // Merge content
        $mergedContent = $this->mergeContent($template->content, $mergeData);

        // Generate file (simplified - actual implementation would use PDF library)
        $fileName = $data['name'] ?? $template->name . '_' . now()->format('Y-m-d_His');
        $filePath = "documents/{$fileName}.{$template->output_format}";

        // Store content (actual implementation would generate proper file)
        Storage::put($filePath, $mergedContent);

        return GeneratedDocument::create([
            'template_id' => $templateId,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'name' => $fileName,
            'output_format' => $template->output_format,
            'file_path' => $filePath,
            'file_url' => Storage::url($filePath),
            'file_size' => Storage::size($filePath),
            'merged_data' => $mergeData,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * List generated documents
     */
    public function listGeneratedDocuments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = GeneratedDocument::query()->with(['template', 'createdBy']);

        // Filter by template
        if (!empty($filters['template_id'])) {
            $query->where('template_id', $filters['template_id']);
        }

        // Filter by record
        if (!empty($filters['record_type']) && !empty($filters['record_id'])) {
            $query->forRecord($filters['record_type'], $filters['record_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->status($filters['status']);
        }

        // Filter by created_by
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Get a generated document
     */
    public function getGeneratedDocument(int $documentId): ?GeneratedDocument
    {
        return GeneratedDocument::with(['template', 'createdBy', 'sendLogs', 'signatureRequests'])->find($documentId);
    }

    /**
     * Delete a generated document
     */
    public function deleteGeneratedDocument(int $documentId): bool
    {
        $document = GeneratedDocument::findOrFail($documentId);

        // Delete file if exists
        if ($document->file_path && Storage::exists($document->file_path)) {
            Storage::delete($document->file_path);
        }

        return $document->delete();
    }

    /**
     * Send document via email
     */
    public function sendDocument(int $documentId, array $recipients, ?string $subject = null, ?string $message = null): DocumentSendLog
    {
        $document = GeneratedDocument::findOrFail($documentId);

        $sendLog = DocumentSendLog::create([
            'document_id' => $documentId,
            'recipients' => $recipients,
            'subject' => $subject ?? "Document: {$document->name}",
            'message' => $message,
            'sent_by' => Auth::id(),
            'sent_at' => now(),
        ]);

        $document->markAsSent();

        // Here you would dispatch an email job
        // SendDocumentEmail::dispatch($document, $recipients, $subject, $message);

        return $sendLog;
    }

    /**
     * Get documents for a record
     */
    public function getDocumentsForRecord(string $recordType, int $recordId): Collection
    {
        return GeneratedDocument::forRecord($recordType, $recordId)
            ->with(['template'])
            ->orderByDesc('created_at')
            ->get();
    }

    // =========================================================================
    // SIGNATURE REQUEST USE CASES
    // =========================================================================

    /**
     * List signature requests
     */
    public function listSignatureRequests(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        return $this->signatureRequestRepository->listSignatureRequests($filters, $perPage, $page);
    }

    /**
     * Get a signature request
     */
    public function getSignatureRequest(int $requestId): ?array
    {
        return $this->signatureRequestRepository->getSignatureRequestWithRelations($requestId);
    }

    /**
     * Get signature request by UUID (for public signing)
     */
    public function getSignatureRequestByUuid(string $uuid): ?array
    {
        return $this->signatureRequestRepository->getSignatureRequestByUuid($uuid);
    }

    /**
     * Create a signature request
     */
    public function createSignatureRequest(array $data): array
    {
        $userId = $this->authContext->userId();
        if (!$userId) {
            throw new \RuntimeException('User must be authenticated');
        }

        return $this->signatureRequestRepository->createSignatureRequest($data, $userId);
    }

    /**
     * Update a signature request (only in draft status)
     */
    public function updateSignatureRequest(int $requestId, array $data): array
    {
        return $this->signatureRequestRepository->updateSignatureRequest($requestId, $data);
    }

    /**
     * Send signature request
     */
    public function sendSignatureRequest(int $requestId): array
    {
        // Here you would dispatch emails to signers
        // foreach ($result['signers'] as $signer) {
        //     SendSignatureRequestEmail::dispatch($result, $signer);
        // }

        return $this->signatureRequestRepository->sendSignatureRequest($requestId);
    }

    /**
     * Void signature request
     */
    public function voidSignatureRequest(int $requestId, string $reason): array
    {
        return $this->signatureRequestRepository->voidSignatureRequest($requestId, $reason);
    }

    /**
     * Add signer to request
     */
    public function addSigner(int $requestId, array $data): array
    {
        return $this->signatureRequestRepository->addSigner($requestId, $data);
    }

    /**
     * Remove signer from request
     */
    public function removeSigner(int $signerId): bool
    {
        return $this->signatureRequestRepository->removeSigner($signerId);
    }

    /**
     * Record signature (for public signing)
     */
    public function recordSignature(string $requestUuid, string $signerEmail, array $signatureData): array
    {
        // Here you would dispatch email to next signer if sequential
        // SendSignatureRequestEmail::dispatch($request, $nextSigner);

        return $this->signatureRequestRepository->recordSignature($requestUuid, $signerEmail, $signatureData);
    }

    /**
     * Decline to sign
     */
    public function declineSignature(string $requestUuid, string $signerEmail, string $reason): array
    {
        return $this->signatureRequestRepository->declineSignature($requestUuid, $signerEmail, $reason);
    }

    /**
     * Get audit trail for a signature request
     */
    public function getSignatureAuditTrail(int $requestId): array
    {
        return $this->signatureRequestRepository->getAuditTrail($requestId);
    }

    /**
     * Send reminder to pending signer
     */
    public function sendSignatureReminder(int $requestId, int $signerId): array
    {
        // SendSignatureReminderEmail::dispatch($request, $signer);

        return $this->signatureRequestRepository->sendReminder($requestId, $signerId);
    }

    // =========================================================================
    // SIGNATURE TEMPLATE USE CASES
    // =========================================================================

    /**
     * List signature templates
     */
    public function listSignatureTemplates(array $filters = []): Collection
    {
        $query = SignatureTemplate::query()->with(['createdBy']);

        if (!empty($filters['active_only'])) {
            $query->where('is_active', true);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'ilike', "%{$filters['search']}%");
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Create signature template from request
     */
    public function createSignatureTemplateFromRequest(int $requestId, string $name): SignatureTemplate
    {
        $request = SignatureRequest::with(['signers', 'fields'])->findOrFail($requestId);

        $signerRoles = $request->signers->map(fn($s) => [
            'role' => $s->role,
            'name_placeholder' => $s->name,
        ])->toArray();

        $fieldTemplates = $request->fields->map(fn($f) => [
            'field_type' => $f->field_type,
            'label' => $f->label,
            'required' => $f->required,
            'page_number' => $f->page_number,
            'position_x' => $f->position_x,
            'position_y' => $f->position_y,
            'width' => $f->width,
            'height' => $f->height,
        ])->toArray();

        return SignatureTemplate::create([
            'name' => $name,
            'signer_roles' => $signerRoles,
            'field_templates' => $fieldTemplates,
            'default_settings' => $request->settings,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);
    }

    // =========================================================================
    // STATISTICS USE CASES
    // =========================================================================

    /**
     * Get document statistics
     */
    public function getDocumentStats(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $documents = GeneratedDocument::whereBetween('created_at', [$start, $end])->get();
        $signatureRequests = SignatureRequest::whereBetween('created_at', [$start, $end])->get();

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'documents' => [
                'total_generated' => $documents->count(),
                'by_status' => $documents->groupBy('status')->map->count()->toArray(),
                'by_format' => $documents->groupBy('output_format')->map->count()->toArray(),
            ],
            'signature_requests' => [
                'total_created' => $signatureRequests->count(),
                'by_status' => $signatureRequests->groupBy('status')->map->count()->toArray(),
                'completed' => $signatureRequests->where('status', SignatureRequest::STATUS_COMPLETED)->count(),
                'avg_completion_time_hours' => round(
                    $signatureRequests->where('status', SignatureRequest::STATUS_COMPLETED)
                        ->map(fn($r) => $r->sent_at?->diffInHours($r->completed_at))
                        ->filter()
                        ->avg() ?? 0,
                    1
                ),
            ],
        ];
    }

    /**
     * Mark expired signature requests
     */
    public function markExpiredSignatureRequests(): int
    {
        return $this->signatureRequestRepository->markExpiredRequests();
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Merge template content with data
     */
    private function mergeContent(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $content = str_replace("{{$key}}", (string)$value, $content);
            $content = str_replace("{{ {$key} }}", (string)$value, $content);
        }

        return $content;
    }
}
