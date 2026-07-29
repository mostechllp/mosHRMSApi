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
            color: #2d3748;
            background: #fff;
            padding: 24px 30px;
            -webkit-font-smoothing: antialiased;
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
            table-layout: fixed; 
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
            white-space: normal; 
        }

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
            word-wrap: break-word;   
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
        <h1>{{ $title }}</h1>
        <div class="meta">
            <div>Generated on: {{ now()->format('d M Y, h:i A') }}</div>
        </div>
    </div>

    {{-- ── Report Table ── --}}
    <table>
        <thead>
            <tr>
                @foreach($headings as $heading)
                <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($data as $row)
            <tr>
                @foreach($row as $cell)
                <td>{{ $cell }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Footer ── --}}
    <div class="report-footer">
        &copy; {{ date('Y') }} HRMS System &mdash; Generated on {{ now()->format('d M Y, h:i A') }}
    </div>

</body>

</html>