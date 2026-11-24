<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject ?? 'Quotation' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            margin: 0;
            padding: 0;
            color: #1e293b;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff;
            padding: 28px 32px;
            text-align: left;
        }
        .header-title {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .header-subtitle {
            margin: 6px 0 0 0;
            font-size: 13px;
            color: #bfdbfe;
        }
        .content {
            padding: 32px;
        }
        .message-body {
            font-size: 14px;
            color: #334155;
            white-space: pre-wrap;
            line-height: 1.7;
            margin-bottom: 24px;
        }
        .quote-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin: 24px 0;
        }
        .quote-card-header {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        .quote-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .quote-grid td {
            padding: 6px 0;
            font-size: 13px;
        }
        .quote-grid .label {
            color: #64748b;
            width: 40%;
        }
        .quote-grid .value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }
        .quote-grid .grand-total {
            color: #2563eb;
            font-size: 16px;
            font-weight: 700;
        }
        .attachment-badge {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            margin: 20px 0;
            font-size: 13px;
            color: #1e40af;
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px 32px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
            text-align: center;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1 class="header-title">Quotation {{ $quotation->quotation_number ?? ('#' . $quotation->id) }}</h1>
            <p class="header-subtitle">From Mostech</p>
        </div>

        <!-- Body Content -->
        <div class="content">
            <div class="message-body">{!! nl2br(e($messageBody)) !!}</div>

            <!-- Quotation Summary Card -->
            <div class="quote-card">
                <div class="quote-card-header">Quotation Summary</div>
                <table class="quote-grid">
                    <tr>
                        <td class="label">Quotation Number:</td>
                        <td class="value">{{ $quotation->quotation_number ?? ('#' . $quotation->id) }}</td>
                    </tr>
                    @if($quotation->quotation_date)
                    <tr>
                        <td class="label">Date:</td>
                        <td class="value">{{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d/m/Y') }}</td>
                    </tr>
                    @endif
                    @if($quotation->valid_until)
                    <tr>
                        <td class="label">Valid Until:</td>
                        <td class="value">{{ \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y') }}</td>
                    </tr>
                    @endif
                    @if($quotation->payment_terms)
                    <tr>
                        <td class="label">Payment Terms:</td>
                        <td class="value">{{ $quotation->payment_terms }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="label" style="padding-top: 10px; font-weight: 600; color: #1e293b;">Total Amount:</td>
                        <td class="value grand-total" style="padding-top: 10px;">
                            {{ $quotation->currency ?? 'INR' }} {{ number_format((float) ($quotation->grand_total ?? 0), 2) }}
                        </td>
                    </tr>
                </table>
            </div>

            @if($hasPdf)
            <div class="attachment-badge">
                📎 <strong>Attached:</strong> Quotation document ({{ 'Quotation_' . ($quotation->quotation_number ?? $quotation->id) . '.pdf' }}) is attached to this email.
            </div>
            @endif

            <p style="font-size: 13px; color: #64748b; margin-top: 24px;">
                If you have any questions or require revisions, please reply directly to this email.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 6px 0;"><strong>Mostech</strong></p>
            <p style="margin: 0;">This email was sent automatically from Mostech Quotation Management.</p>
        </div>
    </div>
</body>
</html>
