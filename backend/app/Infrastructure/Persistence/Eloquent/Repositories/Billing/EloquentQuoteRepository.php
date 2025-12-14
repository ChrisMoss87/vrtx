<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories\Billing;

use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\Entities\QuoteLineItem;
use App\Domain\Billing\Repositories\QuoteRepositoryInterface;
use App\Domain\Billing\ValueObjects\DiscountType;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\QuoteStatus;
use App\Models\Quote as QuoteModel;
use App\Models\QuoteLineItem as QuoteLineItemModel;
use DateTimeImmutable;

/**
 * Eloquent implementation of the QuoteRepository.
 */
class EloquentQuoteRepository implements QuoteRepositoryInterface
{
    public function findById(int $id): ?Quote
    {
        $model = QuoteModel::with('lineItems')->find($id);

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByViewToken(string $viewToken): ?Quote
    {
        $model = QuoteModel::with('lineItems')
            ->where('view_token', $viewToken)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findByQuoteNumber(string $quoteNumber): ?Quote
    {
        $model = QuoteModel::with('lineItems')
            ->where('quote_number', $quoteNumber)
            ->first();

        if (!$model) {
            return null;
        }

        return $this->toDomainEntity($model);
    }

    public function findAll(): array
    {
        $models = QuoteModel::with('lineItems')
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByStatus(QuoteStatus $status): array
    {
        $models = QuoteModel::with('lineItems')
            ->where('status', $status->value)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByDealId(int $dealId): array
    {
        $models = QuoteModel::with('lineItems')
            ->where('deal_id', $dealId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByContactId(int $contactId): array
    {
        $models = QuoteModel::with('lineItems')
            ->where('contact_id', $contactId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByCompanyId(int $companyId): array
    {
        $models = QuoteModel::with('lineItems')
            ->where('company_id', $companyId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findByAssignedTo(int $userId): array
    {
        $models = QuoteModel::with('lineItems')
            ->where('assigned_to', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function findExpired(): array
    {
        $models = QuoteModel::with('lineItems')
            ->where('valid_until', '<', now())
            ->whereNotIn('status', [QuoteStatus::ACCEPTED->value, QuoteStatus::REJECTED->value, QuoteStatus::EXPIRED->value])
            ->get();

        return $models->map(fn($m) => $this->toDomainEntity($m))->all();
    }

    public function save(Quote $quote): Quote
    {
        $data = $this->toModelData($quote);

        if ($quote->getId() !== null) {
            $model = QuoteModel::findOrFail($quote->getId());
            $model->update($data);
        } else {
            $model = QuoteModel::create($data);
        }

        // Sync line items
        $this->syncLineItems($model, $quote->getLineItems());

        return $this->toDomainEntity($model->fresh(['lineItems']));
    }

    public function delete(int $id): bool
    {
        $model = QuoteModel::find($id);

        if (!$model) {
            return false;
        }

        // Delete line items first
        $model->lineItems()->delete();

        return $model->delete() ?? false;
    }

    public function quoteNumberExists(string $quoteNumber, ?int $excludeId = null): bool
    {
        $query = QuoteModel::where('quote_number', $quoteNumber);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Sync line items for a quote.
     *
     * @param QuoteLineItem[] $lineItems
     */
    private function syncLineItems(QuoteModel $model, array $lineItems): void
    {
        // Delete existing line items
        $model->lineItems()->delete();

        // Create new line items
        foreach ($lineItems as $index => $lineItem) {
            QuoteLineItemModel::create([
                'quote_id' => $model->id,
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
     * Convert an Eloquent model to a domain entity.
     */
    private function toDomainEntity(QuoteModel $model): Quote
    {
        $currency = $model->currency ?? 'USD';

        $quote = Quote::reconstitute(
            id: $model->id,
            quoteNumber: $model->quote_number,
            dealId: $model->deal_id,
            contactId: $model->contact_id,
            companyId: $model->company_id,
            status: QuoteStatus::from($model->status),
            title: $model->title,
            subtotal: new Money((float) $model->subtotal, $currency),
            discountAmount: new Money((float) $model->discount_amount, $currency),
            discountType: DiscountType::from($model->discount_type ?? 'fixed'),
            discountPercent: (float) $model->discount_percent,
            taxAmount: new Money((float) $model->tax_amount, $currency),
            total: new Money((float) $model->total, $currency),
            currency: $currency,
            validUntil: $model->valid_until
                ? new DateTimeImmutable($model->valid_until->format('Y-m-d H:i:s'))
                : null,
            terms: $model->terms,
            notes: $model->notes,
            internalNotes: $model->internal_notes,
            templateId: $model->template_id,
            version: $model->version ?? 1,
            viewToken: $model->view_token,
            acceptedAt: $model->accepted_at
                ? new DateTimeImmutable($model->accepted_at->format('Y-m-d H:i:s'))
                : null,
            acceptedBy: $model->accepted_by,
            acceptedSignature: $model->accepted_signature,
            acceptedIp: $model->accepted_ip,
            rejectedAt: $model->rejected_at
                ? new DateTimeImmutable($model->rejected_at->format('Y-m-d H:i:s'))
                : null,
            rejectedBy: $model->rejected_by,
            rejectionReason: $model->rejection_reason,
            viewedAt: $model->viewed_at
                ? new DateTimeImmutable($model->viewed_at->format('Y-m-d H:i:s'))
                : null,
            sentAt: $model->sent_at
                ? new DateTimeImmutable($model->sent_at->format('Y-m-d H:i:s'))
                : null,
            sentToEmail: $model->sent_to_email,
            createdBy: $model->created_by,
            assignedTo: $model->assigned_to,
            createdAt: $model->created_at
                ? new DateTimeImmutable($model->created_at->format('Y-m-d H:i:s'))
                : null,
            updatedAt: $model->updated_at
                ? new DateTimeImmutable($model->updated_at->format('Y-m-d H:i:s'))
                : null,
        );

        // Set line items
        $lineItems = $model->lineItems->map(function ($item) use ($model, $currency) {
            return QuoteLineItem::reconstitute(
                id: $item->id,
                quoteId: $model->id,
                productId: $item->product_id,
                description: $item->name ?? $item->description ?? '',
                quantity: (float) $item->quantity,
                unitPrice: new Money((float) $item->unit_price, $currency),
                discountPercent: (float) $item->discount_percent,
                taxRate: (float) $item->tax_rate,
                displayOrder: $item->display_order ?? 0,
            );
        })->all();

        $quote->setLineItems($lineItems);

        return $quote;
    }

    /**
     * Convert a domain entity to model data.
     *
     * @return array<string, mixed>
     */
    private function toModelData(Quote $quote): array
    {
        return [
            'quote_number' => $quote->getQuoteNumber(),
            'deal_id' => $quote->getDealId(),
            'contact_id' => $quote->getContactId(),
            'company_id' => $quote->getCompanyId(),
            'status' => $quote->getStatus()->value,
            'title' => $quote->getTitle(),
            'subtotal' => $quote->getSubtotal()->amount(),
            'discount_amount' => $quote->getDiscountAmount()->amount(),
            'discount_type' => $quote->getDiscountType()->value,
            'discount_percent' => $quote->getDiscountPercent(),
            'tax_amount' => $quote->getTaxAmount()->amount(),
            'total' => $quote->getTotal()->amount(),
            'currency' => $quote->getCurrency(),
            'valid_until' => $quote->getValidUntil()?->format('Y-m-d'),
            'terms' => $quote->getTerms(),
            'notes' => $quote->getNotes(),
            'internal_notes' => $quote->getInternalNotes(),
            'template_id' => $quote->getTemplateId(),
            'version' => $quote->getVersion(),
            'view_token' => $quote->getViewToken(),
            'accepted_at' => $quote->getAcceptedAt()?->format('Y-m-d H:i:s'),
            'accepted_by' => $quote->getAcceptedBy(),
            'accepted_signature' => $quote->getAcceptedSignature(),
            'accepted_ip' => $quote->getAcceptedIp(),
            'rejected_at' => $quote->getRejectedAt()?->format('Y-m-d H:i:s'),
            'rejected_by' => $quote->getRejectedBy(),
            'rejection_reason' => $quote->getRejectionReason(),
            'viewed_at' => $quote->getViewedAt()?->format('Y-m-d H:i:s'),
            'sent_at' => $quote->getSentAt()?->format('Y-m-d H:i:s'),
            'sent_to_email' => $quote->getSentToEmail(),
            'created_by' => $quote->getCreatedBy(),
            'assigned_to' => $quote->getAssignedTo(),
        ];
    }
}
