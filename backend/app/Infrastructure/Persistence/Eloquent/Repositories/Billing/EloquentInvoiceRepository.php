<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Billing;

use App\Domain\Billing\Entities\Invoice;
use App\Domain\Billing\Entities\InvoiceLineItem;
use App\Domain\Billing\Entities\InvoicePayment;
use App\Domain\Billing\Repositories\InvoiceRepositoryInterface;
use App\Domain\Billing\ValueObjects\InvoiceStatus;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\PaymentMethod;
use App\Domain\Billing\ValueObjects\PaymentTerms;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the InvoiceRepository.
 */
class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    private const TABLE = 'invoices';
    private const TABLE_LINE_ITEMS = 'invoice_line_items';
    private const TABLE_PAYMENTS = 'invoice_payments';

    public function findById(int $id): ?Invoice
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByViewToken(string $viewToken): ?Invoice
    {
        $row = DB::table(self::TABLE)
            ->where('view_token', $viewToken)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        $row = DB::table(self::TABLE)
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findAll(): array
    {
        $rows = DB::table(self::TABLE)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findByStatus(InvoiceStatus $status): array
    {
        $rows = DB::table(self::TABLE)
            ->where('status', $status->value)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findByQuoteId(int $quoteId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('quote_id', $quoteId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findByDealId(int $dealId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('deal_id', $dealId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findByContactId(int $contactId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findByCompanyId(int $companyId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findOverdue(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('due_date', '<', now()->startOfDay())
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->orderBy('due_date')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findUnpaid(): array
    {
        $rows = DB::table(self::TABLE)
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->orderBy('due_date')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function save(Invoice $invoice): Invoice
    {
        $data = $this->toRowData($invoice);

        if ($invoice->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $invoice->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $invoice->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Sync line items
        $this->syncLineItems($id, $invoice->getLineItems());

        // Sync payments
        $this->syncPayments($id, $invoice->getPayments());

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Delete related records first
        DB::table(self::TABLE_LINE_ITEMS)->where('invoice_id', $id)->delete();
        DB::table(self::TABLE_PAYMENTS)->where('invoice_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function invoiceNumberExists(string $invoiceNumber, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)->where('invoice_number', $invoiceNumber);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function getStats(): array
    {
        $total = DB::table(self::TABLE)->count();
        $draft = DB::table(self::TABLE)->where('status', InvoiceStatus::DRAFT->value)->count();
        $sent = DB::table(self::TABLE)->whereIn('status', [InvoiceStatus::SENT->value, InvoiceStatus::VIEWED->value])->count();
        $paid = DB::table(self::TABLE)->where('status', InvoiceStatus::PAID->value)->count();
        $partial = DB::table(self::TABLE)->where('status', InvoiceStatus::PARTIAL->value)->count();
        $overdue = DB::table(self::TABLE)->where('status', InvoiceStatus::OVERDUE->value)->count();

        $totalAmount = DB::table(self::TABLE)->whereNotIn('status', [InvoiceStatus::CANCELLED->value])->sum('total');
        $paidAmount = DB::table(self::TABLE)->whereNotIn('status', [InvoiceStatus::CANCELLED->value])->sum('amount_paid');
        $outstandingAmount = DB::table(self::TABLE)->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])->sum('balance_due');

        return [
            'total_count' => $total,
            'draft_count' => $draft,
            'sent_count' => $sent,
            'paid_count' => $paid,
            'partial_count' => $partial,
            'overdue_count' => $overdue,
            'total_amount' => (float) $totalAmount,
            'paid_amount' => (float) $paidAmount,
            'outstanding_amount' => (float) $outstandingAmount,
        ];
    }

    public function search(
        array $filters = [],
        array $orderBy = ['created_at' => 'desc'],
        int $page = 1,
        int $perPage = 25
    ): PaginatedResult {
        $query = DB::table(self::TABLE);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['quote_id'])) {
            $query->where('quote_id', $filters['quote_id']);
        }

        if (!empty($filters['deal_id'])) {
            $query->where('deal_id', $filters['deal_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'ilike', "%{$search}%")
                    ->orWhere('title', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        // Apply ordering
        foreach ($orderBy as $field => $direction) {
            $query->orderBy($field, $direction);
        }

        // Get total count
        $total = $query->count();

        // Get paginated rows
        $rows = $query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Convert to domain entities
        $domainEntities = $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();

        return PaginatedResult::create(
            items: $domainEntities,
            total: $total,
            perPage: $perPage,
            currentPage: $page
        );
    }

    public function getAllAsArray(): array
    {
        $rows = DB::table(self::TABLE)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toArrayWithRelations($row))->all();
    }

    public function getByFiltersAsArray(array $filters): array
    {
        $query = DB::table(self::TABLE);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['quote_id'])) {
            $query->where('quote_id', $filters['quote_id']);
        }

        if (!empty($filters['deal_id'])) {
            $query->where('deal_id', $filters['deal_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->toArrayWithRelations($row))->all();
    }

    /**
     * Sync line items for an invoice.
     *
     * @param InvoiceLineItem[] $lineItems
     */
    private function syncLineItems(int $invoiceId, array $lineItems): void
    {
        // Delete existing line items
        DB::table(self::TABLE_LINE_ITEMS)->where('invoice_id', $invoiceId)->delete();

        // Create new line items
        foreach ($lineItems as $index => $lineItem) {
            DB::table(self::TABLE_LINE_ITEMS)->insert([
                'invoice_id' => $invoiceId,
                'product_id' => $lineItem->getProductId(),
                'name' => $lineItem->getDescription(),
                'description' => $lineItem->getDescription(),
                'quantity' => $lineItem->getQuantity(),
                'unit_price' => $lineItem->getUnitPrice()->amount(),
                'discount_percent' => $lineItem->getDiscountPercent(),
                'tax_rate' => $lineItem->getTaxRate(),
                'line_total' => $lineItem->calculateSubtotal()->amount(),
                'display_order' => $lineItem->getDisplayOrder() ?: $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Sync payments for an invoice.
     *
     * @param InvoicePayment[] $payments
     */
    private function syncPayments(int $invoiceId, array $payments): void
    {
        // Delete existing payments
        DB::table(self::TABLE_PAYMENTS)->where('invoice_id', $invoiceId)->delete();

        // Create new payments
        foreach ($payments as $payment) {
            DB::table(self::TABLE_PAYMENTS)->insert([
                'invoice_id' => $invoiceId,
                'amount' => $payment->getAmount()->amount(),
                'payment_method' => $payment->getPaymentMethod()?->value,
                'payment_date' => $payment->getPaymentDate()->format('Y-m-d'),
                'reference' => $payment->getReference(),
                'notes' => $payment->getNotes(),
                'created_by' => $payment->getCreatedBy(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Get line items for an invoice.
     */
    private function getLineItemsForInvoice(int $invoiceId): array
    {
        return DB::table(self::TABLE_LINE_ITEMS)
            ->where('invoice_id', $invoiceId)
            ->orderBy('display_order')
            ->get()
            ->all();
    }

    /**
     * Get payments for an invoice.
     */
    private function getPaymentsForInvoice(int $invoiceId): array
    {
        return DB::table(self::TABLE_PAYMENTS)
            ->where('invoice_id', $invoiceId)
            ->orderBy('payment_date')
            ->get()
            ->all();
    }

    /**
     * Convert a database row to a domain entity with relations.
     */
    private function toDomainEntityWithRelations(stdClass $row): Invoice
    {
        $currency = $row->currency ?? 'USD';

        $invoice = Invoice::reconstitute(
            id: (int) $row->id,
            invoiceNumber: $row->invoice_number,
            quoteId: $row->quote_id ? (int) $row->quote_id : null,
            dealId: $row->deal_id ? (int) $row->deal_id : null,
            contactId: $row->contact_id ? (int) $row->contact_id : null,
            companyId: $row->company_id ? (int) $row->company_id : null,
            status: InvoiceStatus::from($row->status),
            title: $row->title,
            subtotal: new Money((float) $row->subtotal, $currency),
            discountAmount: new Money((float) $row->discount_amount, $currency),
            taxAmount: new Money((float) $row->tax_amount, $currency),
            total: new Money((float) $row->total, $currency),
            amountPaid: new Money((float) $row->amount_paid, $currency),
            balanceDue: new Money((float) $row->balance_due, $currency),
            currency: $currency,
            issueDate: new DateTimeImmutable($row->issue_date),
            dueDate: new DateTimeImmutable($row->due_date),
            paymentTerms: PaymentTerms::from($row->payment_terms ?? 'net_30'),
            notes: $row->notes,
            internalNotes: $row->internal_notes,
            templateId: $row->template_id ? (int) $row->template_id : null,
            viewToken: $row->view_token,
            sentAt: $row->sent_at ? new DateTimeImmutable($row->sent_at) : null,
            sentToEmail: $row->sent_to_email,
            viewedAt: $row->viewed_at ? new DateTimeImmutable($row->viewed_at) : null,
            paidAt: $row->paid_at ? new DateTimeImmutable($row->paid_at) : null,
            createdBy: $row->created_by ? (int) $row->created_by : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );

        // Load line items
        $lineItemRows = $this->getLineItemsForInvoice((int) $row->id);
        $lineItems = array_map(function ($item) use ($row, $currency) {
            return InvoiceLineItem::reconstitute(
                id: (int) $item->id,
                invoiceId: (int) $row->id,
                productId: $item->product_id ? (int) $item->product_id : null,
                description: $item->name ?? $item->description ?? '',
                quantity: (float) $item->quantity,
                unitPrice: new Money((float) $item->unit_price, $currency),
                discountPercent: (float) $item->discount_percent,
                taxRate: (float) $item->tax_rate,
                displayOrder: (int) ($item->display_order ?? 0),
            );
        }, $lineItemRows);

        $invoice->setLineItems($lineItems);

        // Load payments
        $paymentRows = $this->getPaymentsForInvoice((int) $row->id);
        $payments = array_map(function ($payment) use ($row, $currency) {
            return InvoicePayment::reconstitute(
                id: (int) $payment->id,
                invoiceId: (int) $row->id,
                amount: new Money((float) $payment->amount, $currency),
                paymentDate: new DateTimeImmutable($payment->payment_date),
                paymentMethod: $payment->payment_method ? PaymentMethod::tryFrom($payment->payment_method) : null,
                reference: $payment->reference,
                notes: $payment->notes,
                createdBy: $payment->created_by ? (int) $payment->created_by : null,
                createdAt: $payment->created_at ? new DateTimeImmutable($payment->created_at) : null,
            );
        }, $paymentRows);

        $invoice->setPayments($payments);

        return $invoice;
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(Invoice $invoice): array
    {
        return [
            'invoice_number' => $invoice->getInvoiceNumber(),
            'quote_id' => $invoice->getQuoteId(),
            'deal_id' => $invoice->getDealId(),
            'contact_id' => $invoice->getContactId(),
            'company_id' => $invoice->getCompanyId(),
            'status' => $invoice->getStatus()->value,
            'title' => $invoice->getTitle(),
            'subtotal' => $invoice->getSubtotal()->amount(),
            'discount_amount' => $invoice->getDiscountAmount()->amount(),
            'tax_amount' => $invoice->getTaxAmount()->amount(),
            'total' => $invoice->getTotal()->amount(),
            'amount_paid' => $invoice->getAmountPaid()->amount(),
            'balance_due' => $invoice->getBalanceDue()->amount(),
            'currency' => $invoice->getCurrency(),
            'issue_date' => $invoice->getIssueDate()->format('Y-m-d'),
            'due_date' => $invoice->getDueDate()->format('Y-m-d'),
            'payment_terms' => $invoice->getPaymentTerms()->value,
            'notes' => $invoice->getNotes(),
            'internal_notes' => $invoice->getInternalNotes(),
            'template_id' => $invoice->getTemplateId(),
            'view_token' => $invoice->getViewToken(),
            'sent_at' => $invoice->getSentAt()?->format('Y-m-d H:i:s'),
            'sent_to_email' => $invoice->getSentToEmail(),
            'viewed_at' => $invoice->getViewedAt()?->format('Y-m-d H:i:s'),
            'paid_at' => $invoice->getPaidAt()?->format('Y-m-d H:i:s'),
            'created_by' => $invoice->getCreatedBy(),
        ];
    }

    /**
     * Convert a database row to array with relations.
     *
     * @return array<string, mixed>
     */
    private function toArrayWithRelations(stdClass $row): array
    {
        $lineItemRows = $this->getLineItemsForInvoice((int) $row->id);
        $paymentRows = $this->getPaymentsForInvoice((int) $row->id);

        return [
            'id' => $row->id,
            'invoice_number' => $row->invoice_number,
            'quote_id' => $row->quote_id,
            'deal_id' => $row->deal_id,
            'contact_id' => $row->contact_id,
            'company_id' => $row->company_id,
            'status' => $row->status,
            'title' => $row->title,
            'subtotal' => (float) $row->subtotal,
            'discount_amount' => (float) $row->discount_amount,
            'tax_amount' => (float) $row->tax_amount,
            'total' => (float) $row->total,
            'amount_paid' => (float) $row->amount_paid,
            'balance_due' => (float) $row->balance_due,
            'currency' => $row->currency,
            'issue_date' => $row->issue_date,
            'due_date' => $row->due_date,
            'payment_terms' => $row->payment_terms,
            'notes' => $row->notes,
            'internal_notes' => $row->internal_notes,
            'template_id' => $row->template_id,
            'view_token' => $row->view_token,
            'sent_at' => $row->sent_at,
            'sent_to_email' => $row->sent_to_email,
            'viewed_at' => $row->viewed_at,
            'paid_at' => $row->paid_at,
            'created_by' => $row->created_by,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'line_items' => array_map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) $item->discount_percent,
                    'tax_rate' => (float) $item->tax_rate,
                    'line_total' => (float) $item->line_total,
                    'display_order' => $item->display_order,
                ];
            }, $lineItemRows),
            'payments' => array_map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date,
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                    'created_by' => $payment->created_by,
                    'created_at' => $payment->created_at,
                ];
            }, $paymentRows),
        ];
    }
}
