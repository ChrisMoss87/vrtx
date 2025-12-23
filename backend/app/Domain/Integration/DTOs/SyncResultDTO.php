<?php

declare(strict_types=1);

namespace App\Domain\Integration\DTOs;

/**
 * Result of a sync operation
 */
final readonly class SyncResultDTO
{
    public function __construct(
        public bool $success,
        public int $recordsProcessed = 0,
        public int $recordsCreated = 0,
        public int $recordsUpdated = 0,
        public int $recordsSkipped = 0,
        public int $recordsFailed = 0,
        public array $errors = [],
        public array $warnings = [],
        public ?string $nextPageToken = null,
        public bool $hasMorePages = false,
        public int $durationMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'records_processed' => $this->recordsProcessed,
            'records_created' => $this->recordsCreated,
            'records_updated' => $this->recordsUpdated,
            'records_skipped' => $this->recordsSkipped,
            'records_failed' => $this->recordsFailed,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'next_page_token' => $this->nextPageToken,
            'has_more_pages' => $this->hasMorePages,
            'duration_ms' => $this->durationMs,
        ];
    }

    public static function success(
        int $processed = 0,
        int $created = 0,
        int $updated = 0,
        int $skipped = 0,
        array $warnings = [],
        ?string $nextPageToken = null,
        int $durationMs = 0,
    ): self {
        return new self(
            success: true,
            recordsProcessed: $processed,
            recordsCreated: $created,
            recordsUpdated: $updated,
            recordsSkipped: $skipped,
            recordsFailed: 0,
            errors: [],
            warnings: $warnings,
            nextPageToken: $nextPageToken,
            hasMorePages: $nextPageToken !== null,
            durationMs: $durationMs,
        );
    }

    public static function failure(array $errors, int $processed = 0, int $failed = 0): self
    {
        return new self(
            success: false,
            recordsProcessed: $processed,
            recordsFailed: $failed > 0 ? $failed : count($errors),
            errors: $errors,
        );
    }
}
