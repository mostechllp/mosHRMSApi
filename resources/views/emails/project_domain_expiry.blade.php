<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain & Email Expiry Alert</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 650px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #ea580c;
            color: white;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .content {
            padding: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .item-card {
            background-color: #fff7ed;
            border-left: 4px solid #f97316;
            padding: 14px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .item-card.expired {
            background-color: #fef2f2;
            border-left-color: #ef4444;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 9999px;
            text-transform: uppercase;
        }
        .badge-expired {
            background-color: #fee2e2;
            color: #dc2626;
        }
        .badge-expiring {
            background-color: #ffedd5;
            color: #c2410c;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 6px;
        }
        .meta-table td {
            padding: 4px 0;
        }
        .meta-label {
            font-weight: bold;
            color: #64748b;
            width: 140px;
        }
        .footer {
            background-color: #f8fafc;
            padding: 15px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Domain & Project Email Expiry Alert</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $recipientName }}</strong>,</p>
            <p>This is an automated notification regarding project domains and project email accounts that are <strong>expired</strong> or <strong>expiring within the next {{ $daysThreshold }} days</strong>.</p>

            @if(count($expiringDomains) > 0)
                <div class="section-title">🌐 Project Domains ({{ count($expiringDomains) }})</div>
                @foreach($expiringDomains as $domain)
                    <div class="item-card {{ $domain['status'] === 'expired' ? 'expired' : '' }}">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong>{{ $domain['domain_name'] }}</strong>
                            <span class="badge {{ $domain['status'] === 'expired' ? 'badge-expired' : 'badge-expiring' }}">
                                {{ $domain['status'] === 'expired' ? 'Expired' : 'Expiring Soon' }}
                            </span>
                        </div>
                        <table class="meta-table">
                            <tr>
                                <td class="meta-label">Project:</td>
                                <td>{{ $domain['project_name'] }} (ID: {{ $domain['project_id'] }})</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Expiry Date:</td>
                                <td><strong>{{ $domain['domain_expiry_date'] }}</strong> ({{ $domain['days_remaining'] < 0 ? abs($domain['days_remaining']) . ' days ago' : 'in ' . $domain['days_remaining'] . ' days' }})</td>
                            </tr>
                            @if(!empty($domain['domain_purchased_from']))
                            <tr>
                                <td class="meta-label">Purchased From:</td>
                                <td>{{ $domain['domain_purchased_from'] }}</td>
                            </tr>
                            @endif
                            @if(!empty($domain['client_name']))
                            <tr>
                                <td class="meta-label">Client:</td>
                                <td>{{ $domain['client_name'] }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            @endif

            @if(count($expiringEmails) > 0)
                <div class="section-title">✉️ Project Email Accounts ({{ count($expiringEmails) }})</div>
                @foreach($expiringEmails as $email)
                    <div class="item-card {{ $email['status'] === 'expired' ? 'expired' : '' }}">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <strong>{{ $email['email_name'] }}</strong>
                            <span class="badge {{ $email['status'] === 'expired' ? 'badge-expired' : 'badge-expiring' }}">
                                {{ $email['status'] === 'expired' ? 'Expired' : 'Expiring Soon' }}
                            </span>
                        </div>
                        <table class="meta-table">
                            <tr>
                                <td class="meta-label">Project:</td>
                                <td>{{ $email['project_name'] }} (ID: {{ $email['project_id'] }})</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Expiry Date:</td>
                                <td><strong>{{ $email['expiry_date'] }}</strong> ({{ $email['days_remaining'] < 0 ? abs($email['days_remaining']) . ' days ago' : 'in ' . $email['days_remaining'] . ' days' }})</td>
                            </tr>
                            @if(!empty($email['purchase_date']))
                            <tr>
                                <td class="meta-label">Purchase Date:</td>
                                <td>{{ $email['purchase_date'] }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            @endif

            <p style="margin-top: 25px;">Please take appropriate action to renew these domains and email subscriptions to avoid service disruption.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} HRMS System. All rights reserved.
        </div>
    </div>
</body>
</html>
