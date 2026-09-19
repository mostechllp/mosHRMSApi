<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $warning->subject }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #dc2626;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
        }
        .content {
            padding: 30px;
        }
        .warning-card {
            background-color: #fff5f5;
            border-left: 4px solid #dc2626;
            padding: 18px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-card h2 {
            margin-top: 0;
            font-size: 18px;
            color: #991b1b;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 6px 0;
            font-size: 14px;
        }
        .meta-label {
            font-weight: bold;
            color: #6b7280;
            width: 120px;
        }
        .meta-value {
            color: #111827;
        }
        .description-body {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 15px;
            font-size: 14px;
            line-height: 1.6;
            color: #374151;
            white-space: pre-wrap;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Official Warning Notice</h1>
        </div>
        <div class="content">
            <p>Dear {{ $employee->first_name }} {{ $employee->last_name }},</p>
            <p>{{ $warning->subject }}.</p>

            <div class="warning-card">
                <h2>{{ $warning->title }}</h2>
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Employee Code:</td>
                        <td class="meta-value">{{ $employee->employee_id }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Date Issued:</td>
                        <td class="meta-value">{{ $warning->issued_date ? $warning->issued_date->format('d M Y') : date('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Subject:</td>
                        <td class="meta-value"><strong>{{ $warning->subject }}</strong></td>
                    </tr>
                </table>

                <div class="description-body">
                    {{ $warning->description }}
                </div>
            </div>

            <p>Regards,</p>
            <p>Management</p>
        </div>
        <div class="footer">
        </div>
    </div>
</body>
</html>
