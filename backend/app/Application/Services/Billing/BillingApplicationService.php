<?php

declare(strict_types=1);

namespace App\Application\Services\Billing;

use App\Domain\Billing\DTOs\CreateInvoiceDTO;
use App\Domain\Billing\DTOs\CreateQuoteDTO;
use App\Domain\Billing\DTOs\InvoiceResponseDTO;
use App\Domain\Billing\DTOs\QuoteResponseDTO;
use App\Domain\Billing\Entities\Invoice;
use App\Domain\Billing\Entities\InvoiceLineItem;
use App\Domain\Billing\Entities\InvoicePayment;
use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\Entities\QuoteLineItem;
use App\Domain\Billing\Events\InvoiceCreated;
use App\Domain\Billing\Events\PaymentReceived;
use App\Domain\Billing\Events\QuoteAccepted;
use App\Domain\Billing\Events\QuoteCreated;
use App\Domain\Billing\Events\QuoteSent;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Billing\Repositories\ProductRepositoryInterface;
use App\Domain\Billing\Repositories\QuoteRepositoryInterface;
use App\Domain\Billing\Services\InvoicePricingService;
use App\Domain\Billing\Services\QuotePricingService;
use App\Domain\Billing\ValueObjects\DiscountType;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\PaymentMethod;
use App\Domain\Billing\ValueObjects\PaymentTerms;
use DateTimeImmutable;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Application service for Billing bounded context.
 *
 * This service orchestrates use cases and coordinates between
 * domain entities, repositories, and infrastructure services.
 */
class BillingApplicationService
{
    public function __construct(
        private QuoteRepositoryInterface $quoteRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private ProductRepositoryInterface $productRepository,
        private QuotePricingService $quotePricingService,
        private InvoicePricingService $invoicePricingService,
    ) {}

    // ========== Quote Use Cases ==========

    /**
     * Create a new quote.
     */
    public function createQuote(CreateQuoteDTO $dto): QuoteResponseDTO
    {
        // Generate quote number
        $quoteNumber = $this->generateQuoteNumber();

        $quote = Quote::create(
            quoteNumber: $quoteNumber,
            currency: $dto->currency,
            dealId: $dto->dealId,
            contactId: $dto->contactId,
            companyId: $dto->companyId,
            title: $dto->title,
            validUntil: $dto->validUntil,
            terms: $dto->terms,
            notes: $dto->notes,
            internalNotes: $dto->internalNotes,
            templateId: $dto->templateId,
            discountType: $dto->discountType,
            discountAmount: $dto->discountAmount,
            discountPercent: $dto->discountPercent,
            assignedTo: $dto->assignedTo,
            createdBy: $dto->createdBy,
        );

        // Add line items
        foreach ($dto->lineItems as $index => $lineItemDto) {
            $lineItem = QuoteLineItem::create(
                quoteId: 0, // Will be set after save
                description: $lineItemDto->description,
                quantity: $lineItemDto->quantity,
                unitPrice: new Money($lineItemDto->unitPrice, $dto->currency),
                productId: $lineItemDto->productId,
                discountPercent: $lineItemDto->discountPercent,
                taxRate: $lineItemDto->taxRate,
                displayOrder: $index,
            );
            $quote->addLineItem($lineItem);
        }

        // Validate line items
        $errors = $this->quotePricingService->validateLineItems($quote);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Quote validation failed: ' . implode(', ', $errors));
        }

        $savedQuote = $this->quoteRepository->save($quote);

        // Dispatch domain event
        Event::dispatch(new QuoteCreated(
            quoteId: $savedQuote->getId(),
            quoteNumber: $savedQuote->getQuoteNumber(),
            dealId: $savedQuote->getDealId(),
            contactId: $savedQuote->getContactId(),
            companyId: $savedQuote->getCompanyId(),
            total: $savedQuote->getTotal()->amount(),
            currency: $savedQuote->getCurrency(),
            createdBy: $savedQuote->getCreatedBy(),
        ));

        return QuoteResponseDTO::fromEntity($savedQuote);
    }

    /**
     * Update an existing quote.
     */
    public function updateQuote(
        int $quoteId,
        ?string $title = null,
        ?DateTimeImmutable $validUntil = null,
        ?string $terms = null,
        ?string $notes = null,
        ?string $internalNotes = null,
        ?int $templateId = null,
        ?DiscountType $discountType = null,
        ?float $discountAmount = null,
        ?float $discountPercent = null,
        ?int $assignedTo = null,
    ): QuoteResponseDTO {
        $quote = $this->quoteRepository->findById($quoteId);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found: {$quoteId}");
        }

        $quote->update(
            title: $title,
            validUntil: $validUntil,
            terms: $terms,
            notes: $notes,
            internalNotes: $internalNotes,
            templateId: $templateId,
            discountType: $discountType,
            discountAmount: $discountAmount,
            discountPercent: $discountPercent,
            assignedTo: $assignedTo,
        );

        $savedQuote = $this->quoteRepository->save($quote);

        return QuoteResponseDTO::fromEntity($savedQuote);
    }

    /**
     * Send a quote to the customer.
     */
    public function sendQuote(int $quoteId, string $toEmail): QuoteResponseDTO
    {
        $quote = $this->quoteRepository->findById($quoteId);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found: {$quoteId}");
        }

        $quote->markAsSent($toEmail);
        $savedQuote = $this->quoteRepository->save($quote);

        // Dispatch domain event
        Event::dispatch(new QuoteSent(
            quoteId: $savedQuote->getId(),
            quoteNumber: $savedQuote->getQuoteNumber(),
            toEmail: $toEmail,
            sentBy: $savedQuote->getCreatedBy(),
        ));

        return QuoteResponseDTO::fromEntity($savedQuote);
    }

    /**
     * Mark a quote as viewed (typically when accessed via public URL).
     */
    public function markQuoteViewed(string $viewToken): QuoteResponseDTO
    {
        $quote = $this->quoteRepository->findByViewToken($viewToken);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found for token");
        }

        $quote->markAsViewed();
        $savedQuote = $this->quoteRepository->save($quote);

        return QuoteResponseDTO::fromEntity($savedQuote);
    }

    /**
     * Accept a quote.
     */
    public function acceptQuote(
        string $viewToken,
        string $acceptedBy,
        ?string $signature = null,
        ?string $ip = null,
    ): QuoteResponseDTO {
        $quote = $this->quoteRepository->findByViewToken($viewToken);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found for token");
        }

        $quote->accept($acceptedBy, $signature, $ip);
        $savedQuote = $this->quoteRepository->save($quote);

        // Dispatch domain event
        Event::dispatch(new QuoteAccepted(
            quoteId: $savedQuote->getId(),
            quoteNumber: $savedQuote->getQuoteNumber(),
            acceptedBy: $acceptedBy,
            total: $savedQuote->getTotal()->amount(),
            currency: $savedQuote->getCurrency(),
        ));

        return QuoteResponseDTO::fromEntity($savedQuote);
    }

    /**
     * Reject a quote.
     */
    public function rejectQuote(
        string $viewToken,
        string $rejectedBy,
        ?string $reason = null,
    ): QuoteResponseDTO {
        $quote = $this->quoteRepository->findByViewToken($viewToken);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found for token");
        }

        $quote->reject($rejectedBy, $reason);
        $savedQuote = $this->quoteRepository->save($quote);

        return QuoteResponseDTO::fromEntity($savedQuote);
    }

    /**
     * Get a quote by ID.
     */
    public function getQuote(int $quoteId): QuoteResponseDTO
    {
        $quote = $this->quoteRepository->findById($quoteId);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found: {$quoteId}");
        }

        return QuoteResponseDTO::fromEntity($quote);
    }

    /**
     * Get a quote by view token.
     */
    public function getQuoteByToken(string $viewToken): QuoteResponseDTO
    {
        $quote = $this->quoteRepository->findByViewToken($viewToken);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found for token");
        }

        return QuoteResponseDTO::fromEntity($quote);
    }

    /**
     * Get all quotes for a deal.
     *
     * @return array<QuoteResponseDTO>
     */
    public function getQuotesForDeal(int $dealId): array
    {
        $quotes = $this->quoteRepository->findByDealId($dealId);

        return array_map(
            fn(Quote $q) => QuoteResponseDTO::fromEntity($q),
            $quotes
        );
    }

    /**
     * Delete a quote.
     */
    public function deleteQuote(int $quoteId): bool
    {
        return $this->quoteRepository->delete($quoteId);
    }

    // ========== Invoice Use Cases ==========

    /**
     * Create an invoice from a quote.
     */
    public function createInvoiceFromQuote(int $quoteId, ?PaymentTerms $paymentTerms = null): InvoiceResponseDTO
    {
        $quote = $this->quoteRepository->findById($quoteId);

        if (!$quote) {
            throw new InvalidArgumentException("Quote not found: {$quoteId}");
        }

        $invoiceNumber = $this->generateInvoiceNumber();
        $terms = $paymentTerms ?? PaymentTerms::NET_30;

        $invoice = Invoice::create(
            invoiceNumber: $invoiceNumber,
            currency: $quote->getCurrency(),
            issueDate: new DateTimeImmutable(),
            paymentTerms: $terms,
            quoteId: $quote->getId(),
            dealId: $quote->getDealId(),
            contactId: $quote->getContactId(),
            companyId: $quote->getCompanyId(),
            title: $quote->getTitle(),
            notes: $quote->getNotes(),
            internalNotes: $quote->getInternalNotes(),
            templateId: $quote->getTemplateId(),
            createdBy: $quote->getCreatedBy(),
        );

        // Copy line items from quote
        foreach ($quote->getLineItems() as $quoteLineItem) {
            $invoiceLineItem = InvoiceLineItem::create(
                invoiceId: 0,
                description: $quoteLineItem->getDescription(),
                quantity: $quoteLineItem->getQuantity(),
                unitPrice: $quoteLineItem->getUnitPrice(),
                productId: $quoteLineItem->getProductId(),
                discountPercent: $quoteLineItem->getDiscountPercent(),
                taxRate: $quoteLineItem->getTaxRate(),
                displayOrder: $quoteLineItem->getDisplayOrder(),
            );
            $invoice->addLineItem($invoiceLineItem);
        }

        $savedInvoice = $this->invoiceRepository->save($invoice);

        // Dispatch domain event
        Event::dispatch(new InvoiceCreated(
            invoiceId: $savedInvoice->getId(),
            invoiceNumber: $savedInvoice->getInvoiceNumber(),
            quoteId: $savedInvoice->getQuoteId(),
            dealId: $savedInvoice->getDealId(),
            contactId: $savedInvoice->getContactId(),
            companyId: $savedInvoice->getCompanyId(),
            total: $savedInvoice->getTotal()->amount(),
            currency: $savedInvoice->getCurrency(),
            createdBy: $savedInvoice->getCreatedBy(),
        ));

        return InvoiceResponseDTO::fromEntity($savedInvoice);
    }

    /**
     * Create a new invoice directly.
     */
    public function createInvoice(CreateInvoiceDTO $dto): InvoiceResponseDTO
    {
        $invoiceNumber = $this->generateInvoiceNumber();

        $invoice = Invoice::create(
            invoiceNumber: $invoiceNumber,
            currency: $dto->currency,
            issueDate: $dto->issueDate,
            paymentTerms: $dto->paymentTerms,
            quoteId: $dto->quoteId,
            dealId: $dto->dealId,
            contactId: $dto->contactId,
            companyId: $dto->companyId,
            title: $dto->title,
            notes: $dto->notes,
            internalNotes: $dto->internalNotes,
            templateId: $dto->templateId,
            createdBy: $dto->createdBy,
        );

        // Add line items
        foreach ($dto->lineItems as $index => $lineItemDto) {
            $lineItem = InvoiceLineItem::create(
                invoiceId: 0,
                description: $lineItemDto->description,
                quantity: $lineItemDto->quantity,
                unitPrice: new Money($lineItemDto->unitPrice, $dto->currency),
                productId: $lineItemDto->productId,
                discountPercent: $lineItemDto->discountPercent,
                taxRate: $lineItemDto->taxRate,
                displayOrder: $index,
            );
            $invoice->addLineItem($lineItem);
        }

        // Validate line items
        $errors = $this->invoicePricingService->validateLineItems($invoice);
        if (!empty($errors)) {
            throw new InvalidArgumentException('Invoice validation failed: ' . implode(', ', $errors));
        }

        $savedInvoice = $this->invoiceRepository->save($invoice);

        // Dispatch domain event
        Event::dispatch(new InvoiceCreated(
            invoiceId: $savedInvoice->getId(),
            invoiceNumber: $savedInvoice->getInvoiceNumber(),
            quoteId: $savedInvoice->getQuoteId(),
            dealId: $savedInvoice->getDealId(),
            contactId: $savedInvoice->getContactId(),
            companyId: $savedInvoice->getCompanyId(),
            total: $savedInvoice->getTotal()->amount(),
            currency: $savedInvoice->getCurrency(),
            createdBy: $savedInvoice->getCreatedBy(),
        ));

        return InvoiceResponseDTO::fromEntity($savedInvoice);
    }

    /**
     * Send an invoice to the customer.
     */
    public function sendInvoice(int $invoiceId, string $toEmail): InvoiceResponseDTO
    {
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (!$invoice) {
            throw new InvalidArgumentException("Invoice not found: {$invoiceId}");
        }

        $invoice->markAsSent($toEmail);
        $savedInvoice = $this->invoiceRepository->save($invoice);

        return InvoiceResponseDTO::fromEntity($savedInvoice);
    }

    /**
     * Record a payment for an invoice.
     */
    public function recordPayment(
        int $invoiceId,
        float $amount,
        DateTimeImmutable $paymentDate,
        ?PaymentMethod $paymentMethod = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InvoiceResponseDTO {
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (!$invoice) {
            throw new InvalidArgumentException("Invoice not found: {$invoiceId}");
        }

        $payment = InvoicePayment::create(
            invoiceId: $invoice->getId(),
            amount: new Money($amount, $invoice->getCurrency()),
            paymentDate: $paymentDate,
            paymentMethod: $paymentMethod,
            reference: $reference,
            notes: $notes,
            createdBy: $createdBy,
        );

        $invoice->recordPayment($payment);
        $savedInvoice = $this->invoiceRepository->save($invoice);

        // Dispatch domain event
        Event::dispatch(new PaymentReceived(
            invoiceId: $savedInvoice->getId(),
            invoiceNumber: $savedInvoice->getInvoiceNumber(),
            paymentAmount: $amount,
            totalPaid: $savedInvoice->getAmountPaid()->amount(),
            balanceDue: $savedInvoice->getBalanceDue()->amount(),
            currency: $savedInvoice->getCurrency(),
            isPaidInFull: $savedInvoice->getBalanceDue()->isZero(),
        ));

        return InvoiceResponseDTO::fromEntity($savedInvoice);
    }

    /**
     * Mark an invoice as viewed.
     */
    public function markInvoiceViewed(string $viewToken): InvoiceResponseDTO
    {
        $invoice = $this->invoiceRepository->findByViewToken($viewToken);

        if (!$invoice) {
            throw new InvalidArgumentException("Invoice not found for token");
        }

        $invoice->markAsViewed();
        $savedInvoice = $this->invoiceRepository->save($invoice);

        return InvoiceResponseDTO::fromEntity($savedInvoice);
    }

    /**
     * Cancel an invoice.
     */
    public function cancelInvoice(int $invoiceId): InvoiceResponseDTO
    {
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (!$invoice) {
            throw new InvalidArgumentException("Invoice not found: {$invoiceId}");
        }

        $invoice->cancel();
        $savedInvoice = $this->invoiceRepository->save($invoice);

        return InvoiceResponseDTO::fromEntity($savedInvoice);
    }

    /**
     * Get an invoice by ID.
     */
    public function getInvoice(int $invoiceId): InvoiceResponseDTO
    {
        $invoice = $this->invoiceRepository->findById($invoiceId);

        if (!$invoice) {
            throw new InvalidArgumentException("Invoice not found: {$invoiceId}");
        }

        return InvoiceResponseDTO::fromEntity($invoice);
    }

    /**
     * Get an invoice by view token.
     */
    public function getInvoiceByToken(string $viewToken): InvoiceResponseDTO
    {
        $invoice = $this->invoiceRepository->findByViewToken($viewToken);

        if (!$invoice) {
            throw new InvalidArgumentException("Invoice not found for token");
        }

        return InvoiceResponseDTO::fromEntity($invoice);
    }

    /**
     * Get all invoices for a deal.
     *
     * @return array<InvoiceResponseDTO>
     */
    public function getInvoicesForDeal(int $dealId): array
    {
        $invoices = $this->invoiceRepository->findByDealId($dealId);

        return array_map(
            fn(Invoice $i) => InvoiceResponseDTO::fromEntity($i),
            $invoices
        );
    }

    /**
     * Get overdue invoices.
     *
     * @return array<InvoiceResponseDTO>
     */
    public function getOverdueInvoices(): array
    {
        $invoices = $this->invoiceRepository->findOverdue();

        return array_map(
            fn(Invoice $i) => InvoiceResponseDTO::fromEntity($i),
            $invoices
        );
    }

    /**
     * Get billing statistics.
     */
    public function getStats(): array
    {
        return $this->invoiceRepository->getStats();
    }

    /**
     * Delete an invoice.
     */
    public function deleteInvoice(int $invoiceId): bool
    {
        return $this->invoiceRepository->delete($invoiceId);
    }

    // ========== Helper Methods ==========

    /**
     * Generate a unique quote number.
     */
    private function generateQuoteNumber(): string
    {
        $prefix = 'QT';
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $number = "{$prefix}-{$year}-{$random}";

        // Ensure uniqueness
        while ($this->quoteRepository->quoteNumberExists($number)) {
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $number = "{$prefix}-{$year}-{$random}";
        }

        return $number;
    }

    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $number = "{$prefix}-{$year}-{$random}";

        // Ensure uniqueness
        while ($this->invoiceRepository->invoiceNumberExists($number)) {
            $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $number = "{$prefix}-{$year}-{$random}";
        }

        return $number;
    }
}
