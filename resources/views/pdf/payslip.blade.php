<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payslip - {{ $pay_period }}</title>
    <style>
        @page {
            margin: 20px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
            font-size: 11px;
            line-height: 1.4;
        }
        .container {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            background-color: #ffffff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 20px;
            font-weight: bold;
            color: #0b1941;
            letter-spacing: 0.5px;
        }
        .payslip-badge {
            display: inline-block;
            background-color: #eff6ff;
            color: #2563eb;
            font-size: 11px;
            font-weight: bold;
            padding: 4px 12px;
            border-radius: 12px;
            margin-top: 4px;
        }
        .metrics-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .metric-label {
            font-size: 9px;
            color: #64748b;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .metric-value {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }
        .metric-value-blue {
            font-size: 13px;
            font-weight: bold;
            color: #2563eb;
        }
        .info-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }
        .card-title {
            font-size: 10px;
            font-weight: bold;
            color: #0b1941;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 6px;
        }
        .info-table td {
            padding: 4px 0;
            font-size: 11px;
        }
        .info-label {
            color: #64748b;
        }
        .info-val {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }
        .table-section-title {
            font-size: 10px;
            font-weight: bold;
            color: #0b1941;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .component-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .component-table th {
            background-color: #0b1941;
            color: #ffffff;
            font-weight: bold;
            padding: 8px 12px;
            font-size: 10px;
        }
        .component-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .component-table tfoot td {
            background-color: #f8fafc;
            font-weight: bold;
            color: #0b1941;
            padding: 10px 12px;
            border-bottom: none;
            font-size: 11px;
        }
        .banner-card {
            background-color: #0b1941;
            border-radius: 10px;
            padding: 16px 20px;
            color: #ffffff;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .banner-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .banner-subtitle {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 3px;
        }
        .banner-amount {
            font-size: 22px;
            font-weight: bold;
            color: #ffffff;
            text-align: right;
        }
        .footer-table {
            border-top: 1px solid #f1f5f9;
            padding-top: 12px;
            font-size: 9px;
            color: #64748b;
        }
        .blue-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            background-color: #2563eb;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <table>
        <tr>
            <td style="vertical-align: middle;">
                <img src="https://mostech.ae/img/logo.webp" style="width: 240px; height: 36px;"/>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                <div style="font-size: 22px; font-weight: bold; color: #0b1941; letter-spacing: 1px;">PAYSLIP</div>
                <div class="payslip-badge">
                    #PS{{ str_pad($payroll_id, 6, '0', STR_PAD_LEFT) }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Top Metrics Card -->
    <div class="metrics-card">
        <table>
            <tr>
                <td style="width: 25%;">
                    <div class="metric-label">PAY PERIOD</div>
                    <div class="metric-value">{{ $pay_period }}</div>
                </td>
                <td style="width: 25%;">
                    <div class="metric-label">DATE OF JOINING</div>
                    <div class="metric-value">{{ !empty($employee['joining_date']) ? \Carbon\Carbon::parse($employee['joining_date'])->format('d-m-Y') : 'N/A' }}</div>
                </td>
                <td style="width: 25%;">
                    <div class="metric-label">WORKED DAYS</div>
                    <div class="metric-value">{{ $days_present > 0 ? $days_present : $working_days }} / {{ $total_days }} Days</div>
                </td>
                <td style="width: 25%;">
                    <div class="metric-label">NET DISBURSEMENT</div>
                    <div class="metric-value-blue">{{ $currency_symbol }}{{ number_format($net_pay, 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Employee Profile & Disbursement Info -->
    <table style="margin-bottom: 20px;">
        <tr>
            <td style="width: 49%; vertical-align: top;">
                <div class="info-card">
                    <div class="card-title">EMPLOYEE PROFILE</div>
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Employee Name</td>
                            <td class="info-val">{{ $employee['name'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Employee ID</td>
                            <td class="info-val">{{ $employee['employee_code'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Designation</td>
                            <td class="info-val">{{ $employee['designation']['name'] ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%; vertical-align: top;">
                <div class="info-card">
                    <div class="card-title">DISBURSEMENT INFO</div>
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Bank Name</td>
                            <td class="info-val">{{ $disbursement['bank_name'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Account Number</td>
                            <td class="info-val">{{ $disbursement['account_number'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">IFSC / Branch</td>
                            <td class="info-val">{{ $disbursement['ifsc_branch'] ?? 'N/A' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- Earnings & Deductions Tables -->
    <table style="margin-bottom: 20px;">
        <tr>
            <!-- Earnings Breakdown -->
            <td style="width: 49%; vertical-align: top;">
                <div class="table-section-title">EARNINGS BREAKDOWN</div>
                <table class="component-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; border-top-left-radius: 6px;">Component</th>
                            <th style="text-align: right; border-top-right-radius: 6px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($earnings['components'] as $comp)
                            <tr>
                                <td style="color: #475569;">{{ $comp['name'] }}</td>
                                <td style="text-align: right; color: #0f172a;">{{ $currency_symbol }}{{ number_format($comp['amount'], 2) }}</td>
                            </tr>
                        @empty
                        @endforelse

                        @if(($earnings['overtime_amount'] ?? 0) > 0)
                            <tr>
                                <td style="color: #475569;">Overtime</td>
                                <td style="text-align: right; color: #0f172a;">{{ $currency_symbol }}{{ number_format($earnings['overtime_amount'], 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="border-bottom-left-radius: 6px;">Total Earnings</td>
                            <td style="text-align: right; border-bottom-right-radius: 6px;">{{ $currency_symbol }}{{ number_format($earnings['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>

            <td style="width: 2%;"></td>

            <!-- Deductions Breakdown -->
            <td style="width: 49%; vertical-align: top;">
                <div class="table-section-title">DEDUCTIONS</div>
                <table class="component-table">
                    <thead>
                        <tr>
                            <th style="text-align: left; border-top-left-radius: 6px;">Component</th>
                            <th style="text-align: right; border-top-right-radius: 6px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deductions['components'] as $ded)
                            <tr>
                                <td style="color: #475569;">{{ $ded['name'] }}</td>
                                <td style="text-align: right; color: #0f172a;">{{ $currency_symbol }}{{ number_format($ded['amount'], 2) }}</td>
                            </tr>
                        @empty
                        @endforelse

                        @if(($leave_summary['total_leave_days'] ?? 0) > 0)
                            <tr>
                                <td style="color: #475569;">Leave (LOP: {{ $leave_summary['total_leave_days'] }} Days)</td>
                                <td style="text-align: right; color: #0f172a;">-</td>
                            </tr>
                        @endif
                    </tbody>
                    <tfoot>
                        <tr>
                            <td style="border-bottom-left-radius: 6px;">Total Deductions</td>
                            <td style="text-align: right; border-bottom-right-radius: 6px;">{{ $currency_symbol }}{{ number_format($deductions['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
    </table>

    <!-- Final Net Payable Banner -->
    <div class="banner-card">
        <table>
            <tr>
                <td style="vertical-align: middle;">
                    <div class="banner-title">FINAL NET PAYABLE AMOUNT</div>
                    <div class="banner-subtitle">Disbursed directly to designated account</div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div class="banner-amount">{{ $currency_symbol }}{{ number_format($net_pay, 2) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Footer -->
    <table class="footer-table">
        <tr>
            <td style="vertical-align: middle;">
                <span class="blue-dot"></span>
                <strong style="color: #0f172a;">System Generated Document</strong>
            </td>
            <td style="text-align: right; vertical-align: middle;">
                This is an official digital payslip and does not require a physical signature.
            </td>
        </tr>
    </table>
</div>

</body>
</html>