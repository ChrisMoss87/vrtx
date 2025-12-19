<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repositories;

use App\Domain\Billing\Entities\Invoice;
use App\Domain\Billing\ValueObjects\InvoiceStatus;

/**
 * Repository interface for Invoice aggregate root.
 */
interface InvoiceRepositoryInterface
{
    /**
     * Find an invoice by its ID.
     */
    public function findById(int $id): ?Invoice;

    /**
     * Find an invoice by its view token.
     */
    public function findByViewToken(string $viewToken): ?Invoice;

    /**
     * Find an invoice by its invoice number.
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice;

    /**
     * Find all invoices.
     *
     * @return array<Invoice>
     */
    public function findAll(): array;

    /**
     * Find invoices by status.
     *
     * @return array<Invoice>
     */
    public function findByStatus(InvoiceStatus $status): array;

    /**
     * Find invoices for a specific quote.
     *
     * @return array<Invoice>
     */
    public function findByQuoteId(int $quoteId): array;

    /**
     * Find invoices for a specific deal.
     *
     * @return array<Invoice>
     */
    public function findByDealId(int $dealId): array;

    /**
     * Find invoices for a specific contact.
     *
     * @return array<Invoice>
     */
    public function findByContactId(int $contactId): array;

    /**
     * Find invoices for a specific company.
     *
     * @return array<Invoice>
     */
    public function findByCompanyId(int $companyId): array;

    /**
     * Find overdue invoices.
     *
     * @return array<Invoice>
     */
    public function findOverdue(): array;

    /**
     * Find unpaid invoices.
     *
     * @return array<Invoice>
     */
    public function findUnpaid(): array;

    /**
     * Save an invoice (insert or update).
     */
    public function save(Invoice $invoice): Invoice;

    /**
     * Delete an invoice.
     */
    public function delete(int $id): bool;

    /**
     * Check if an invoice number already exists.
     */
    public function invoiceNumberExists(string $invoiceNumber, ?int $excludeId = null): bool;

    /**
     * Get invoice statistics.
     */
    public function getStats(): array;
}
