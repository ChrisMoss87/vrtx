<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingSetting;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use Illuminate\Support\Facades\View;

class PdfGeneratorService
{
    protected array $currencySymbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥',
        'CHF' => 'CHF ',
        'CNY' => '¥',
        'INR' => '₹',
        'BRL' => 'R$',
    ];

    /**
     * Generate HTML for a quote PDF.
     */
    public function generateQuoteHtml(Quote $quote): string
    {
        $quote->load(['lineItems.product', 'template', 'createdBy', 'assignedTo', 'contact']);
        $settings = BillingSetting::getSettings();

        return View::make('pdf.quote', [
            'quote' => $quote,
            'settings' => $settings,
            'currencySymbol' => $this->getCurrencySymbol($quote->currency),
        ])->render();
    }

    /**
     * Generate HTML for an invoice PDF.
     */
    public function generateInvoiceHtml(Invoice $invoice): string
    {
        $invoice->load(['lineItems.product', 'payments', 'template', 'createdBy', 'quote', 'contact']);
        $settings = BillingSetting::getSettings();

        return View::make('pdf.invoice', [
            'invoice' => $invoice,
            'settings' => $settings,
            'currencySymbol' => $this->getCurrencySymbol($invoice->currency),
        ])->render();
    }

    /**
     * Generate PDF data for a quote.
     * This returns the data structure needed to render a PDF.
     * In production, this would use a library like DomPDF, TCPDF, or a service like Browsershot.
     */
    public function generateQuotePdf(Quote $quote): array
    {
        $quote->load(['lineItems.product', 'template', 'createdBy', 'assignedTo']);
        $settings = BillingSetting::getSettings();
        $template = $quote->template ?? QuoteTemplate::getDefault();

        return [
            'type' => 'quote',
            'document_number' => $quote->quote_number,
            'title' => $quote->title ?? 'Quote',
            'status' => $quote->status,
            'date' => $quote->created_at->format('M d, Y'),
            'valid_until' => $quote->valid_until?->format('M d, Y'),
            'company_info' => $settings->company_info ?? [],
            'customer' => [
                'contact_id' => $quote->contact_id,
                'company_id' => $quote->company_id,
            ],
            'line_items' => $quote->lineItems->map(function ($item) {
                return [
                    'description' => $item->description,
                    'quantity' => number_format((float) $item->quantity, 2),
                    'unit_price' => $this->formatCurrency($item->unit_price, 'USD'),
                    'discount_percent' => $item->discount_percent > 0 ? "{$item->discount_percent}%" : null,
                    'tax_rate' => $item->tax_rate > 0 ? "{$item->tax_rate}%" : null,
                    'line_total' => $this->formatCurrency($item->line_total, 'USD'),
                ];
            })->toArray(),
            'subtotal' => $this->formatCurrency($quote->subtotal, $quote->currency),
            'discount' => $quote->discount_amount > 0 ? [
                'type' => $quote->discount_type,
                'amount' => $this->formatCurrency($quote->discount_amount, $quote->currency),
                'percent' => $quote->discount_percent > 0 ? "{$quote->discount_percent}%" : null,
            ] : null,
            'tax_amount' => $this->formatCurrency($quote->tax_amount, $quote->currency),
            'total' => $this->formatCurrency($quote->total, $quote->currency),
            'currency' => $quote->currency,
            'terms' => $quote->terms,
            'notes' => $quote->notes,
            'template' => $template ? [
                'header_html' => $template->header_html,
                'footer_html' => $template->footer_html,
                'styling' => $template->styling,
            ] : null,
            'created_by' => $quote->createdBy ? [
                'name' => $quote->createdBy->name,
                'email' => $quote->createdBy->email,
            ] : null,
            'html' => $this->generateQuoteHtml($quote),
        ];
    }

    /**
     * Generate PDF data for an invoice.
     */
    public function generateInvoicePdf(Invoice $invoice): array
    {
        $invoice->load(['lineItems.product', 'payments', 'template', 'createdBy', 'quote']);
        $settings = BillingSetting::getSettings();
        $template = $invoice->template ?? QuoteTemplate::getDefault();

        return [
            'type' => 'invoice',
            'document_number' => $invoice->invoice_number,
            'title' => $invoice->title ?? 'Invoice',
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date->format('M d, Y'),
            'due_date' => $invoice->due_date->format('M d, Y'),
            'payment_terms' => $invoice->payment_terms,
            'company_info' => $settings->company_info ?? [],
            'customer' => [
                'contact_id' => $invoice->contact_id,
                'company_id' => $invoice->company_id,
            ],
            'quote_reference' => $invoice->quote ? [
                'quote_number' => $invoice->quote->quote_number,
            ] : null,
            'line_items' => $invoice->lineItems->map(function ($item) {
                return [
                    'description' => $item->description,
                    'quantity' => number_format((float) $item->quantity, 2),
                    'unit_price' => $this->formatCurrency($item->unit_price, 'USD'),
                    'discount_percent' => $item->discount_percent > 0 ? "{$item->discount_percent}%" : null,
                    'tax_rate' => $item->tax_rate > 0 ? "{$item->tax_rate}%" : null,
                    'line_total' => $this->formatCurrency($item->line_total, 'USD'),
                ];
            })->toArray(),
            'subtotal' => $this->formatCurrency($invoice->subtotal, $invoice->currency),
            'discount_amount' => $invoice->discount_amount > 0
                ? $this->formatCurrency($invoice->discount_amount, $invoice->currency)
                : null,
            'tax_amount' => $this->formatCurrency($invoice->tax_amount, $invoice->currency),
            'total' => $this->formatCurrency($invoice->total, $invoice->currency),
            'amount_paid' => $this->formatCurrency($invoice->amount_paid, $invoice->currency),
            'balance_due' => $this->formatCurrency($invoice->balance_due, $invoice->currency),
            'currency' => $invoice->currency,
            'payments' => $invoice->payments->map(function ($payment) use ($invoice) {
                return [
                    'date' => $payment->payment_date->format('M d, Y'),
                    'amount' => $this->formatCurrency($payment->amount, $invoice->currency),
                    'method' => $payment->method_label,
                    'reference' => $payment->reference,
                ];
            })->toArray(),
            'notes' => $invoice->notes,
            'template' => $template ? [
                'header_html' => $template->header_html,
                'footer_html' => $template->footer_html,
                'styling' => $template->styling,
            ] : null,
            'created_by' => $invoice->createdBy ? [
                'name' => $invoice->createdBy->name,
                'email' => $invoice->createdBy->email,
            ] : null,
            'html' => $this->generateInvoiceHtml($invoice),
        ];
    }

    /**
     * Get currency symbol.
     */
    public function getCurrencySymbol(string $currency): string
    {
        return $this->currencySymbols[$currency] ?? $currency . ' ';
    }

    /**
     * Format currency value.
     */
    protected function formatCurrency(float|string|null $amount, string $currency): string
    {
        if ($amount === null) {
            $amount = 0;
        }

        $symbol = $this->getCurrencySymbol($currency);
        return $symbol . number_format((float) $amount, 2);
    }

    /**
     * Generate actual PDF binary using DomPDF.
     * Requires barryvdh/laravel-dompdf package.
     */
    public function renderQuoteToPdf(Quote $quote): string
    {
        $html = $this->generateQuoteHtml($quote);

        // Check if DomPDF is available
        if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'portrait');
            return $pdf->output();
        }

        throw new \Exception('PDF rendering requires barryvdh/laravel-dompdf. Install with: composer require barryvdh/laravel-dompdf');
    }

    /**
     * Generate actual PDF binary for invoice using DomPDF.
     * Requires barryvdh/laravel-dompdf package.
     */
    public function renderInvoiceToPdf(Invoice $invoice): string
    {
        $html = $this->generateInvoiceHtml($invoice);

        // Check if DomPDF is available
        if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'portrait');
            return $pdf->output();
        }

        throw new \Exception('PDF rendering requires barryvdh/laravel-dompdf. Install with: composer require barryvdh/laravel-dompdf');
    }

    /**
     * Stream PDF download for quote.
     */
    public function streamQuotePdf(Quote $quote): mixed
    {
        $html = $this->generateQuoteHtml($quote);

        if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'portrait');
            return $pdf->download($quote->quote_number . '.pdf');
        }

        // Fallback: return HTML for browser printing
        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * Stream PDF download for invoice.
     */
    public function streamInvoicePdf(Invoice $invoice): mixed
    {
        $html = $this->generateInvoiceHtml($invoice);

        if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'portrait');
            return $pdf->download($invoice->invoice_number . '.pdf');
        }

        // Fallback: return HTML for browser printing
        return response($html)->header('Content-Type', 'text/html');
    }
}
