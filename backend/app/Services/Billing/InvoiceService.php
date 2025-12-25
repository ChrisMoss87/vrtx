<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function create(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId) {
            $settings = BillingSetting::getSettings();

            $invoice = DB::table('invoices')->insertGetId([
                'invoice_number' => $settings->generateInvoiceNumber(),
                'quote_id' => $data['quote_id'] ?? null,
                'deal_id' => $data['deal_id'] ?? null,
                'contact_id' => $data['contact_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'title' => $data['title'] ?? null,
                'currency' => $data['currency'] ?? $settings->currency,
                'issue_date' => $data['issue_date'] ?? now(),
                'due_date' => $data['due_date'] ?? $this->calculateDueDate($data['payment_terms'] ?? $settings->default_payment_terms),
                'payment_terms' => $data['payment_terms'] ?? $settings->default_payment_terms,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'created_by' => $userId,
            ]);

            if (!empty($data['line_items'])) {
                $this->syncLineItems($invoice, $data['line_items']);
            }

            return $invoice->fresh(['lineItems', 'createdBy']);
        });
    }

    public function createFromQuote(Quote $quote, int $userId): Invoice
    {
        if ($quote->status !== Quote::STATUS_ACCEPTED) {
            throw new \Exception('Only accepted quotes can be converted to invoices.');
        }

        if ($quote->invoice) {
            throw new \Exception('This quote has already been converted to an invoice.');
        }

        return DB::transaction(function () use ($quote, $userId) {
            $settings = BillingSetting::getSettings();

            $invoice = DB::table('invoices')->insertGetId([
                'invoice_number' => $settings->generateInvoiceNumber(),
                'quote_id' => $quote->id,
                'deal_id' => $quote->deal_id,
                'contact_id' => $quote->contact_id,
                'company_id' => $quote->company_id,
                'title' => $quote->title,
                'currency' => $quote->currency,
                'issue_date' => now(),
                'due_date' => $this->calculateDueDate($settings->default_payment_terms),
                'payment_terms' => $settings->default_payment_terms,
                'discount_amount' => $quote->discount_amount,
                'notes' => $quote->notes,
                'template_id' => $quote->template_id,
                'created_by' => $userId,
            ]);

            // Copy line items from quote
            foreach ($quote->lineItems as $quoteItem) {
                DB::table('invoice_line_items')->insertGetId([
                    'invoice_id' => $invoice->id,
                    'product_id' => $quoteItem->product_id,
                    'description' => $quoteItem->description,
                    'quantity' => $quoteItem->quantity,
                    'unit_price' => $quoteItem->unit_price,
                    'discount_percent' => $quoteItem->discount_percent,
                    'tax_rate' => $quoteItem->tax_rate,
                    'display_order' => $quoteItem->display_order,
                ]);
            }

            $invoice->recalculateTotals();

            return $invoice->fresh(['lineItems', 'quote']);
        });
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'deal_id' => $data['deal_id'] ?? $invoice->deal_id,
                'contact_id' => $data['contact_id'] ?? $invoice->contact_id,
                'company_id' => $data['company_id'] ?? $invoice->company_id,
                'title' => $data['title'] ?? $invoice->title,
                'currency' => $data['currency'] ?? $invoice->currency,
                'issue_date' => $data['issue_date'] ?? $invoice->issue_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'payment_terms' => $data['payment_terms'] ?? $invoice->payment_terms,
                'discount_amount' => $data['discount_amount'] ?? $invoice->discount_amount,
                'notes' => $data['notes'] ?? $invoice->notes,
                'internal_notes' => $data['internal_notes'] ?? $invoice->internal_notes,
                'template_id' => $data['template_id'] ?? $invoice->template_id,
            ]);

            if (isset($data['line_items'])) {
                $this->syncLineItems($invoice, $data['line_items']);
            }

            return $invoice->fresh(['lineItems', 'payments', 'createdBy']);
        });
    }

    protected function syncLineItems(Invoice $invoice, array $items): void
    {
        // Delete existing items
        $invoice->lineItems()->delete();

        // Create new items
        foreach ($items as $index => $item) {
            DB::table('invoice_line_items')->insertGetId([
                'invoice_id' => $invoice->id,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'] ?? 1,
                'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'display_order' => $item['display_order'] ?? $index,
            ]);
        }

        $invoice->recalculateTotals();
    }

    public function send(Invoice $invoice, string $toEmail, ?string $message = null): void
    {
        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'sent_to_email' => $toEmail,
        ]);

        // Send email (simplified)
        // Mail::to($toEmail)->send(new InvoiceMail($invoice, $message));
    }

    public function recordPayment(Invoice $invoice, array $data, int $userId): InvoicePayment
    {
        if (!$invoice->canRecordPayment()) {
            throw new \Exception('Cannot record payment for this invoice.');
        }

        $payment = DB::table('invoice_payments')->insertGetId([
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'],
            'payment_date' => $data['payment_date'] ?? now(),
            'payment_method' => $data['payment_method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
        ]);

        return $payment;
    }

    public function cancel(Invoice $invoice): void
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw new \Exception('Cannot cancel a paid invoice.');
        }

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
    }

    protected function calculateDueDate(string $paymentTerms): \Carbon\Carbon
    {
        $days = match ($paymentTerms) {
            'due_on_receipt' => 0,
            'net_7' => 7,
            'net_15' => 15,
            'net_30' => 30,
            'net_45' => 45,
            'net_60' => 60,
            'net_90' => 90,
            default => 30,
        };

        return now()->addDays($days);
    }

    public function checkOverdueInvoices(): int
    {
        $count = Invoice::whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_VIEWED, Invoice::STATUS_PARTIAL])
            ->where('due_date', '<', now()->startOfDay())
            ->update(['status' => Invoice::STATUS_OVERDUE]);

        return $count;
    }

    public function getStats(): array
    {
        return [
            'total_invoices' => DB::table('invoices')->count(),
            'draft' => Invoice::status(Invoice::STATUS_DRAFT)->count(),
            'sent' => Invoice::status(Invoice::STATUS_SENT)->count(),
            'paid' => Invoice::status(Invoice::STATUS_PAID)->count(),
            'overdue' => Invoice::status(Invoice::STATUS_OVERDUE)->count(),
            'total_outstanding' => Invoice::unpaid()->sum('balance_due'),
            'total_overdue' => Invoice::overdue()->sum('balance_due'),
        ];
    }
}
