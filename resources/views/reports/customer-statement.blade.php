<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Statement — {{ $customer->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #111827;
            font-size: 11px;
            margin: 0;
            padding: 24px;
        }
        h1 {
            font-size: 20px;
            margin: 0 0 8px 0;
            color: #1f2937;
        }
        h2 {
            font-size: 14px;
            margin: 0 0 16px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 4px;
        }
        .header {
            margin-bottom: 24px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .header td {
            border: none;
            padding: 0;
            vertical-align: top;
        }
        .business-info {
            text-align: right;
            font-size: 10px;
            color: #4b5563;
        }
        .customer-card {
            margin-top: 16px;
            margin-bottom: 24px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 12px;
            border-radius: 6px;
        }
        .customer-card table {
            width: 100%;
            border-collapse: collapse;
        }
        .customer-card td {
            border: none;
            padding: 3px 0;
            vertical-align: top;
        }
        .statement-summary {
            margin-bottom: 24px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background-color: #f3f4f6;
        }
        .statement-summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .statement-summary th {
            font-size: 9px;
            text-transform: uppercase;
            color: #4b5563;
            background-color: #e5e7eb;
            padding: 6px 12px;
            text-align: right;
            border-bottom: 1px solid #d1d5db;
        }
        .statement-summary th:first-child {
            text-align: left;
        }
        .statement-summary td {
            font-size: 13px;
            font-weight: bold;
            padding: 8px 12px;
            text-align: right;
        }
        .statement-summary td:first-child {
            text-align: left;
        }
        .ledger-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        .ledger-table th, .ledger-table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: middle;
        }
        .ledger-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            font-size: 10px;
            color: #374151;
            text-transform: uppercase;
        }
        .text-right {
            text-align: right !important;
        }
        .font-mono {
            font-family: monospace;
        }
        .footer {
            margin-top: 40px;
            font-size: 10px;
            text-align: center;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
        }
        @page {
            size: A4;
            margin: 20px;
        }
    </style>
</head>
<body>
    @php
        $settings = $business?->settings;
        $currencySymbol = $settings?->currency_symbol ?: config('retail.currency.symbol');
    @endphp

    <div class="header">
        <table>
            <tr>
                <td>
                    <h1>{{ $business->name ?? 'Retail POS' }}</h1>
                    <p style="margin: 0; font-size: 11px; color: #4b5563;">CUSTOMER STATEMENT</p>
                </td>
                <td class="business-info">
                    <p style="margin: 0; font-weight: bold;">{{ $business->legal_name ?? $business->name }}</p>
                    <p style="margin: 2px 0 0 0;">Statement Date: {{ now()->format('d M Y') }}</p>
                    <p style="margin: 2px 0 0 0;">Period: {{ $startDate->format('d M Y') }} to {{ $endDate->format('d M Y') }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="customer-card">
        <h2>Billed To</h2>
        <table>
            <tr>
                <td style="width: 12%; font-weight: bold; color: #4b5563;">Customer:</td>
                <td style="width: 38%; font-weight: bold;">{{ $customer->name }}</td>
                <td style="width: 12%; font-weight: bold; color: #4b5563;">Billing Address:</td>
                <td style="width: 38%;" rowspan="3">{{ $customer->billing_address ?: '—' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #4b5563;">Email:</td>
                <td>{{ $customer->email ?: '—' }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #4b5563;">Phone:</td>
                <td>{{ $customer->phone ?: '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="statement-summary">
        <table>
            <thead>
                <tr>
                    <th style="width: 33%;">Opening Balance</th>
                    <th style="width: 34%;">Total Activity</th>
                    <th style="width: 33%;">Amount Due</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $currencySymbol }}{{ number_format($openingBalance, 2) }}</td>
                    <td>
                        @php
                            $debits = array_sum(array_column($ledgerRows, 'debit'));
                            $credits = array_sum(array_column($ledgerRows, 'credit'));
                            $netActivity = $debits - $credits;
                        @endphp
                        {{ $netActivity >= 0 ? '+' : '' }}{{ $currencySymbol }}{{ number_format($netActivity, 2) }}
                    </td>
                    <td class="font-mono text-emerald-600" style="color: #059669;">{{ $currencySymbol }}{{ number_format($closingBalance, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <h2>Statement Activity</h2>
    <table class="ledger-table">
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 40%;">Description</th>
                <th style="width: 15%;" class="text-right">Debits (+)</th>
                <th style="width: 15%;" class="text-right">Credits (-)</th>
                <th style="width: 15%;" class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="color: #6b7280;">{{ $startDate->format('d M Y') }}</td>
                <td style="font-weight: bold; color: #4b5563;">Opening Balance</td>
                <td class="text-right">—</td>
                <td class="text-right">—</td>
                <td class="text-right font-mono font-medium">{{ $currencySymbol }}{{ number_format($openingBalance, 2) }}</td>
            </tr>
            @forelse ($ledgerRows as $row)
                <tr>
                    <td>{{ $row['date']->format('d M Y') }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="text-right font-mono">{{ $row['debit'] > 0 ? $currencySymbol . number_format($row['debit'], 2) : '—' }}</td>
                    <td class="text-right font-mono">{{ $row['credit'] > 0 ? $currencySymbol . number_format($row['credit'], 2) : '—' }}</td>
                    <td class="text-right font-mono font-medium">{{ $currencySymbol . number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #6b7280; font-style: italic;">
                        No account activity recorded within this statement period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Statement generated on {{ now()->format('d M Y H:i') }}. Please review all transactions. For queries, contact support.
    </div>
</body>
</html>
