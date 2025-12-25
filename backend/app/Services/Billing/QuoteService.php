<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class QuoteService
{
    public function create(array $data, int $userId): Quote
    {
        return DB::transaction(function () use ($data, $userId) {
            $settings = BillingSetting::getSettings();

            $quote = DB::table('quotes')->insertGetId([
                'quote_number' => $settings->generateQuoteNumber(),
                'deal_id' => $data['deal_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'title' => $data['title'] ?? null,
                'currency' => $data['currency'] ?? $settings->currency,
                'valid_until' => $data['valid_until'] ?? now()->addDays($settings->quote_validity_days),
                'terms' => $data['terms'] ?? $settings->default_terms,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'discount_type' => $data['discount_type'] ?? 'fixed',
                'discount_amount' => $data['discount_amount'] ?? 0,
                'discount_percent' => $data['discount_percent'] ?? 0,
                'assigned_to' => $data['assigned_to'] ?? $userId,
                'created_by' => $userId,
            ]);

            if (!empty($data['line_items'])) {
                $this->syncLineItems($quote, $data['line_items']);
            }

            return $quote->fresh(['lineItems', 'createdBy', 'assignedTo']);
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data) {
            // Create version snapshot before updating
            if ($quote->status !== Quote::STATUS_DRAFT) {
                $this->createVersionSnapshot($quote);
            }

            $quote->update([
                'deal_id' => $data['deal_id'] ?? $quote->deal_id,
                'contact_id' => $data['contact_id'] ?? $quote->contact_id,
                'company_id' => $data['company_id'] ?? $quote->company_id,
                'title' => $data['title'] ?? $quote->title,
                'currency' => $data['currency'] ?? $quote->currency,
                'valid_until' => $data['valid_until'] ?? $quote->valid_until,
                'terms' => $data['terms'] ?? $quote->terms,
                'notes' => $data['notes'] ?? $quote->notes,
                'internal_notes' => $data['internal_notes'] ?? $quote->internal_notes,
                'template_id' => $data['template_id'] ?? $quote->template_id,
                'discount_type' => $data['discount_type'] ?? $quote->discount_type,
                'discount_amount' => $data['discount_amount'] ?? $quote->discount_amount,
                'discount_percent' => $data['discount_percent'] ?? $quote->discount_percent,
                'assigned_to' => $data['assigned_to'] ?? $quote->assigned_to,
            ]);

            if (isset($data['line_items'])) {
                $this->syncLineItems($quote, $data['line_items']);
            }

            return $quote->fresh(['lineItems', 'createdBy', 'assignedTo']);
        });
    }

    protected function syncLineItems(Quote $quote, array $items): void
    {
        // Delete existing items
        $quote->lineItems()->delete();

        // Create new items
        foreach ($items as $index => $item) {
            DB::table('quote_line_items')->insertGetId([
                'quote_id' => $quote->id,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'display_order' => $item['display_order'] ?? $index,
            ]);
        }

        $quote->recalculateTotals();
    }

    protected function createVersionSnapshot(Quote $quote, ?string $notes = null): QuoteVersion
    {
        $snapshot = [
            'title' => $quote->title,
            'subtotal' => $quote->subtotal,
            'discount_amount' => $quote->discount_amount,
            'tax_amount' => $quote->tax_amount,
            'total' => $quote->total,
            'terms' => $quote->terms,
            'notes' => $quote->notes,
            'valid_until' => $quote->valid_until?->toDateString(),
            'line_items' => $quote->lineItems->map(function ($item) {
                return [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate' => $item->tax_rate,
                    'line_total' => $item->line_total,
                ];
            })->toArray(),
        ];

        $version = DB::table('quote_versions')->insertGetId([
            'quote_id' => $quote->id,
            'version' => $quote->version,
            'snapshot' => $snapshot,
            'change_notes' => $notes,
            'created_by' => auth()->id(),
        ]);

        $quote->increment('version');

        return $version;
    }

    public function send(Quote $quote, string $toEmail, ?string $message = null): void
    {
        // Mark quote as sent
        $quote->update([
            'status' => Quote::STATUS_SENT,
            'sent_at' => now(),
            'sent_to_email' => $toEmail,
        ]);

        // Send email (simplified - would use a proper Mailable in production)
        // Mail::to($toEmail)->send(new QuoteMail($quote, $message));
    }

    public function duplicate(Quote $quote, int $userId): Quote
    {
        return DB::transaction(function () use ($quote, $userId) {
            $settings = BillingSetting::getSettings();

            $newQuote = DB::table('quotes')->insertGetId([
                'quote_number' => $settings->generateQuoteNumber(),
                'deal_id' => $quote->deal_id,
                'contact_id' => $quote->contact_id,
                'company_id' => $quote->company_id,
                'title' => $quote->title . ' (Copy)',
                'currency' => $quote->currency,
                'valid_until' => now()->addDays($settings->quote_validity_days),
                'terms' => $quote->terms,
                'notes' => $quote->notes,
                'template_id' => $quote->template_id,
                'discount_type' => $quote->discount_type,
                'discount_amount' => $quote->discount_amount,
                'discount_percent' => $quote->discount_percent,
                'assigned_to' => $userId,
                'created_by' => $userId,
            ]);

            // Copy line items
            foreach ($quote->lineItems as $item) {
                DB::table('quote_line_items')->insertGetId([
                    'quote_id' => $newQuote->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'tax_rate' => $item->tax_rate,
                    'display_order' => $item->display_order,
                ]);
            }

            $newQuote->recalculateTotals();

            return $newQuote->fresh(['lineItems']);
        });
    }

    public function accept(Quote $quote, string $acceptedBy, ?string $signature = null, ?string $ip = null): void
    {
        if (!$quote->canBeAccepted()) {
            throw new \Exception('This quote cannot be accepted.');
        }

        $quote->accept($acceptedBy, $signature, $ip);
    }

    public function reject(Quote $quote, string $rejectedBy, ?string $reason = null): void
    {
        if (!$quote->canBeAccepted()) {
            throw new \Exception('This quote cannot be rejected.');
        }

        $quote->reject($rejectedBy, $reason);
    }

    public function checkExpiredQuotes(): int
    {
        $count = Quote::whereIn('status', [Quote::STATUS_DRAFT, Quote::STATUS_SENT, Quote::STATUS_VIEWED])
            ->where('valid_until', '<', now()->startOfDay())
            ->update(['status' => Quote::STATUS_EXPIRED]);

        return $count;
    }
}
