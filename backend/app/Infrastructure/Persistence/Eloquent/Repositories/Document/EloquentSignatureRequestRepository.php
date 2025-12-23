<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Document;

use App\Domain\Document\Entities\SignatureRequest;
use App\Domain\Document\Repositories\SignatureRequestRepositoryInterface;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class EloquentSignatureRequestRepository implements SignatureRequestRepositoryInterface
{
    private const TABLE = 'signature_requests';
    private const TABLE_SIGNERS = 'signature_signers';
    private const TABLE_FIELDS = 'signature_fields';
    private const TABLE_AUDIT_LOGS = 'signature_audit_logs';
    private const TABLE_DOCUMENTS = 'generated_documents';
    private const TABLE_USERS = 'users';

    // Status constants
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SENT = 'sent';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_DECLINED = 'declined';
    private const STATUS_VOIDED = 'voided';
    private const STATUS_EXPIRED = 'expired';

    // Signer status constants
    private const SIGNER_STATUS_PENDING = 'pending';
    private const SIGNER_STATUS_SIGNED = 'signed';
    private const SIGNER_STATUS_DECLINED = 'declined';

    public function findById(int $id): ?SignatureRequest
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return SignatureRequest::reconstitute(
            id: (int) $row->id,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );
    }

    public function findAll(): array
    {
        return DB::table(self::TABLE)
            ->get()
            ->map(fn($row) => $this->rowToArray($row))
            ->all();
    }

    public function save(SignatureRequest $entity): SignatureRequest
    {
        return $entity;
    }

    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            DB::table(self::TABLE_AUDIT_LOGS)->where('request_id', $id)->delete();
            DB::table(self::TABLE_FIELDS)->where('request_id', $id)->delete();
            DB::table(self::TABLE_SIGNERS)->where('request_id', $id)->delete();
            return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
        });
    }

    public function listSignatureRequests(array $filters = [], int $perPage = 15, int $page = 1): PaginatedResult
    {
        $query = DB::table(self::TABLE);

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Pending only
        if (!empty($filters['pending_only'])) {
            $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SENT]);
        }

        // Filter by source
        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }
        if (!empty($filters['source_id'])) {
            $query->where('source_id', $filters['source_id']);
        }

        // Filter by created_by
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Filter expired
        if (!empty($filters['expired_only'])) {
            $query->where('expires_at', '<', now());
        }

        // Sort
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        $total = $query->count();

        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $rows->map(fn($row) => $this->rowToArrayWithRelations($row))->all();

        return PaginatedResult::create(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getSignatureRequestWithRelations(int $id): ?array
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        $result = $this->rowToArrayWithRelations($row);

        // Load fields
        $result['fields'] = DB::table(self::TABLE_FIELDS)
            ->where('request_id', $id)
            ->get()
            ->map(fn($f) => (array) $f)
            ->all();

        // Load audit logs
        $result['audit_logs'] = DB::table(self::TABLE_AUDIT_LOGS)
            ->where('request_id', $id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($a) => (array) $a)
            ->all();

        return $result;
    }

    public function getSignatureRequestByUuid(string $uuid): ?array
    {
        $row = DB::table(self::TABLE)->where('uuid', $uuid)->first();

        if (!$row) {
            return null;
        }

        $result = $this->rowToArray($row);

        // Load signers
        $result['signers'] = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $row->id)
            ->get()
            ->map(fn($s) => (array) $s)
            ->all();

        // Load fields
        $result['fields'] = DB::table(self::TABLE_FIELDS)
            ->where('request_id', $row->id)
            ->get()
            ->map(fn($f) => (array) $f)
            ->all();

        return $result;
    }

    public function createSignatureRequest(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $requestId = DB::table(self::TABLE)->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'file_url' => $data['file_url'] ?? null,
                'status' => self::STATUS_DRAFT,
                'expires_at' => $data['expires_at'] ?? now()->addDays(30),
                'settings' => json_encode($data['settings'] ?? []),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add signers
            if (!empty($data['signers'])) {
                foreach ($data['signers'] as $index => $signerData) {
                    DB::table(self::TABLE_SIGNERS)->insert([
                        'request_id' => $requestId,
                        'name' => $signerData['name'],
                        'email' => $signerData['email'],
                        'role' => $signerData['role'] ?? 'signer',
                        'sign_order' => $signerData['sign_order'] ?? $index + 1,
                        'access_code' => $signerData['access_code'] ?? null,
                        'status' => self::SIGNER_STATUS_PENDING,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Add signature fields
            if (!empty($data['fields'])) {
                foreach ($data['fields'] as $fieldData) {
                    DB::table(self::TABLE_FIELDS)->insert([
                        'request_id' => $requestId,
                        'signer_id' => $fieldData['signer_id'] ?? null,
                        'field_type' => $fieldData['field_type'],
                        'label' => $fieldData['label'] ?? null,
                        'required' => $fieldData['required'] ?? true,
                        'page_number' => $fieldData['page_number'] ?? 1,
                        'position_x' => $fieldData['position_x'],
                        'position_y' => $fieldData['position_y'],
                        'width' => $fieldData['width'] ?? null,
                        'height' => $fieldData['height'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->logEvent($requestId, 'created', 'Signature request created', null);

            return $this->getSignatureRequestWithRelations($requestId);
        });
    }

    public function updateSignatureRequest(int $id, array $data): array
    {
        $request = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$request) {
            throw new \RuntimeException("Signature request not found: {$id}");
        }

        if (!$this->isEditable($id)) {
            throw new \RuntimeException('Signature request cannot be edited');
        }

        $updateData = ['updated_at' => now()];

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['expires_at'])) {
            $updateData['expires_at'] = $data['expires_at'];
        }
        if (isset($data['settings'])) {
            $updateData['settings'] = json_encode($data['settings']);
        }

        DB::table(self::TABLE)->where('id', $id)->update($updateData);

        return $this->getSignatureRequestWithRelations($id);
    }

    public function sendSignatureRequest(int $id): array
    {
        $request = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$request) {
            throw new \RuntimeException("Signature request not found: {$id}");
        }

        $signerCount = DB::table(self::TABLE_SIGNERS)->where('request_id', $id)->count();

        if ($signerCount === 0) {
            throw new \RuntimeException('Cannot send request without signers');
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'updated_at' => now(),
        ]);

        // Update first signer's sent_at
        $firstSigner = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $id)
            ->orderBy('sign_order')
            ->first();

        if ($firstSigner) {
            DB::table(self::TABLE_SIGNERS)
                ->where('id', $firstSigner->id)
                ->update(['sent_at' => now(), 'updated_at' => now()]);
        }

        $this->logEvent($id, 'sent', 'Signature request sent', null);

        return $this->getSignatureRequestWithRelations($id);
    }

    public function voidSignatureRequest(int $id, string $reason): array
    {
        $request = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$request) {
            throw new \RuntimeException("Signature request not found: {$id}");
        }

        if (!$this->canBeVoided($id)) {
            throw new \RuntimeException('Signature request cannot be voided');
        }

        DB::table(self::TABLE)->where('id', $id)->update([
            'status' => self::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => $reason,
            'updated_at' => now(),
        ]);

        $this->logEvent($id, 'voided', "Request voided: {$reason}", null);

        $row = DB::table(self::TABLE)->where('id', $id)->first();
        return $this->rowToArray($row);
    }

    public function addSigner(int $requestId, array $data): array
    {
        $request = DB::table(self::TABLE)->where('id', $requestId)->first();

        if (!$request) {
            throw new \RuntimeException("Signature request not found: {$requestId}");
        }

        if (!$this->isEditable($requestId)) {
            throw new \RuntimeException('Cannot add signer to non-draft request');
        }

        $maxOrder = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $requestId)
            ->max('sign_order') ?? 0;

        $signerId = DB::table(self::TABLE_SIGNERS)->insertGetId([
            'request_id' => $requestId,
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'signer',
            'sign_order' => $data['sign_order'] ?? $maxOrder + 1,
            'access_code' => $data['access_code'] ?? null,
            'status' => self::SIGNER_STATUS_PENDING,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $signer = DB::table(self::TABLE_SIGNERS)->where('id', $signerId)->first();
        return (array) $signer;
    }

    public function removeSigner(int $signerId): bool
    {
        $signer = DB::table(self::TABLE_SIGNERS)->where('id', $signerId)->first();

        if (!$signer) {
            throw new \RuntimeException("Signer not found: {$signerId}");
        }

        if (!$this->isEditable($signer->request_id)) {
            throw new \RuntimeException('Cannot remove signer from non-draft request');
        }

        return DB::table(self::TABLE_SIGNERS)->where('id', $signerId)->delete() > 0;
    }

    public function recordSignature(string $requestUuid, string $signerEmail, array $signatureData): array
    {
        $request = DB::table(self::TABLE)->where('uuid', $requestUuid)->first();

        if (!$request) {
            throw new \RuntimeException('Signature request not found');
        }

        if ($this->isExpired($request->id)) {
            throw new \RuntimeException('Signature request has expired');
        }

        $signer = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $request->id)
            ->where('email', $signerEmail)
            ->first();

        if (!$signer) {
            throw new \RuntimeException('Signer not found');
        }

        if ($signer->status === self::SIGNER_STATUS_SIGNED) {
            throw new \RuntimeException('Already signed');
        }

        // Verify access code if required
        if ($signer->access_code && ($signatureData['access_code'] ?? '') !== $signer->access_code) {
            throw new \RuntimeException('Invalid access code');
        }

        return DB::transaction(function () use ($request, $signer, $signatureData) {
            // Update signer
            DB::table(self::TABLE_SIGNERS)->where('id', $signer->id)->update([
                'status' => self::SIGNER_STATUS_SIGNED,
                'signed_at' => now(),
                'signature_data' => json_encode($signatureData['signature_data']),
                'signature_image_url' => $signatureData['signature_image_url'] ?? null,
                'ip_address' => $signatureData['ip_address'] ?? null,
                'updated_at' => now(),
            ]);

            $this->logEvent($request->id, 'signed', "Signed by {$signer->name}", $signer->id);

            // Update field values
            if (!empty($signatureData['field_values'])) {
                foreach ($signatureData['field_values'] as $fieldId => $value) {
                    DB::table(self::TABLE_FIELDS)
                        ->where('id', $fieldId)
                        ->where('signer_id', $signer->id)
                        ->update(['value' => $value, 'updated_at' => now()]);
                }
            }

            // Check if all signers have completed
            $this->checkCompletion($request->id);

            // Send to next signer if sequential
            $nextSigner = DB::table(self::TABLE_SIGNERS)
                ->where('request_id', $request->id)
                ->where('status', self::SIGNER_STATUS_PENDING)
                ->whereNull('sent_at')
                ->orderBy('sign_order')
                ->first();

            if ($nextSigner) {
                DB::table(self::TABLE_SIGNERS)
                    ->where('id', $nextSigner->id)
                    ->update(['sent_at' => now(), 'updated_at' => now()]);
            }

            $updatedSigner = DB::table(self::TABLE_SIGNERS)->where('id', $signer->id)->first();
            return (array) $updatedSigner;
        });
    }

    public function declineSignature(string $requestUuid, string $signerEmail, string $reason): array
    {
        $request = DB::table(self::TABLE)->where('uuid', $requestUuid)->first();

        if (!$request) {
            throw new \RuntimeException('Signature request not found');
        }

        $signer = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $request->id)
            ->where('email', $signerEmail)
            ->first();

        if (!$signer) {
            throw new \RuntimeException('Signer not found');
        }

        return DB::transaction(function () use ($request, $signer, $reason) {
            DB::table(self::TABLE_SIGNERS)->where('id', $signer->id)->update([
                'status' => self::SIGNER_STATUS_DECLINED,
                'declined_at' => now(),
                'decline_reason' => $reason,
                'updated_at' => now(),
            ]);

            DB::table(self::TABLE)->where('id', $request->id)->update([
                'status' => self::STATUS_DECLINED,
                'updated_at' => now(),
            ]);

            $this->logEvent($request->id, 'declined', "Declined by {$signer->name}: {$reason}", $signer->id);

            $updatedSigner = DB::table(self::TABLE_SIGNERS)->where('id', $signer->id)->first();
            return (array) $updatedSigner;
        });
    }

    public function getAuditTrail(int $requestId): array
    {
        return DB::table(self::TABLE_AUDIT_LOGS)
            ->where('request_id', $requestId)
            ->orderBy('created_at')
            ->get()
            ->map(function ($log) {
                $array = (array) $log;
                if ($log->signer_id) {
                    $signer = DB::table(self::TABLE_SIGNERS)->where('id', $log->signer_id)->first();
                    $array['signer'] = $signer ? (array) $signer : null;
                }
                return $array;
            })
            ->all();
    }

    public function sendReminder(int $requestId, int $signerId): array
    {
        $request = DB::table(self::TABLE)->where('id', $requestId)->first();

        if (!$request) {
            throw new \RuntimeException("Signature request not found: {$requestId}");
        }

        $signer = DB::table(self::TABLE_SIGNERS)->where('id', $signerId)->first();

        if (!$signer) {
            throw new \RuntimeException("Signer not found: {$signerId}");
        }

        if ($signer->status !== self::SIGNER_STATUS_PENDING) {
            throw new \RuntimeException('Signer has already completed');
        }

        DB::table(self::TABLE_SIGNERS)->where('id', $signerId)->update([
            'reminder_sent_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logEvent($requestId, 'reminder_sent', "Reminder sent to {$signer->name}", $signerId);

        $updatedSigner = DB::table(self::TABLE_SIGNERS)->where('id', $signerId)->first();
        return (array) $updatedSigner;
    }

    public function markExpiredRequests(): int
    {
        return DB::table(self::TABLE)
            ->whereIn('status', [self::STATUS_DRAFT, self::STATUS_SENT])
            ->where('expires_at', '<', now())
            ->update(['status' => self::STATUS_EXPIRED, 'updated_at' => now()]);
    }

    public function isEditable(int $id): bool
    {
        $request = DB::table(self::TABLE)->where('id', $id)->first();
        return $request && $request->status === self::STATUS_DRAFT;
    }

    public function canBeVoided(int $id): bool
    {
        $request = DB::table(self::TABLE)->where('id', $id)->first();
        return $request && in_array($request->status, [self::STATUS_DRAFT, self::STATUS_SENT]);
    }

    public function isExpired(int $id): bool
    {
        $request = DB::table(self::TABLE)->where('id', $id)->first();
        return $request && $request->expires_at && $request->expires_at < now();
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    private function rowToArray(stdClass $row): array
    {
        $array = (array) $row;

        // Handle JSON fields
        if (isset($array['settings']) && is_string($array['settings'])) {
            $array['settings'] = json_decode($array['settings'], true);
        }

        return $array;
    }

    private function rowToArrayWithRelations(stdClass $row): array
    {
        $result = $this->rowToArray($row);

        // Load document
        if ($row->document_id) {
            $document = DB::table(self::TABLE_DOCUMENTS)->where('id', $row->document_id)->first();
            $result['document'] = $document ? (array) $document : null;
        }

        // Load creator
        if ($row->created_by) {
            $creator = DB::table(self::TABLE_USERS)
                ->select('id', 'name', 'email')
                ->where('id', $row->created_by)
                ->first();
            $result['created_by_user'] = $creator ? (array) $creator : null;
        }

        // Load signers
        $result['signers'] = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $row->id)
            ->orderBy('sign_order')
            ->get()
            ->map(fn($s) => (array) $s)
            ->all();

        return $result;
    }

    private function logEvent(int $requestId, string $event, string $description, ?int $signerId): void
    {
        DB::table(self::TABLE_AUDIT_LOGS)->insert([
            'request_id' => $requestId,
            'signer_id' => $signerId,
            'event' => $event,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function checkCompletion(int $requestId): void
    {
        $pendingCount = DB::table(self::TABLE_SIGNERS)
            ->where('request_id', $requestId)
            ->where('status', self::SIGNER_STATUS_PENDING)
            ->count();

        if ($pendingCount === 0) {
            DB::table(self::TABLE)->where('id', $requestId)->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

            $this->logEvent($requestId, 'completed', 'All signers have signed', null);
        }
    }
}
