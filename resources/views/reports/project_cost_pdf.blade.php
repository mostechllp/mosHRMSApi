<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            padding: 24px 28px;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ── */
        .report-header {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #0f172a;
        }

        .report-header h1 {
            font-size: 22px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .report-header .subtitle {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
        }

        .report-header .meta {
            font-size: 10px;
            color: #94a3b8;
        }

        /* ── KPI Cards ── */
        .kpi-container {
            width: 100%;
            margin-bottom: 20px;
        }

        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
        }

        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            text-align: center;
        }

        .kpi-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }

        .kpi-value {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }

        .text-green { color: #16a34a !important; }
        .text-red { color: #dc2626 !important; }
        .text-blue { color: #2563eb !important; }

        /* ── Main Table ── */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.data-table thead tr {
            background-color: #0f172a;
            color: #ffffff;
        }

        table.data-table thead th {
            padding: 8px 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid #1e293b;
            text-align: left;
        }

        table.data-table thead th.text-right {
            text-align: right;
        }

        table.data-table thead th.text-center {
            text-align: center;
        }

        table.data-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        table.data-table tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        table.data-table tbody td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #334155;
            vertical-align: middle;
        }

        table.data-table tbody td.text-right {
            text-align: right;
        }

        table.data-table tbody td.text-center {
            text-align: center;
        }

        table.data-table tr.summary-row td {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #0f172a;
            border-top: 2px solid #94a3b8;
            font-size: 11px;
        }

        .badge-employees {
            display: inline-block;
            background-color: #e0e7ff;
            color: #4338ca;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 10px;
        }

        /* ── Footer ── */
        .report-footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="report-header">
        <h1>{{ $title }}</h1>
        <div class="subtitle">Project-wise employees, hours, cost, budget and variance in AED &bull; <strong>{{ $period_label }}</strong></div>
        <div class="meta">Generated on: {{ now()->format('d M Y, h:i A') }} &bull; Currency: AED</div>
    </div>

    <!-- Summary KPIs -->
    <div class="kpi-container">
        <table class="kpi-table">
            <tr>
                <td class="kpi-card">
                    <div class="kpi-label">Total Employees</div>
                    <div class="kpi-value">{{ $summary['total_employees'] }}</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-label">Total Hours</div>
                    <div class="kpi-value text-blue">{{ $summary['total_hours_formatted'] }}</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-label">Total Cost</div>
                    <div class="kpi-value">{{ $summary['total_cost_formatted'] }}</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-label">Total Budget</div>
                    <div class="kpi-value">{{ $summary['total_budget_formatted'] }}</div>
                </td>
                <td class="kpi-card">
                    <div class="kpi-label">Net Variance</div>
                    <div class="kpi-value {{ $summary['total_variance'] >= 0 ? 'text-green' : 'text-red' }}">
                        {{ $summary['total_variance_formatted'] }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Project Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 25%;">Project</th>
                <th style="width: 15%;">Client</th>
                <th class="text-center" style="width: 10%;">Employees</th>
                <th class="text-center" style="width: 12%;">Hours</th>
                <th class="text-right" style="width: 13%;">Cost (AED)</th>
                <th class="text-right" style="width: 13%;">Budget (AED)</th>
                <th class="text-right" style="width: 12%;">Variance (AED)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($projects as $proj)
            <tr>
                <td><strong>{{ $proj['project_name'] }}</strong></td>
                <td>{{ $proj['client_name'] ?? '-' }}</td>
                <td class="text-center">
                    <span class="badge-employees">{{ $proj['employees_count'] }}</span>
                </td>
                <td class="text-center">{{ $proj['total_hours_formatted'] }}</td>
                <td class="text-right">{{ number_format($proj['total_cost_aed'], 2) }}</td>
                <td class="text-right">
                    @if($proj['has_budget'])
                        {{ number_format($proj['budget_aed'], 2) }}
                    @else
                        <span style="color: #94a3b8; font-style: italic;">No budget set</span>
                    @endif
                </td>
                <td class="text-right">
                    @if($proj['has_budget'])
                        <span class="{{ $proj['variance_aed'] >= 0 ? 'text-green' : 'text-red' }}" style="font-weight: 600;">
                            {{ ($proj['variance_aed'] < 0 ? '-' : '') . number_format(abs($proj['variance_aed']), 2) }}
                        </span>
                    @else
                        <span style="color: #94a3b8;">-</span>
                    @endif
                </td>
            </tr>
            @endforeach

            <!-- Total Summary Row -->
            <tr class="summary-row">
                <td colspan="2">TOTAL SUMMARY</td>
                <td class="text-center">{{ $summary['total_employees'] }}</td>
                <td class="text-center">{{ $summary['total_hours_formatted'] }}</td>
                <td class="text-right">{{ number_format($summary['total_cost'], 2) }}</td>
                <td class="text-right">{{ number_format($summary['total_budget'], 2) }}</td>
                <td class="text-right">
                    <span class="{{ $summary['total_variance'] >= 0 ? 'text-green' : 'text-red' }}">
                        {{ ($summary['total_variance'] < 0 ? '-' : '') . number_format(abs($summary['total_variance']), 2) }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="report-footer">
        &copy; {{ date('Y') }} HRMS System &bull; Project Cost & Financial Analysis Report &bull; Confirmed & Confidential
    </div>

</body>

</html>

