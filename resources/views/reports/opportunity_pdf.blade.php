<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Opportunities Report' }}</title>
    <style>
        @font-face {
            font-family: 'DejaVu Sans';
            font-style: normal;
            font-weight: normal;
            src: url('{{ storage_path("fonts/DejaVuSans.ttf") }}') format('truetype');
        }

        body,
        table,
        th,
        td,
        div,
        span {
            font-family: 'DejaVu Sans', sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            background: #ffffff;
            padding: 20px 24px;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ── */
        .report-header {
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #2563eb;
            display: table;
            width: 100%;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
            width: 60%;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            width: 40%;
            text-align: right;
            font-size: 9.5px;
            color: #64748b;
        }

        .report-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 4px;
        }

        .report-subtitle {
            font-size: 11px;
            color: #64748b;
        }

        /* ── KPI Cards ── */
        .stats-grid {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: separate;
            border-spacing: 8px 0;
        }

        .stat-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            text-align: center;
            vertical-align: top;
        }

        .stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .text-blue {
            color: #2563eb;
        }

        .text-purple {
            color: #7c3aed;
        }

        .text-emerald {
            color: #059669;
        }

        .text-rose {
            color: #e11d48;
        }

        .text-amber {
            color: #d97706;
        }

        /* ── Table ── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        table.data-table thead tr {
            background-color: #1e3a8a;
            color: #ffffff;
        }

        table.data-table th {
            padding: 8px 6px;
            font-size: 9.5px;
            font-weight: bold;
            text-align: left;
            border: 1px solid #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table.data-table td {
            padding: 7px 6px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
            vertical-align: middle;
            color: #334155;
        }

        table.data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-semibold {
            font-weight: 600;
        }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 3px 6px;
            font-size: 8.5px;
            font-weight: 600;
            border-radius: 10px;
            text-align: center;
        }

        .badge-new {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        .badge-qualified {
            background-color: #f3e8ff;
            color: #7e22ce;
        }

        .badge-proposal {
            background-color: #e0e7ff;
            color: #4338ca;
        }

        .badge-negotiation {
            background-color: #fef3c7;
            color: #b45309;
        }

        .badge-won {
            background-color: #d1fae5;
            color: #047857;
        }

        .badge-lost {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .priority-high {
            background-color: #fee2e2;
            color: #dc2626;
        }

        .priority-medium {
            background-color: #fef3c7;
            color: #d97706;
        }

        .priority-low {
            background-color: #d1fae5;
            color: #059669;
        }

        /* ── Footer ── */
        .report-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5px;
            color: #94a3b8;
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            text-align: left;
        }

        .footer-right {
            display: table-cell;
            text-align: right;
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="report-header">
        <div class="header-left">
            <div class="report-title">{{ $company->name ?? 'MOS HRMS & CRM' }}</div>
            <div class="report-subtitle">Opportunities Pipeline & Sales Report</div>
        </div>
        <div class="header-right">
            <div><strong>Generated:</strong> {{ now()->format('d M Y, h:i A') }}</div>
            <div><strong>Total Records:</strong> {{ count($opportunities) }}</div>
        </div>
    </div>

    <!-- Metric Summary Cards -->
    @if(isset($stats))
        <table class="stats-grid">
            <tr>
                <td class="stat-card" style="width: 16.6%;">
                    <div class="stat-value text-blue">{{ $stats['total'] ?? count($opportunities) }}</div>
                    <div class="stat-label">Total</div>
                </td>
                <td class="stat-card" style="width: 16.6%;">
                    <div class="stat-value text-purple">{{ $stats['open'] ?? 0 }}</div>
                    <div class="stat-label">Open</div>
                </td>
                <td class="stat-card" style="width: 20%;">
                    <div class="stat-value text-blue">{{ $stats['formatted_pipeline_value'] ?? '₹0.00' }}</div>
                    <div class="stat-label">Pipeline Value</div>
                </td>
                <td class="stat-card" style="width: 15.6%;">
                    <div class="stat-value text-emerald">{{ $stats['won'] ?? 0 }}</div>
                    <div class="stat-label">Won</div>
                </td>
                <td class="stat-card" style="width: 15.6%;">
                    <div class="stat-value text-rose">{{ $stats['lost'] ?? 0 }}</div>
                    <div class="stat-label">Lost</div>
                </td>
                <td class="stat-card" style="width: 15.6%;">
                    <div class="stat-value text-amber">{{ $stats['closing_this_month'] ?? 0 }}</div>
                    <div class="stat-label">Closing This Month</div>
                </td>
            </tr>
        </table>
    @endif

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%;">OPP ID</th>
                <th style="width: 19%;">OPPORTUNITY</th>
                <th style="width: 15%;">CUSTOMER</th>
                <th style="width: 11%;">PRODUCT</th>
                <th style="width: 9%; text-align: right;">VALUE</th>
                <th style="width: 11%; text-align: center;">STAGE</th>
                <th style="width: 6%; text-align: center;">PROB.</th>
                <th style="width: 9%; text-align: center;">EXPECTED CLOSE</th>
                <th style="width: 12%;">ASSIGNED</th>
                <th style="width: 8%; text-align: center;">PRIORITY</th>
            </tr>
        </thead>
        <tbody>
            @forelse($opportunities as $opp)
                @php
                    $stageLower = strtolower(trim($opp->stage ?? 'new'));
                    $stageClass = match ($stageLower) {
                        'new' => 'badge-new',
                        'qualified' => 'badge-qualified',
                        'proposal sent' => 'badge-proposal',
                        'negotiation' => 'badge-negotiation',
                        'won' => 'badge-won',
                        'lost' => 'badge-lost',
                        default => 'badge-new'
                    };

                    $priorityLower = strtolower(trim($opp->priority ?? 'medium'));
                    $priorityClass = match ($priorityLower) {
                        'high' => 'priority-high',
                        'medium' => 'priority-medium',
                        'low' => 'priority-low',
                        default => 'priority-medium'
                    };
                @endphp
                <tr>
                    <td class="font-semibold">
                        {{ $opp->opportunity_id ?? ('OPP-' . str_pad($opp->id, 4, '0', STR_PAD_LEFT)) }}
                    </td>
                    <td class="font-semibold">{{ $opp->name }}</td>
                    <td>{{ $opp->customer_name }}</td>
                    <td>{{ $opp->product_display }}</td>
                    <td class="text-right font-semibold">{{ $opp->formatted_value }}</td>
                    <td class="text-center">
                        <span class="badge {{ $stageClass }}">{{ $opp->stage }}</span>
                    </td>
                    <td class="text-center">{{ (int) $opp->probability }}%</td>
                    <td class="text-center">{{ $opp->formatted_expected_closing_date ?? '-' }}</td>
                    <td>{{ $opp->assigned_salesperson_name }}</td>
                    <td class="text-center">
                        <span class="badge {{ $priorityClass }}">{{ $opp->priority }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align: center; padding: 20px; color: #94a3b8;">
                        No opportunities found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="report-footer">
        <div class="footer-left">
            MOS HRMS & CRM Pipeline Report &bull; Strictly Confidential
        </div>
        <div class="footer-right">
            Page 1 of 1
        </div>
    </div>

</body>

</html>