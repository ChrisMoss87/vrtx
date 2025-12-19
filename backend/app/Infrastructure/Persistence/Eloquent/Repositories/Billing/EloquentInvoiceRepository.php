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
use App\Models\Invoice as InvoiceModel;
use App\Models\InvoiceLineItem as InvoiceLineItemModel;
use App\Models\InvoicePayment as InvoicePaymentModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the InvoiceRepository.
 */
class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function findById(int $id): ?Invoice
    {
        $model = InvoiceModel::with(['lineItems', 'payments'])->find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByViewToken(string $viewToken): ?Invoice
    {
        $model = InvoiceModel::with(['lineItems', 'payments'])
            ->where('view_token', $viewToken)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        $model = InvoiceModel::with(['lineItems', 'payments'])
            ->where('invoice_number', $invoiceNumber)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findAll(): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByStatus(InvoiceStatus $status): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->where('status', $status->value)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByQuoteId(int $quoteId): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->where('quote_id', $quoteId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByDealId(int $dealId): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->where('deal_id', $dealId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByContactId(int $contactId): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByCompanyId(int $companyId): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findOverdue(): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->where('due_date', '<', now()->startOfDay())
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->orderBy('due_date')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findUnpaid(): array
    {
        $models = InvoiceModel::with(['lineItems', 'payments'])
            ->whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])
            ->orderBy('due_date')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(Invoice $invoice): Invoice
    {
        $data = $this->toModelData($invoice);

        if ($invoice->getId() !== null) {
            $model = InvoiceModel::findOrFail($invoice->getId());
            $model->update($data);
        } else {
            $model = InvoiceModel::create($data);
        }

        // Sync line items
        $this->syncLineItems($model, $invoice->getLineItems());

        // Sync payments
        $this->syncPayments($model, $invoice->getPayments());

        return $this->toDomainEntity($model->fresh(['lineItems', 'payments']));
    }

    public function delete(int $id): bool
    {
        $model = InvoiceModel::find($id);

        if (!$model) {
            return false;
        }

        // Delete related records first
        $model->lineItems()->delete();
        $model->payments()->delete();

        return $model->delete() ?? false;
    }

    public function invoiceNumberExists(string $invoiceNumber, ?int $excludeId = null): bool
    {
        $query = InvoiceModel::where('invoice_number', $invoiceNumber);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function getStats(): array
    {
        $total = InvoiceModel::count();
        $draft = InvoiceModel::where('status', InvoiceStatus::DRAFT->value)->count();
        $sent = InvoiceModel::whereIn('status', [InvoiceStatus::SENT->value, InvoiceStatus::VIEWED->value])->count();
        $paid = InvoiceModel::where('status', InvoiceStatus::PAID->value)->count();
        $partial = InvoiceModel::where('status', InvoiceStatus::PARTIAL->value)->count();
        $overdue = InvoiceModel::where('status', InvoiceStatus::OVERDUE->value)->count();

        $totalAmount = InvoiceModel::whereNotIn('status', [InvoiceStatus::CANCELLED->value])->sum('total');
        $paidAmount = InvoiceModel::whereNotIn('status', [InvoiceStatus::CANCELLED->value])->sum('amount_paid');
        $outstandingAmount = InvoiceModel::whereNotIn('status', [InvoiceStatus::PAID->value, InvoiceStatus::CANCELLED->value])->sum('balance_due');

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

    /**
     * Sync line items for an invoice.
     *
     * @param InvoiceLineItem[] $lineItems
     */
    private function syncLineItems(InvoiceModel $model, array $lineItems): void
    {
        // Delete existing line items
        $model->lineItems()->delete();

        // Create new line items
        foreach ($lineItems as $index => $lineItem) {
            InvoiceLineItemModel::create([
                'invoice_id' => $model->id,
                'product_id' => $lineItem->getProductId(),
                'name' => $lineItem->getDescription(),
                'description' => $lineItem->getDescription(),
                'quantity' => $lineItem->getQuantity(),
                'unit_price' => $lineItem->getUnitPrice()->amount(),
                'discount_percent' => $lineItem->getDiscountPercent(),
                'tax_rate' => $lineItem->getTaxRate(),
                'line_total' => $lineItem->calculateSubtotal()->amount(),
                'display_order' => $lineItem->getDisplayOrder() ?: $index,
            ]);
        }
    }

    /**
     * Sync payments for an invoice.
     *
     * @param InvoicePayment[] $payments
     */
    private function syncPayments(InvoiceModel $model, array $payments): void
    {
        // Delete existing payments
        $model->payments()->delete();

        // Create new payments
        foreach ($payments as $payment) {
            InvoicePaymentModel::create([
                'invoice_id' => $model->id,
                'amount' => $payment->getAmount()->amount(),
                'payment_method' => $payment->getPaymentMethod()?->value,
                'payment_date' => $payment->getPaymentDate()->format('Y-m-d'),
                'reference' => $payment->getReference(),
                'notes' => $payment->getNotes(),
                'created_by' => $payment->getCreatedBy(),
            ]);
        }
    }

    /**
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(InvoiceModel $model): Invoice
    {
        $currency = $model->currency ?? 'USD';

        $invoice = Invoice::reconstitute(
            id: $model->id,
            invoiceNumber: $model->invoice_number,
            quoteId: $model->quote_id,
            dealId: $model->deal_id,
            contactId: $model->contact_id,
            companyId: $model->company_id,
            status: InvoiceStatus::from($model->status),
            title: $model->title,
            subtotal: new Money((float) $model->subtotal, $currency),
            discountAmount: new Money((float) $model->discount_amount, $currency),
            taxAmount: new Money((float) $model->tax_amount, $currency),
            total: new Money((float) $model->total, $currency),
            amountPaid: new Money((float) $model->amount_paid, $currency),
            balanceDue: new Money((float) $model->balance_due, $currency),
            currency: $currency,
            issueDate: new DateTimeImmutable($model->issue_date->format('Y-m-d')),
            dueDate: new DateTimeImmutable($model->due_date->format('Y-m-d')),
            paymentTerms: PaymentTerms::from($model->payment_terms ?? 'net_30'),
            notes: $model->notes,
            internalNotes: $model->internal_notes,
            templateId: $model->template_id,
            viewToken: $model->view_token,
            sentAt: $model->sent_at
                ? new DateTimeImmutable($model->sent_at->format('Y-m-d H:i:s'))
                : null,
            sentToEmail: $model->sent_to_email,
            viewedAt: $model->viewed_at
                ? new DateTimeImmutable($model->viewed_at->format('Y-m-d H:i:s'))
                : null,
            paidAt: $model->paid_at
                ? new DateTimeImmutable($model->paid_at->format('Y-m-d H:i:s'))
                : null,
            createdBy: $model->created_by,
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->format('Y-m-d H:i:s'))
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->format('Y-m-d H:i:s'))
                : null,
        );

        // Set line items
        $lineItems = $model->lineItems->map(function ($item) use ($model, $currency) {
            return InvoiceLineItem::reconstitute(
                id: $item->id,
                invoiceId: $model->id,
                productId: $item->product_id,
                description: $item->name ?? $item->description ?? '',
                quantity: (float) $item->quantity,
                unitPrice: new Money((float) $item->unit_price, $currency),
                discountPercent: (float) $item->discount_percent,
                taxRate: (float) $item->tax_rate,
                displayOrder: $item->display_order ?? 0,
            );
        })->all();

        $invoice->setLineItems($lineItems);

        // Set payments
        $payments = $model->payments->map(function ($payment) use ($model, $currency) {
            return InvoicePayment::reconstitute(
                id: $payment->id,
                invoiceId: $model->id,
                amount: new Money((float) $payment->amount, $currency),
                paymentDate: new DateTimeImmutable($payment->payment_date->format('Y-m-d')),
                paymentMethod: $payment->payment_method ? PaymentMethod::tryFrom($payment->payment_method) : null,
                reference: $payment->reference,
                notes: $payment->notes,
                createdBy: $payment->created_by ?? null,
                createdAt: $payment->created_at
                    ? new DateTimeImmutable($payment->created_at->format('Y-m-d H:i:s'))
                    : null,
            );
        })->all();

        $invoice->setPayments($payments);

        return $invoice;
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Invoice $invoice): array
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
}
