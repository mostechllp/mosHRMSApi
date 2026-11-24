<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Final Settlement Letter</title>
    <style>
        @page { size: A4 portrait; margin: 18mm 20mm; }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 10.5pt;
            line-height: 1.6;
            color: #1a1a2e;
            margin: 0; padding: 0;
        }

        .header-bar { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .header-bar td { border: none; vertical-align: middle; }
        .company-name {
            font-size: 16pt; font-weight: bold;
            color: #1e3a5f; text-transform: uppercase; letter-spacing: 1px;
        }
        .company-sub { font-size: 8.5pt; color: #555; margin-top: 2px; }
        .doc-label {
            text-align: right;
            font-size: 13pt; font-weight: bold;
            color: #1e3a5f; letter-spacing: 2px; text-transform: uppercase;
        }
        .header-rule { border: none; border-top: 3px solid #1e3a5f; margin: 0 0 14px 0; }

        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 22px; font-size: 9pt; }
        .meta-table td { border: none; padding: 1px 0; }
        .meta-label { color: #555; width: 140px; }
        .meta-val   { font-weight: bold; color: #1a1a2e; }
        .meta-right { text-align: right; }

        .body-para { margin-bottom: 12px; text-align: justify; }

        /* Employee info block */
        .info-block {
            border-left: 4px solid #1e3a5f;
            background: #f0f4ff;
            padding: 10px 14px;
            margin: 14px 0;
        }
        .info-block table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        .info-block table td { padding: 4px 0; border: none; }
        .ib-label { color: #555; width: 180px; }
        .ib-val   { font-weight: bold; }

        /* Settlement table */
        .settlement-table {
            width: 100%; border-collapse: collapse;
            margin: 16px 0; font-size: 9.5pt;
        }
        .settlement-table thead tr th {
            background: #1e3a5f; color: #fff;
            padding: 7px 10px; text-align: left;
            font-weight: bold; font-size: 9pt;
        }
        .settlement-table tbody tr td {
            padding: 6px 10px; border-bottom: 1px solid #e0e0e0;
        }
        .settlement-table tbody tr.deduction-subrow td {
            padding-left: 25px; font-size: 9pt; color: #555; background: #fafafa;
        }
        .settlement-table tbody tr.total-row td {
            font-weight: bold; background: #eef2ff;
            border-top: 2px solid #1e3a5f; border-bottom: none;
        }
        .amount-col { text-align: right; }

        .sig-section { margin-top: 36px; }
        .sig-label   { font-size: 9pt; color: #555; }
        .sig-name    { font-weight: bold; margin-top: 28px; font-size: 10pt; }
        .sig-title   { font-size: 9pt; color: #555; }

        .footer {
            position: absolute; bottom: 0; left: 0; right: 0;
            border-top: 1px solid #ccc;
            padding: 6px 20mm;
            font-size: 7.5pt; color: #888; text-align: center;
        }
    </style>
</head>
<body>

@php
    $employee        = $offboarding->employee;
    $user            = $employee?->user;
    $company         = $employee?->company ?? $user?->company;
    $companyName     = $company?->name ?? 'Mostech LLP';
    $companySub      = 'HR & Payroll Services';

    $empCode         = $employee->employee_id ?? 'N/A';
    $empName         = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
    $designation     = $user?->designation?->name ?? 'N/A';
    $department      = $user?->department?->name ?? 'N/A';
    $joiningDate     = $employee->joining_date
        ? \Carbon\Carbon::parse($employee->joining_date)->format('d M Y')
        : 'N/A';
    $lastWorkingDay  = $offboarding->last_working_day
        ? \Carbon\Carbon::parse($offboarding->last_working_day)->format('d M Y')
        : 'N/A';
    $issueDate       = now()->format('d M Y');
    $refNo           = 'FS/' . now()->format('Y') . '/' . str_pad($offboarding->id, 4, '0', STR_PAD_LEFT);

    // Settlement figures
    $settlement      = $offboarding->settlement;
    $totalPayable    = $settlement ? (float) $settlement->total_payable    : 0;
    $totalDeductions = $settlement ? (float) $settlement->total_deductions : 0;
    $netPayable      = $settlement ? (float) $settlement->net_payable      : 0;
    $remarks         = $settlement?->remarks ?? '';
    $deductions      = is_array($settlement?->deductions) ? $settlement->deductions : [];

    $managerEmployee = $offboarding->reportingManager;
    $managerName     = trim(($managerEmployee?->first_name ?? '') . ' ' . ($managerEmployee?->last_name ?? ''));
    $managerDesig    = $managerEmployee?->user?->designation?->name ?? 'HR Manager';

    $currency        = 'INR';
@endphp

<table class="header-bar">
    <tr>
        <td>
            <div class="company-name">{{ $companyName }}</div>
            <div class="company-sub">{{ $companySub }}</div>
        </td>
        <td class="doc-label">Final Settlement</td>
    </tr>
</table>
<hr class="header-rule">

<table class="meta-table">
    <tr>
        <td class="meta-label">Reference No.</td>
        <td class="meta-val">{{ $refNo }}</td>
        <td class="meta-right">Date: <strong>{{ $issueDate }}</strong></td>
    </tr>
</table>

<p class="body-para">To Whom It May Concern,</p>

<p class="body-para">
    This document certifies the final settlement of accounts for <strong>{{ $empName }}</strong>
    upon separation from <strong>{{ $companyName }}</strong>. Below is the summary of service details and settlement breakdown.
</p>

<div class="info-block">
    <table>
        <tr>
            <td class="ib-label">Employee ID</td>
            <td class="ib-val">{{ $empCode }}</td>
        </tr>
        <tr>
            <td class="ib-label">Employee Name</td>
            <td class="ib-val">{{ $empName }}</td>
        </tr>
        <tr>
            <td class="ib-label">Designation</td>
            <td class="ib-val">{{ $designation }}</td>
        </tr>
        <tr>
            <td class="ib-label">Department</td>
            <td class="ib-val">{{ $department }}</td>
        </tr>
        <tr>
            <td class="ib-label">Date of Joining</td>
            <td class="ib-val">{{ $joiningDate }}</td>
        </tr>
        <tr>
            <td class="ib-label">Last Working Day</td>
            <td class="ib-val">{{ $lastWorkingDay }}</td>
        </tr>
    </table>
</div>

<!-- Settlement Breakdown Table -->
<table class="settlement-table">
    <thead>
        <tr>
            <th>Description</th>
            <th class="amount-col">Amount ({{ $currency }})</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Total Earnings / Earnings Payable</strong> (Gratuity, Encashment, Overtime, Dues)</td>
            <td class="amount-col"><strong>{{ number_format($totalPayable, 2) }}</strong></td>
        </tr>

        @if(count($deductions) > 0)
            <tr>
                <td colspan="2"><strong>Deductions Breakdown:</strong></td>
            </tr>
            @foreach($deductions as $ded)
                <tr class="deduction-subrow">
                    <td>&bull; {{ $ded['name'] ?? ($ded['description'] ?? 'Deduction Item') }}</td>
                    <td class="amount-col">- {{ number_format((float) ($ded['amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        @endif

        <tr>
            <td><strong>Total Deductions</strong></td>
            <td class="amount-col"><strong>- {{ number_format($totalDeductions, 2) }}</strong></td>
        </tr>
        <tr class="total-row">
            <td>Net Settlement Amount Payable</td>
            <td class="amount-col">{{ number_format($netPayable, 2) }}</td>
        </tr>
    </tbody>
</table>

@if($remarks)
<p class="body-para"><strong>Remarks:</strong> {{ $remarks }}</p>
@endif

<p class="body-para">
    The net settlement amount of <strong>{{ $currency }} {{ number_format($netPayable, 2) }}</strong>
    has been calculated and processed. Upon receipt of this settlement, all financial obligations between the employee and {{ $companyName }} are considered fully discharged.
</p>

<div class="sig-section">
    <div class="sig-label">Authorised Signatory</div>
    <div class="sig-name">{{ $managerName ?: 'HR Department' }}</div>
    <div class="sig-title">{{ $managerDesig }} · {{ $companyName }}</div>
</div>

<div class="footer">
    {{ $companyName }} · This is a system-generated document and does not require a physical signature.
</div>

</body>
</html>
