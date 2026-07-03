<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Task Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            background: #fff;
            padding: 24px;
        }

        /* ── Header ── */
        .report-header {
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 2px solid #1e3a5f;
        }

        .report-header h1 {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 6px;
        }

        .report-header .meta {
            font-size: 11px;
            color: #555;
            line-height: 1.7;
        }

        .report-header .summary {
            margin-top: 8px;
            font-size: 12px;
            font-weight: bold;
            color: #1e3a5f;
        }

        /* ── Table ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        thead tr {
            background-color: #1e3a5f;
            color: #ffffff;
        }

        thead th {
            padding: 9px 8px;
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            border: 1px solid #16304f;
            white-space: nowrap;
        }

        /* Column widths */
        .col-sno       { width: 4%; }
        .col-date      { width: 8%; }
        .col-employee  { width: 12%; }
        .col-tasks     { width: 38%; }
        .col-pending   { width: 14%; }
        .col-plan      { width: 14%; }
        .col-remarks   { width: 10%; }

        tbody tr {
            vertical-align: top;
        }

        tbody tr:nth-child(even) {
            background-color: #f5f8fc;
        }

        tbody tr:nth-child(odd) {
            background-color: #ffffff;
        }

        tbody td {
            padding: 8px 8px;
            border: 1px solid #d0d8e4;
            font-size: 10.5px;
            line-height: 1.55;
            color: #333;
        }

        tbody td.center {
            text-align: center;
        }

        /* Task text — preserve newlines */
        .task-text {
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Empty cell dash */
        .dash {
            color: #aaa;
            text-align: center;
        }

        /* ── Footer ── */
        .report-footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #d0d8e4;
            font-size: 10px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ── Report Header ── --}}
    <div class="report-header">
        <h1>Task Report</h1>
        <div class="meta">
            <div>Generated: {{ now()->format('n/j/Y, g:i:s A') }}</div>
            @if($period)
                <div>Period: {{ $period }}</div>
            @endif
        </div>
        <div class="summary">
            Total: {{ $total }} | With Remarks: {{ $withRemarks }}
        </div>
    </div>

    {{-- ── Report Table ── --}}
    <table>
        <thead>
            <tr>
                <th class="col-sno">S.No</th>
                <th class="col-date">Date</th>
                <th class="col-employee">Employee</th>
                <th class="col-tasks">Tasks Completed</th>
                <th class="col-pending">Pending Tasks</th>
                <th class="col-plan">Plan for Tomorrow</th>
                <th class="col-remarks">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center">{{ $row['date'] ?? '-' }}</td>
                    <td>{{ $row['employee_name'] ?? '-' }}</td>
                    <td>
                        @if(!empty($row['tasks_completed']))
                            <span class="task-text">{{ $row['tasks_completed'] }}</span>
                        @else
                            <span class="dash">-</span>
                        @endif
                    </td>
                    <td>
                        @if(!empty($row['pending_tasks']))
                            <span class="task-text">{{ $row['pending_tasks'] }}</span>
                        @else
                            <span class="dash">-</span>
                        @endif
                    </td>
                    <td>
                        @if(!empty($row['plan_tomorrow']))
                            <span class="task-text">{{ $row['plan_tomorrow'] }}</span>
                        @else
                            <span class="dash">-</span>
                        @endif
                    </td>
                    <td>
                        @if(!empty($row['remarks']))
                            <span class="task-text">{{ $row['remarks'] }}</span>
                        @else
                            <span class="dash">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center" style="padding: 20px; color: #999;">
                        No task reports found for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Footer ── --}}
    <div class="report-footer">
        &copy; {{ date('Y') }} HRMS System &mdash; Task Report &mdash; Generated on {{ now()->format('d M Y, h:i A') }}
    </div>

</body>
</html>
