<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'CRM Report' }}</title>
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
            font-size: 9px;
            color: #64748b;
            line-height: 1.4;
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

        /* ── KPI Summary Cards ── */
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

        .stat-sub {
            font-size: 8px;
            color: #94a3b8;
            margin-top: 2px;
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
            font-size: 9px;
            font-weight: bold;
            text-align: left;
            border: 1px solid #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        table.data-table td {
            padding: 7px 6px;
            border: 1px solid #e2e8f0;
            font-size: 9px;
            vertical-align: middle;
            color: #334155;
            word-wrap: break-word;
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
            <div class="report-subtitle">{{ $title }}</div>
        </div>
        <div class="header-right">
            <div><strong>Generated:</strong> {{ now()->format('d M Y, h:i A') }}</div>
            <div><strong>Total Records:</strong> {{ count($data ?? []) }}</div>
            @if(!empty($date_range_label))
                <div><strong>Period:</strong> {{ $date_range_label }}</div>
            @endif
        </div>
    </div>

    <!-- KPI Summary Cards -->
    @if(!empty($kpis) && is_array($kpis))
        <table class="stats-grid">
            <tr>
                @foreach($kpis as $kpi)
                    <td class="stat-card">
                        <div class="stat-value {{ $kpi['color_class'] ?? 'text-blue' }}">{{ $kpi['value'] }}</div>
                        <div class="stat-label">{{ $kpi['label'] }}</div>
                        @if(!empty($kpi['sub']))
                            <div class="stat-sub">{{ $kpi['sub'] }}</div>
                        @endif
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <!-- Data Table -->
    <table class="data-table">
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($data as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}" class="text-center" style="padding: 18px; color: #94a3b8;">
                        No records found for the selected criteria.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="report-footer">
        <div class="footer-left">
            MOS HRMS &bull; CRM & Sales Analytics Report
        </div>
        <div class="footer-right">
            Confidential &bull; Generated on {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>

</body>

</html>