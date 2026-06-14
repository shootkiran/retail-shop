<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Count Sheet</title>
    <style>
        @page { margin: 18px 20px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 11px;
        }
        .header {
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
        }
        .meta {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            font-size: 10px;
            color: #4b5563;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 12px 0;
        }
        .card {
            border: 1px solid #d1d5db;
            padding: 8px 10px;
        }
        .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #6b7280;
        }
        .value {
            margin-top: 4px;
            font-size: 13px;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #374151;
        }
        .right { text-align: right; }
        .center { text-align: center; }
        .blank {
            color: transparent;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $business->name }} - Inventory Count Sheet</h1>
        <div class="meta">
            <div>Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
            <div>Filters: {{ collect($filters)->filter()->map(fn ($value, $key) => str_replace('_', ' ', $key) . ': ' . $value)->join(', ') ?: 'None' }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="card">
            <div class="label">Total SKUs</div>
            <div class="value">{{ number_format($summary['total_skus']) }}</div>
        </div>
        <div class="card">
            <div class="label">In stock</div>
            <div class="value">{{ number_format($summary['items_in_stock']) }}</div>
        </div>
        <div class="card">
            <div class="label">Low stock</div>
            <div class="value">{{ number_format($summary['low_stock_items']) }}</div>
        </div>
        <div class="card">
            <div class="label">Valuation</div>
            <div class="value">{{ config('retail.currency.symbol', 'रू') }} {{ number_format((float) $summary['total_valuation'], 2) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 14%;">Category</th>
                <th style="width: 24%;">Stock name</th>
                <th style="width: 12%;">System unit</th>
                <th style="width: 12%;" class="right">System qty</th>
                <th style="width: 18%;" class="center">Units in store</th>
                <th style="width: 20%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['category'] ?? 'Unassigned' }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['unit_symbol'] }}</td>
                    <td class="right">{{ number_format($row['system_quantity_display'], 2) }}</td>
                    <td class="center blank">____________________</td>
                    <td class="blank">____________________</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">No products match the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
