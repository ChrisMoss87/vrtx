<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\ValueObjects\QuoteStatus;
use App\Domain\Shared\ValueObjects\PaginatedResult;

/**
 * Repository interface for Quote aggregate root.
 */
interface QuoteRepositoryInterface
{
    /**
     * Find a quote by its ID.
     */
    public function findById(int $id): ?Quote;

    /**
     * Find a quote by its view token.
     */
    public function findByViewToken(string $viewToken): ?Quote;

    /**
     * Find a quote by its quote number.
     */
    public function findByQuoteNumber(string $quoteNumber): ?Quote;

    /**
     * Find all quotes.
     *
     * @return array<Quote>
     */
    public function findAll(): array;

    /**
     * Find quotes by status.
     *
     * @return array<Quote>
     */
    public function findByStatus(QuoteStatus $status): array;

    /**
     * Find quotes for a specific deal.
     *
     * @return array<Quote>
     */
    public function findByDealId(int $dealId): array;

    /**
     * Find quotes for a specific contact.
     *
     * @return array<Quote>
     */
    public function findByContactId(int $contactId): array;

    /**
     * Find quotes for a specific company.
     *
     * @return array<Quote>
     */
    public function findByCompanyId(int $companyId): array;

    /**
     * Find quotes assigned to a specific user.
     *
     * @return array<Quote>
     */
    public function findByAssignedTo(int $userId): array;

    /**
     * Find expired quotes.
     *
     * @return array<Quote>
     */
    public function findExpired(): array;

    /**
     * Search quotes with filters and pagination.
     *
     * @param array<string, mixed> $filters
     * @param array<string, string> $orderBy ['field' => 'asc|desc']
     */
    public function search(
        array $filters = [],
        array $orderBy = ['created_at' => 'desc'],
        int $page = 1,
        int $perPage = 25
    ): PaginatedResult;

    /**
     * Get all quotes as array data.
     *
     * @return array<array<string, mixed>>
     */
    public function getAllAsArray(): array;

    /**
     * Get quotes by filters as array data.
     *
     * @param array<string, mixed> $filters
     * @return array<array<string, mixed>>
     */
    public function getByFiltersAsArray(array $filters): array;

    /**
     * Save a quote (insert or update).
     */
    public function save(Quote $quote): Quote;

    /**
     * Delete a quote.
     */
    public function delete(int $id): bool;

    /**
     * Check if a quote number already exists.
     */
    public function quoteNumberExists(string $quoteNumber, ?int $excludeId = null): bool;
}
