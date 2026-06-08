<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $sale->reference }}</title>
    <style>
        @font-face {
            font-family: 'Noto Sans Devanagari';
            font-style: normal;
            font-weight: 400;
            src: url('{{ resource_path('fonts/NotoSansDevanagari-Regular.ttf') }}') format('truetype');
        }

        @font-face {
            font-family: 'Noto Sans Devanagari';
            font-style: normal;
            font-weight: 600;
            src: url('{{ resource_path('fonts/NotoSansDevanagari-Bold.ttf') }}') format('truetype');
        }

        body { font-family: 'Noto Sans Devanagari', DejaVu Sans, sans-serif; color: #111827; font-size: 12px; margin: 0; padding: 24px; }
        h1 { font-size: 20px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border-bottom: 1px solid #d1d5db; text-align: left; }
        th { background-color: #f3f4f6; font-weight: 600; }
        .text-right { text-align: right; }
        .totals { margin-top: 16px; }
        .totals table { width: 50%; margin-left: auto; }
        .totals td { border-bottom: none; }
        .footer { margin-top: 24px; font-size: 11px; text-align: center; color: #6b7280; }

        @page { size: A5; margin: 0; }
    </style>
</head>
<body>
    @php
        $business = $sale->business;
        $settings = $business?->settings;
        $currencySymbol = $settings?->currency_symbol ?: config('retail.currency.symbol');
        $timezone = $settings?->timezone ?: config('retail.timezone');
        $soldAt = $sale->sold_at ? $sale->sold_at->timezone($timezone) : now()->timezone($timezone);
    @endphp

    <div>
        <h1>{{ $business->name ?? 'Retail POS' }} Invoice</h1>
        <p style="margin: 0;">Reference: <strong>{{ $sale->reference }}</strong></p>
        <p style="margin: 0;">Date: {{ $soldAt->format($settings?->date_format ?: 'd M Y') }} {{ $soldAt->format($settings?->time_format ?: 'H:i') }}</p>
        @if ($sale->terminal)
            <p style="margin: 0;">Terminal: {{ $sale->terminal->name }}</p>
        @endif
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
                    <td>{{ $currencySymbol }}{{ number_format($item->unit_price, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($item->discount_amount, 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format(($item->quantity * $item->unit_price) - $item->discount_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($sale->total_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Discounts</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($sale->discount_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($sale->tax_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="font-weight: 600;">Grand Total</td>
                <td class="text-right" style="font-weight: 600;">{{ $currencySymbol }}{{ number_format($sale->grand_total, 2) }}</td>
            </tr>
            <tr>
                <td>Amount Paid</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($sale->amount_paid, 2) }}</td>
            </tr>
            @if ($sale->amount_due > 0)
                <tr>
                    <td>Balance Due</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($sale->amount_due, 2) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        Thank you for shopping with us!
    </div>

    @if ($autoPrint ?? false)
        <script>
            window.addEventListener('load', () => {
                window.print();
            });
        </script>
    @endif
</body>
</html>
