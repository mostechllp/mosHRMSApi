<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Quotation - {{ $quotation->quotation_number ?? $quotation->id }}</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            font-size: 10px;
            line-height: 1.4;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #1d4ed8;
            margin-bottom: 4px;
        }
        .company-subtitle {
            font-size: 10px;
            color: #64748b;
        }
        .quotation-badge-title {
            text-align: right;
        }
        .quote-title {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .quote-number {
            font-size: 12px;
            font-weight: bold;
            color: #2563eb;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background-color: #eff6ff;
            color: #1d4ed8;
            margin-top: 4px;
        }
        .divider {
            border-top: 2px solid #e2e8f0;
            margin: 15px 0;
        }
        .meta-boxes td {
            vertical-align: top;
            width: 50%;
        }
        .box {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            background-color: #f8fafc;
        }
        .box-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .info-row {
            margin-bottom: 3px;
        }
        .info-label {
            color: #64748b;
            font-size: 9px;
        }
        .info-value {
            font-weight: bold;
            color: #0f172a;
            font-size: 10px;
        }
        .items-table {
            margin-top: 15px;
            border: 1px solid #e2e8f0;
        }
        .items-table th {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
            padding: 7px 8px;
            border: 1px solid #1d4ed8;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 7px 8px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-left {
            text-align: left;
        }
        .totals-table {
            width: 45%;
            margin-left: auto;
            margin-top: 12px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 4px 8px;
            font-size: 10px;
        }
        .totals-table .label {
            color: #64748b;
            text-align: right;
        }
        .totals-table .value {
            font-weight: bold;
            color: #0f172a;
            text-align: right;
        }
        .grand-total-row {
            background-color: #eff6ff;
            border-top: 1.5px solid #2563eb;
            border-bottom: 1.5px solid #2563eb;
        }
        .grand-total-row td {
            padding: 6px 8px;
            font-size: 11px;
            font-weight: bold;
            color: #1d4ed8;
        }
        .terms-card {
            margin-top: 15px;
            padding: 10px 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .terms-card-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 4px;
        }
        .terms-content {
            font-size: 9px;
            color: #334155;
            white-space: pre-wrap;
            line-height: 1.4;
        }
        .footer-table {
            margin-top: 25px;
        }
        .footer-table td {
            vertical-align: bottom;
            font-size: 9px;
            color: #64748b;
        }
        .signature-box {
            text-align: right;
        }
        .signature-line {
            border-top: 1px solid #0f172a;
            width: 180px;
            margin-left: auto;
            margin-top: 40px;
            padding-top: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #0f172a;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="company-name">{{ $company->company_name ?? 'Mostech' }}</div>
                <div class="company-subtitle">
                    {{ $company->address ?? 'Corporate Solutions & Services' }}<br>
                    @if(!empty($company->phone)) Phone: {{ $company->phone }} | @endif
                    @if(!empty($company->email)) Email: {{ $company->email }} @endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <div class="quote-title">Quotation</div>
                <div class="quote-number">{{ $quotation->quotation_number ?? ('#' . $quotation->id) }}</div>
                <span class="status-badge">{{ strtoupper($quotation->status ?? 'Draft') }}</span>
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Metadata Boxes (Customer & Dates) -->
    <table class="meta-boxes">
        <tr>
            <td style="padding-right: 8px;">
                <div class="box">
                    <div class="box-title">Quotation To (Customer)</div>
                    <div style="font-size: 11px; font-weight: bold; color: #0f172a; margin-bottom: 3px;">
                        {{ $quotation->customer->company_name ?? $quotation->customer_name ?? 'Client' }}
                    </div>
                    @if(!empty($quotation->contact_person) || !empty($quotation->customer?->contact_person_name))
                        <div class="info-row"><span class="info-label">Attn:</span> <span class="info-value">{{ $quotation->contact_person ?? $quotation->customer->contact_person_name }}</span></div>
                    @endif
                    @if(!empty($quotation->contact_email) || !empty($quotation->customer?->contact_email))
                        <div class="info-row"><span class="info-label">Email:</span> <span class="info-value">{{ $quotation->contact_email ?? $quotation->customer->contact_email }}</span></div>
                    @endif
                    @if(!empty($quotation->contact_phone) || !empty($quotation->customer?->contact_phone))
                        <div class="info-row"><span class="info-label">Phone:</span> <span class="info-value">{{ $quotation->contact_phone ?? $quotation->customer->contact_phone }}</span></div>
                    @endif
                    @if(!empty($quotation->billing_address) || !empty($quotation->customer?->billing_address))
                        <div class="info-row"><span class="info-label">Address:</span> <span class="info-value">{{ $quotation->billing_address ?? $quotation->customer->billing_address }}</span></div>
                    @endif
                </div>
            </td>
            <td style="padding-left: 8px;">
                <div class="box">
                    <div class="box-title">Quotation Details</div>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value">{{ $quotation->quotation_date ? \Carbon\Carbon::parse($quotation->quotation_date)->format('d M, Y') : date('d M, Y') }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Valid Until:</span>
                        <span class="info-value">{{ $quotation->valid_until ? \Carbon\Carbon::parse($quotation->valid_until)->format('d M, Y') : '—' }}</span>
                    </div>
                    @if(!empty($quotation->currency))
                    <div class="info-row">
                        <span class="info-label">Currency:</span>
                        <span class="info-value">{{ $quotation->currency }}</span>
                    </div>
                    @endif
                    @if(!empty($quotation->payment_terms))
                    <div class="info-row">
                        <span class="info-label">Payment Terms:</span>
                        <span class="info-value">{{ $quotation->payment_terms }}</span>
                    </div>
                    @endif
                    @if(!empty($quotation->delivery_timeline))
                    <div class="info-row">
                        <span class="info-label">Timeline:</span>
                        <span class="info-value">{{ $quotation->delivery_timeline }}</span>
                    </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;" class="text-center">#</th>
                <th style="width: 40%;" class="text-left">Product / Service</th>
                <th style="width: 10%;" class="text-center">Qty</th>
                <th style="width: 13%;" class="text-right">Unit Price</th>
                <th style="width: 10%;" class="text-right">Discount</th>
                <th style="width: 8%;" class="text-right">Tax</th>
                <th style="width: 14%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quotation->items as $idx => $item)
            <tr>
                <td class="text-center">{{ $idx + 1 }}</td>
                <td class="text-left">
                    <strong>{{ $item->product_service }}</strong>
                    @if(!empty($item->description))
                        <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">{{ $item->description }}</div>
                    @endif
                </td>
                <td class="text-center">{{ (float) $item->quantity }}</td>
                <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="text-right">
                    @if((float) $item->discount > 0)
                        {{ (float) $item->discount }}{{ $item->discount_type === 'percent' ? '%' : '' }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right">
                    @if((float) $item->tax_percent > 0)
                        {{ (float) $item->tax_percent }}%
                    @else
                        -
                    @endif
                </td>
                <td class="text-right"><strong>{{ number_format((float) $item->line_total, 2) }}</strong></td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="color: #94a3b8; padding: 12px;">No items listed.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Financial Totals -->
    <table class="totals-table">
        <tr>
            <td class="label">Subtotal:</td>
            <td class="value">{{ number_format((float) ($quotation->subtotal ?? 0), 2) }}</td>
        </tr>
        @if((float) ($quotation->discount_total ?? 0) > 0)
        <tr>
            <td class="label">Discount:</td>
            <td class="value">-{{ number_format((float) $quotation->discount_total, 2) }}</td>
        </tr>
        @endif
        @if((float) ($quotation->tax_total ?? 0) > 0)
        <tr>
            <td class="label">Tax:</td>
            <td class="value">+{{ number_format((float) $quotation->tax_total, 2) }}</td>
        </tr>
        @endif
        @if((float) ($quotation->additional_charges ?? 0) > 0)
        <tr>
            <td class="label">Additional Charges:</td>
            <td class="value">+{{ number_format((float) $quotation->additional_charges, 2) }}</td>
        </tr>
        @endif
        <tr class="grand-total-row">
            <td class="label" style="color: #1d4ed8;">Grand Total ({{ $quotation->currency ?? 'INR' }}):</td>
            <td class="value" style="color: #1d4ed8;">{{ number_format((float) ($quotation->grand_total ?? 0), 2) }}</td>
        </tr>
    </table>

    <!-- Terms and Notes -->
    @if(!empty($quotation->terms_and_conditions) || !empty($quotation->notes))
    <div style="margin-top: 15px;">
        @if(!empty($quotation->terms_and_conditions))
        <div class="terms-card">
            <div class="terms-card-title">Terms & Conditions</div>
            <div class="terms-content">{!! nl2br(e($quotation->terms_and_conditions)) !!}</div>
        </div>
        @endif

        @if(!empty($quotation->notes))
        <div class="terms-card" style="margin-top: 8px;">
            <div class="terms-card-title">Special Notes</div>
            <div class="terms-content">{!! nl2br(e($quotation->notes)) !!}</div>
        </div>
        @endif
    </div>
    @endif

    <!-- Signatures and Footer -->
    <table class="footer-table" style="width: 100%;">
        <tr>
            <td style="width: 60%;">
                <p style="margin: 0;">Thank you for your business inquiry!</p>
                <p style="margin: 2px 0; color: #94a3b8; font-size: 8px;">This is a computer-generated quotation.</p>
            </td>
            <td style="width: 40%;" class="signature-box">
                <div class="signature-line">Authorized Signatory</div>
                <div style="font-size: 8.5px; color: #64748b; margin-top: 2px;">{{ $company->company_name ?? 'Mostech' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
