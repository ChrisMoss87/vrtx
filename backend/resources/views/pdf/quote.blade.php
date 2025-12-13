<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $quote->quote_number }} - Quote</title>
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
            color: #3b82f6;
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
        .status-accepted { background: #dcfce7; color: #15803d; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        .status-expired { background: #fef3c7; color: #b45309; }

        /* Customer & Quote Details */
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
            width: 300px;
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

        .totals-row.total {
            background: #f1f5f9;
            margin: 10px -16px -10px;
            padding: 16px;
            border-radius: 0 0 8px 8px;
        }

        .totals-row.total .totals-label {
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }

        .totals-row.total .totals-value {
            font-size: 18px;
            color: #3b82f6;
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

        .validity-notice {
            background: #fef3c7;
            color: #92400e;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 11px;
            display: inline-block;
            margin-top: 10px;
        }

        /* Signature Area */
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }

        .signature-box {
            flex: 1;
            padding-top: 60px;
            border-top: 1px solid #94a3b8;
        }

        .signature-label {
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
                <div class="document-title">Quote</div>
                <div class="document-number">{{ $quote->quote_number }}</div>
                <div class="document-date">Date: {{ $quote->created_at->format('F j, Y') }}</div>
                <span class="status-badge status-{{ $quote->status }}">{{ ucfirst($quote->status) }}</span>
            </div>
        </div>

        <!-- Details Section -->
        <div class="details-section">
            <div class="details-box">
                <div class="details-label">Bill To</div>
                <div class="details-content">
                    @if($quote->contact)
                        <strong>{{ $quote->contact->full_name ?? 'Customer' }}</strong><br>
                        {{ $quote->contact->company ?? '' }}<br>
                        {{ $quote->contact->email ?? '' }}<br>
                        {{ $quote->contact->phone ?? '' }}
                    @else
                        <em>Customer details not specified</em>
                    @endif
                </div>
            </div>
            <div class="details-box">
                <div class="details-label">Quote Details</div>
                <div class="details-content">
                    @if($quote->title)<strong>{{ $quote->title }}</strong><br>@endif
                    <strong>Valid Until:</strong> {{ $quote->valid_until?->format('F j, Y') ?? 'N/A' }}<br>
                    <strong>Currency:</strong> {{ $quote->currency }}<br>
                    @if($quote->assignedTo)
                        <strong>Sales Rep:</strong> {{ $quote->assignedTo->name }}
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
                    @foreach($quote->lineItems as $item)
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
                    <span class="totals-value">{{ $currencySymbol }}{{ number_format($quote->subtotal, 2) }}</span>
                </div>
                @if($quote->discount > 0)
                    <div class="totals-row">
                        <span class="totals-label">Discount</span>
                        <span class="totals-value">-{{ $currencySymbol }}{{ number_format($quote->discount, 2) }}</span>
                    </div>
                @endif
                @if($quote->tax > 0)
                    <div class="totals-row">
                        <span class="totals-label">Tax</span>
                        <span class="totals-value">{{ $currencySymbol }}{{ number_format($quote->tax, 2) }}</span>
                    </div>
                @endif
                <div class="totals-row total">
                    <span class="totals-label">Total</span>
                    <span class="totals-value">{{ $currencySymbol }}{{ number_format($quote->total, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Terms -->
        @if($quote->terms)
            <div class="notes-section">
                <div class="section-title">Terms & Conditions</div>
                <div class="notes-content">{{ $quote->terms }}</div>
            </div>
        @endif

        <!-- Notes -->
        @if($quote->notes)
            <div class="notes-section">
                <div class="section-title">Notes</div>
                <div class="notes-content">{{ $quote->notes }}</div>
            </div>
        @endif

        <!-- Signature Area -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="signature-label">Customer Signature</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Date</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            @if($quote->valid_until && $quote->status !== 'accepted' && $quote->status !== 'rejected')
                <div class="validity-notice">
                    This quote is valid until {{ $quote->valid_until->format('F j, Y') }}
                </div>
            @endif
            <div class="footer-text" style="margin-top: 15px;">
                {{ $settings->footer_text ?? 'Thank you for your business!' }}
            </div>
            @if($settings->tax_id)
                <div class="footer-text">Tax ID: {{ $settings->tax_id }}</div>
            @endif
        </div>
    </div>
</body>
</html>
