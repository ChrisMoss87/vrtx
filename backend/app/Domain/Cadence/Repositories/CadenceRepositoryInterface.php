<?php

declare(strict_types=1);

namespace App\Domain\Cadence\Repositories;

use App\Domain\Cadence\Entities\Cadence;
use App\Domain\Shared\ValueObjects\PaginatedResult;

interface CadenceRepositoryInterface
{
    // =========================================================================
    // ENTITY METHODS (DDD-compliant)
    // =========================================================================

    public function findById(int $id): ?Cadence;

    public function save(Cadence $cadence): Cadence;

    public function delete(int $id): bool;

    // =========================================================================
    // ARRAY METHODS (backward-compatible)
    // =========================================================================

    public function findByIdAsArray(int $id): ?array;

    public function findByIdWithRelations(int $id): ?array;

    public function create(array $data): array;

    public function update(int $id, array $data): array;

    // =========================================================================
    // QUERY METHODS - CADENCES
    // =========================================================================

    public function findWithFilters(array $filters, int $perPage = 20): PaginatedResult;

    public function findAll(): array;

    public function findActive(): array;

    public function findForModule(int $moduleId, bool $activeOnly = false): array;

    public function findByOwner(int $ownerId, bool $activeOnly = false): array;

    public function canEnroll(int $cadenceId): bool;

    public function duplicate(int $id, string $newName, int $createdBy): array;

    // =========================================================================
    // BASIC CRUD - STEPS
    // =========================================================================

    public function findStepById(int $stepId): ?array;

    public function createStep(int $cadenceId, array $data): array;

    public function updateStep(int $stepId, array $data): array;

    public function deleteStep(int $stepId): bool;

    public function reorderSteps(int $cadenceId, array $stepIds): void;

    public function findStepsForCadence(int $cadenceId, bool $activeOnly = false): array;

    // =========================================================================
    // BASIC CRUD - ENROLLMENTS
    // =========================================================================

    public function findEnrollmentById(int $id): ?array;

    public function createEnrollment(array $data): array;

    public function updateEnrollment(int $id, array $data): array;

    public function deleteEnrollment(int $id): bool;

    public function findEnrollments(int $cadenceId, array $filters, int $perPage = 20): PaginatedResult;

    public function findActiveEnrollmentForRecord(int $cadenceId, int $recordId): ?array;

    public function findPreviousEnrollment(int $cadenceId, int $recordId): ?array;

    public function countActiveEnrollments(int $cadenceId): int;

    // =========================================================================
    // BASIC CRUD - TEMPLATES
    // =========================================================================

    public function findTemplateById(int $id): ?array;

    public function findTemplates(?string $category = null): array;

    public function createTemplate(array $data): array;

    public function updateTemplate(int $id, array $data): array;

    public function deleteTemplate(int $id): bool;

    public function createTemplateStep(int $templateId, array $data): array;

    // =========================================================================
    // ANALYTICS
    // =========================================================================

    public function getAnalytics(int $cadenceId, ?string $startDate = null, ?string $endDate = null): array;

    public function getStepPerformance(int $cadenceId): array;

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    public function calculateNextStepTime(int $stepId): ?\DateTimeInterface;
}
