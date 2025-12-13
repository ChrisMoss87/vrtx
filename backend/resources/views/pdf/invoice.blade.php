<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_number }} - Invoice</title>
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }

        .company-info {
            flex: 1;
        }

        .company-name {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .company-details {
            color: #64748b;
            font-size: 11px;
            line-height: 1.6;
        }

        .document-info {
            text-align: right;
        }

        .document-title {
            font-size: 32px;
            font-weight: 700;
            color: #10b981;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 12px;
        }

        .document-number {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .document-date {
            font-size: 11px;
            color: #64748b;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .status-draft { background: #f1f5f9; color: #475569; }
        .status-sent { background: #dbeafe; color: #1d4ed8; }
        .status-viewed { background: #ede9fe; color: #6d28d9; }
        .status-paid { background: #dcfce7; color: #15803d; }
        .status-partial { background: #fef3c7; color: #b45309; }
        .status-overdue { background: #fee2e2; color: #dc2626; }
        .status-cancelled { background: #f1f5f9; color: #64748b; text-decoration: line-through; }

        /* Customer & Invoice Details */
        .details-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            gap: 40px;
        }

        .details-box {
            flex: 1;
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
        }

        .details-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .details-content {
            font-size: 12px;
            color: #1e293b;
            line-height: 1.6;
        }

        .details-content strong {
            font-weight: 600;
        }

        .due-date-highlight {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .overdue-highlight {
            background: #fee2e2;
            color: #dc2626;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        /* Line Items Table */
        .items-section {
            margin-bottom: 30px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: #f1f5f9;
            padding: 12px 16px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        .items-table th:last-child,
        .items-table td:last-child {
            text-align: right;
        }

        .items-table th:nth-child(2),
        .items-table td:nth-child(2) {
            text-align: center;
        }

        .items-table th:nth-child(3),
        .items-table td:nth-child(3),
        .items-table th:nth-child(4),
        .items-table td:nth-child(4) {
            text-align: right;
        }

        .items-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .items-table tr:hover {
            background: #f8fafc;
        }

        .item-name {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .item-description {
            font-size: 11px;
            color: #64748b;
        }

        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 40px;
        }

        .totals-box {
            width: 320px;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .totals-row:last-child {
            border-bottom: none;
        }

        .totals-label {
            color: #64748b;
            font-size: 12px;
        }

        .totals-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 12px;
        }

        .totals-row.subtotal {
            border-top: 2px solid #e2e8f0;
            margin-top: 5px;
            padding-top: 15px;
        }

        .totals-row.paid {
            color: #15803d;
        }

        .totals-row.paid .totals-value {
            color: #15803d;
        }

        .totals-row.balance-due {
            background: #fee2e2;
            margin: 10px -16px -10px;
            padding: 16px;
            border-radius: 0 0 8px 8px;
        }

        .totals-row.balance-due .totals-label {
            font-size: 14px;
            font-weight: 600;
            color: #dc2626;
        }

        .totals-row.balance-due .totals-value {
            font-size: 18px;
            color: #dc2626;
        }

        .totals-row.paid-in-full {
            background: #dcfce7;
            margin: 10px -16px -10px;
            padding: 16px;
            border-radius: 0 0 8px 8px;
        }

        .totals-row.paid-in-full .totals-label {
            font-size: 14px;
            font-weight: 600;
            color: #15803d;
        }

        .totals-row.paid-in-full .totals-value {
            font-size: 18px;
            color: #15803d;
        }

        /* Payment History */
        .payments-section {
            margin-bottom: 30px;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .payments-table th {
            background: #dcfce7;
            padding: 10px 14px;
            text-align: left;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #15803d;
        }

        .payments-table th:last-child,
        .payments-table td:last-child {
            text-align: right;
        }

        .payments-table td {
            padding: 10px 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Notes & Terms */
        .notes-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .notes-content {
            font-size: 11px;
            color: #475569;
            line-height: 1.7;
            white-space: pre-wrap;
        }

        /* Payment Instructions */
        .payment-instructions {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .payment-instructions h4 {
            color: #15803d;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .payment-instructions p {
            font-size: 11px;
            color: #166534;
            margin-bottom: 5px;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            text-align: center;
        }

        .footer-text {
            font-size: 11px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .overdue-notice {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }

        /* Print Styles */
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .container {
                padding: 20px;
            }

            .no-print {
                display: none;
            }
        }

        /* Page Break */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div class="company-name">{{ $settings->company_name ?? 'Your Company' }}</div>
                <div class="company-details">
                    {!! nl2br(e($settings->company_address ?? '')) !!}<br>
                    @if($settings->company_phone)Phone: {{ $settings->company_phone }}<br>@endif
                    @if($settings->company_email){{ $settings->company_email }}<br>@endif
                    @if($settings->company_website){{ $settings->company_website }}@endif
                </div>
            </div>
            <div class="document-info">
                <div class="document-title">Invoice</div>
                <div class="document-number">{{ $invoice->invoice_number }}</div>
                <div class="document-date">Issue Date: {{ $invoice->issue_date->format('F j, Y') }}</div>
                <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
            </div>
        </div>

        <!-- Overdue Notice -->
        @if($invoice->status === 'overdue')
            <div style="text-align: center; margin-bottom: 30px;">
                <div class="overdue-notice">
                    PAYMENT OVERDUE - This invoice was due on {{ $invoice->due_date->format('F j, Y') }}
                </div>
            </div>
        @endif

        <!-- Details Section -->
        <div class="details-section">
            <div class="details-box">
                <div class="details-label">Bill To</div>
                <div class="details-content">
                    @if($invoice->contact)
                        <strong>{{ $invoice->contact->full_name ?? 'Customer' }}</strong><br>
                        {{ $invoice->contact->company ?? '' }}<br>
                        {{ $invoice->contact->email ?? '' }}<br>
                        {{ $invoice->contact->phone ?? '' }}
                    @else
                        <em>Customer details not specified</em>
                    @endif
                </div>
            </div>
            <div class="details-box">
                <div class="details-label">Invoice Details</div>
                <div class="details-content">
                    @if($invoice->title)<strong>{{ $invoice->title }}</strong><br>@endif
                    <strong>Due Date:</strong>
                    @if($invoice->status === 'overdue')
                        <span class="overdue-highlight">{{ $invoice->due_date->format('F j, Y') }}</span>
                    @elseif($invoice->status !== 'paid' && $invoice->status !== 'cancelled')
                        <span class="due-date-highlight">{{ $invoice->due_date->format('F j, Y') }}</span>
                    @else
                        {{ $invoice->due_date->format('F j, Y') }}
                    @endif
                    <br>
                    <strong>Payment Terms:</strong> {{ $invoice->payment_terms ?? 'Net 30' }}<br>
                    <strong>Currency:</strong> {{ $invoice->currency }}
                    @if($invoice->quote)
                        <br><strong>Quote Ref:</strong> {{ $invoice->quote->quote_number }}
                    @endif
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 45%">Description</th>
                        <th style="width: 10%">Qty</th>
                        <th style="width: 15%">Unit Price</th>
                        <th style="width: 10%">Discount</th>
                        <th style="width: 20%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->lineItems as $item)
                        <tr>
                            <td>
                                <div class="item-name">{{ $item->product?->name ?? 'Custom Item' }}</div>
                                <div class="item-description">{{ $item->description }}</div>
                            </td>
                            <td>{{ number_format($item->quantity, 2) }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($item->unit_price, 2) }}</td>
                            <td>{{ $item->discount > 0 ? number_format($item->discount, 2) . '%' : '-' }}</td>
                            <td>{{ $currencySymbol }}{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="totals-row">
                    <span class="totals-label">Subtotal</span>
                    <span class="totals-value">{{ $currencySymbol }}{{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                @if($invoice->discount > 0)
                    <div class="totals-row">
                        <span class="totals-label">Discount</span>
                        <span class="totals-value">-{{ $currencySymbol }}{{ number_format($invoice->discount, 2) }}</span>
                    </div>
                @endif
                @if($invoice->tax > 0)
                    <div class="totals-row">
                        <span class="totals-label">Tax</span>
                        <span class="totals-value">{{ $currencySymbol }}{{ number_format($invoice->tax, 2) }}</span>
                    </div>
                @endif
                <div class="totals-row subtotal">
                    <span class="totals-label">Total</span>
                    <span class="totals-value">{{ $currencySymbol }}{{ number_format($invoice->total, 2) }}</span>
                </div>
                @if($invoice->payments->count() > 0)
                    <div class="totals-row paid">
                        <span class="totals-label">Amount Paid</span>
                        <span class="totals-value">-{{ $currencySymbol }}{{ number_format($invoice->total - $invoice->balance_due, 2) }}</span>
                    </div>
                @endif
                @if($invoice->balance_due > 0)
                    <div class="totals-row balance-due">
                        <span class="totals-label">Balance Due</span>
                        <span class="totals-value">{{ $currencySymbol }}{{ number_format($invoice->balance_due, 2) }}</span>
                    </div>
                @else
                    <div class="totals-row paid-in-full">
                        <span class="totals-label">Paid in Full</span>
                        <span class="totals-value">{{ $currencySymbol }}0.00</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Payment History -->
        @if($invoice->payments->count() > 0)
            <div class="payments-section">
                <div class="section-title">Payment History</div>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date->format('M j, Y') }}</td>
                                <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                <td>{{ $payment->reference ?? '-' }}</td>
                                <td>{{ $currencySymbol }}{{ number_format($payment->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Payment Instructions (only if balance due) -->
        @if($invoice->balance_due > 0 && $invoice->status !== 'cancelled')
            <div class="payment-instructions">
                <h4>Payment Instructions</h4>
                <p>Please make payment to the following account:</p>
                <p><strong>Bank:</strong> First National Bank</p>
                <p><strong>Account Name:</strong> {{ $settings->company_name ?? 'Your Company' }}</p>
                <p><strong>Reference:</strong> {{ $invoice->invoice_number }}</p>
            </div>
        @endif

        <!-- Notes -->
        @if($invoice->notes)
            <div class="notes-section">
                <div class="section-title">Notes</div>
                <div class="notes-content">{{ $invoice->notes }}</div>
            </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <div class="footer-text">
                {{ $settings->footer_text ?? 'Thank you for your business!' }}
            </div>
            @if($settings->tax_id)
                <div class="footer-text">Tax ID: {{ $settings->tax_id }}</div>
            @endif
            <div class="footer-text" style="margin-top: 10px; font-size: 10px; color: #94a3b8;">
                Generated on {{ now()->format('F j, Y \a\t g:i A') }}
            </div>
        </div>
    </div>
</body>
</html>
