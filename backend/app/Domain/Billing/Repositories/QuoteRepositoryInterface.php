<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\ValueObjects\QuoteStatus;

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
