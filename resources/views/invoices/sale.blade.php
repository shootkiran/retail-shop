<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->reference }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border-bottom: 1px solid #d1d5db; text-align: left; }
        th { background-color: #f3f4f6; font-weight: 600; }
        .text-right { text-align: right; }
        .totals { margin-top: 16px; }
        .totals table { width: 50%; margin-left: auto; }
        .totals td { border-bottom: none; }
        .footer { margin-top: 24px; font-size: 11px; text-align: center; color: #6b7280; }
    </style>
</head>
<body>
    <div>
        <h1>Retail POS Invoice</h1>
        <p style="margin: 0;">Reference: <strong>{{ $sale->reference }}</strong></p>
        <p style="margin: 0;">Date: {{ optional($sale->sold_at)->format('d M Y H:i') ?? now()->format('d M Y H:i') }}</p>
    </div>

    <div style="margin-top: 16px; display: flex; justify-content: space-between;">
        <div>
            <h3 style="margin: 0 0 4px 0; font-size: 14px;">Billed To</h3>
            <p style="margin: 0;">{{ $sale->customer->name ?? 'Walk-in Customer' }}</p>
        </div>
        <div style="text-align: right;">
            <h3 style="margin: 0 0 4px 0; font-size: 14px;">Payment</h3>
            <p style="margin: 0;">Type: {{ ucfirst($sale->payment_type) }}</p>
            <p style="margin: 0;">Method: {{ $sale->paymentMethod->name ?? 'N/A' }}</p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Discount</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? 'Product' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>₦{{ number_format($item->unit_price, 2) }}</td>
                    <td>₦{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="text-right">₦{{ number_format(($item->quantity * $item->unit_price) - $item->discount_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">₦{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Discounts</td>
                <td class="text-right">₦{{ number_format($sale->discount_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="text-right">₦{{ number_format($sale->tax_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Grand Total</td>
                <td class="text-right" style="font-weight: 600;">₦{{ number_format($sale->grand_total, 2) }}</td>
            </tr>
            <tr>
                <td>Amount Paid</td>
                <td class="text-right">₦{{ number_format($sale->amount_paid, 2) }}</td>
            </tr>
            <tr>
                <td>Balance Due</td>
                <td class="text-right">₦{{ number_format($sale->amount_due, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Thank you for shopping with us!
    </div>
</body>
</html>
