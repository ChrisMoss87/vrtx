<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Database\Repositories\Billing;

use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\Entities\QuoteLineItem;
use App\Domain\Billing\Repositories\QuoteRepositoryInterface;
use App\Domain\Billing\ValueObjects\DiscountType;
use App\Domain\Billing\ValueObjects\Money;
use App\Domain\Billing\ValueObjects\QuoteStatus;
use App\Domain\Shared\ValueObjects\PaginatedResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query Builder implementation of the QuoteRepository.
 */
class DbQuoteRepository implements QuoteRepositoryInterface
{
    private const TABLE = 'quotes';
    private const TABLE_LINE_ITEMS = 'quote_line_items';

    public function findById(int $id): ?Quote
    {
        $row = DB::table(self::TABLE)->where('id', $id)->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByViewToken(string $viewToken): ?Quote
    {
        $row = DB::table(self::TABLE)
            ->where('view_token', $viewToken)
            ->first();

        if (!$row) {
            return null;
        }

        return $this->toDomainEntityWithRelations($row);
    }

    public function findByQuoteNumber(string $quoteNumber): ?Quote
    {
        $row = DB::table(self::TABLE)
            ->where('quote_number', $quoteNumber)
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

    public function findByStatus(QuoteStatus $status): array
    {
        $rows = DB::table(self::TABLE)
            ->where('status', $status->value)
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

    public function findByAssignedTo(int $userId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('assigned_to', $userId)
            ->orderByDesc('created_at')
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function findExpired(): array
    {
        $rows = DB::table(self::TABLE)
            ->where('valid_until', '<', now())
            ->whereNotIn('status', [QuoteStatus::ACCEPTED->value, QuoteStatus::REJECTED->value, QuoteStatus::EXPIRED->value])
            ->get();

        return $rows->map(fn($row) => $this->toDomainEntityWithRelations($row))->all();
    }

    public function save(Quote $quote): Quote
    {
        $data = $this->toRowData($quote);

        if ($quote->getId() !== null) {
            DB::table(self::TABLE)
                ->where('id', $quote->getId())
                ->update(array_merge($data, ['updated_at' => now()]));
            $id = $quote->getId();
        } else {
            $id = DB::table(self::TABLE)->insertGetId(
                array_merge($data, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Sync line items
        $this->syncLineItems($id, $quote->getLineItems());

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        // Delete line items first
        DB::table(self::TABLE_LINE_ITEMS)->where('quote_id', $id)->delete();

        return DB::table(self::TABLE)->where('id', $id)->delete() > 0;
    }

    public function quoteNumberExists(string $quoteNumber, ?int $excludeId = null): bool
    {
        $query = DB::table(self::TABLE)->where('quote_number', $quoteNumber);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
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

        if (!empty($filters['deal_id'])) {
            $query->where('deal_id', $filters['deal_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'ilike', "%{$search}%")
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

        if (!empty($filters['deal_id'])) {
            $query->where('deal_id', $filters['deal_id']);
        }

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['company_id'])) {
            $query->where('company_id', $filters['company_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return $rows->map(fn($row) => $this->toArrayWithRelations($row))->all();
    }

    /**
     * Sync line items for a quote.
     *
     * @param QuoteLineItem[] $lineItems
     */
    private function syncLineItems(int $quoteId, array $lineItems): void
    {
        // Delete existing line items
        DB::table(self::TABLE_LINE_ITEMS)->where('quote_id', $quoteId)->delete();

        // Create new line items
        foreach ($lineItems as $index => $lineItem) {
            DB::table(self::TABLE_LINE_ITEMS)->insert([
                'quote_id' => $quoteId,
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
     * Get line items for a quote.
     */
    private function getLineItemsForQuote(int $quoteId): array
    {
        return DB::table(self::TABLE_LINE_ITEMS)
            ->where('quote_id', $quoteId)
            ->orderBy('display_order')
            ->get()
            ->all();
    }

    /**
     * Convert a database row to a domain entity with relations.
     */
    private function toDomainEntityWithRelations(stdClass $row): Quote
    {
        $currency = $row->currency ?? 'USD';

        $quote = Quote::reconstitute(
            id: (int) $row->id,
            quoteNumber: $row->quote_number,
            dealId: $row->deal_id ? (int) $row->deal_id : null,
            contactId: $row->contact_id ? (int) $row->contact_id : null,
            companyId: $row->company_id ? (int) $row->company_id : null,
            status: QuoteStatus::from($row->status),
            title: $row->title,
            subtotal: new Money((float) $row->subtotal, $currency),
            discountAmount: new Money((float) $row->discount_amount, $currency),
            discountType: DiscountType::from($row->discount_type ?? 'fixed'),
            discountPercent: (float) $row->discount_percent,
            taxAmount: new Money((float) $row->tax_amount, $currency),
            total: new Money((float) $row->total, $currency),
            currency: $currency,
            validUntil: $row->valid_until ? new DateTimeImmutable($row->valid_until) : null,
            terms: $row->terms,
            notes: $row->notes,
            internalNotes: $row->internal_notes,
            templateId: $row->template_id ? (int) $row->template_id : null,
            version: (int) ($row->version ?? 1),
            viewToken: $row->view_token,
            acceptedAt: $row->accepted_at ? new DateTimeImmutable($row->accepted_at) : null,
            acceptedBy: $row->accepted_by,
            acceptedSignature: $row->accepted_signature,
            acceptedIp: $row->accepted_ip,
            rejectedAt: $row->rejected_at ? new DateTimeImmutable($row->rejected_at) : null,
            rejectedBy: $row->rejected_by,
            rejectionReason: $row->rejection_reason,
            viewedAt: $row->viewed_at ? new DateTimeImmutable($row->viewed_at) : null,
            sentAt: $row->sent_at ? new DateTimeImmutable($row->sent_at) : null,
            sentToEmail: $row->sent_to_email,
            createdBy: $row->created_by ? (int) $row->created_by : null,
            assignedTo: $row->assigned_to ? (int) $row->assigned_to : null,
            createdAt: $row->created_at ? new DateTimeImmutable($row->created_at) : null,
            updatedAt: $row->updated_at ? new DateTimeImmutable($row->updated_at) : null,
        );

        // Load line items
        $lineItemRows = $this->getLineItemsForQuote((int) $row->id);
        $lineItems = array_map(function ($item) use ($row, $currency) {
            return QuoteLineItem::reconstitute(
                id: (int) $item->id,
                quoteId: (int) $row->id,
                productId: $item->product_id ? (int) $item->product_id : null,
                description: $item->name ?? $item->description ?? '',
                quantity: (float) $item->quantity,
                unitPrice: new Money((float) $item->unit_price, $currency),
                discountPercent: (float) $item->discount_percent,
                taxRate: (float) $item->tax_rate,
                displayOrder: (int) ($item->display_order ?? 0),
            );
        }, $lineItemRows);

        $quote->setLineItems($lineItems);

        return $quote;
    }

    /**
     * Convert a domain entity to row data.
     *
     * @return array<string, mixed>
     */
    private function toRowData(Quote $quote): array
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

    /**
     * Convert a database row to array with relations.
     *
     * @return array<string, mixed>
     */
    private function toArrayWithRelations(stdClass $row): array
    {
        $lineItemRows = $this->getLineItemsForQuote((int) $row->id);

        return [
            'id' => $row->id,
            'quote_number' => $row->quote_number,
            'deal_id' => $row->deal_id,
            'contact_id' => $row->contact_id,
            'company_id' => $row->company_id,
            'status' => $row->status,
            'title' => $row->title,
            'subtotal' => (float) $row->subtotal,
            'discount_amount' => (float) $row->discount_amount,
            'discount_type' => $row->discount_type,
            'discount_percent' => (float) $row->discount_percent,
            'tax_amount' => (float) $row->tax_amount,
            'total' => (float) $row->total,
            'currency' => $row->currency,
            'valid_until' => $row->valid_until,
            'terms' => $row->terms,
            'notes' => $row->notes,
            'internal_notes' => $row->internal_notes,
            'template_id' => $row->template_id,
            'version' => $row->version,
            'view_token' => $row->view_token,
            'accepted_at' => $row->accepted_at,
            'accepted_by' => $row->accepted_by,
            'accepted_signature' => $row->accepted_signature,
            'accepted_ip' => $row->accepted_ip,
            'rejected_at' => $row->rejected_at,
            'rejected_by' => $row->rejected_by,
            'rejection_reason' => $row->rejection_reason,
            'viewed_at' => $row->viewed_at,
            'sent_at' => $row->sent_at,
            'sent_to_email' => $row->sent_to_email,
            'created_by' => $row->created_by,
            'assigned_to' => $row->assigned_to,
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
        ];
    }
}
