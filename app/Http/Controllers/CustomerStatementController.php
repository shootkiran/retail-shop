<?php

namespace App\Http\Controllers;

use App\Models\Accounting\CreditNote;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Support\CurrentBusiness;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CustomerStatementController extends Controller
{
    public function show(Request $request, Customer $customer): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless($customer->business_id === app(CurrentBusiness::class)->id(), 404);

        $startDate = $request->input('startDate') ? Carbon::parse($request->input('startDate')) : now()->startOfMonth();
        $endDate = $request->input('endDate') ? Carbon::parse($request->input('endDate')) : now()->endOfMonth();

        // 1. Calculate opening balance (before startDate)
        $salesBefore = Sale::query()
            ->where('customer_id', $customer->id)
            ->whereDate('sold_at', '<', $startDate)
            ->sum('grand_total');

        $paymentsBefore = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->whereDate('payment_date', '<', $startDate)
            ->sum('amount');

        $creditsBefore = CreditNote::query()
            ->where('customer_id', $customer->id)
            ->whereDate('refunded_at', '<', $startDate)
            ->sum('grand_total');

        $openingBalance = round((float) $salesBefore - (float) $paymentsBefore - (float) $creditsBefore, 2);

        // 2. Fetch transactions within range
        $sales = Sale::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('sold_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->map(fn ($s) => [
                'date' => Carbon::parse($s->sold_at),
                'type' => 'invoice',
                'reference' => $s->reference,
                'description' => 'Invoice '.$s->reference,
                'debit' => (float) $s->grand_total,
                'credit' => 0.00,
            ]);

        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('payment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->map(fn ($p) => [
                'date' => Carbon::parse($p->payment_date),
                'type' => 'payment',
                'reference' => $p->reference,
                'description' => 'Payment '.$p->reference,
                'debit' => 0.00,
                'credit' => (float) $p->amount,
            ]);

        $credits = CreditNote::query()
            ->where('customer_id', $customer->id)
            ->whereBetween('refunded_at', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->map(fn ($c) => [
                'date' => Carbon::parse($c->refunded_at),
                'type' => 'credit_note',
                'reference' => $c->reference,
                'description' => 'Credit Note '.$c->reference,
                'debit' => 0.00,
                'credit' => (float) $c->grand_total,
            ]);

        // Combine and sort chronologically
        $transactions = collect()
            ->concat($sales)
            ->concat($payments)
            ->concat($credits)
            ->sortBy('date')
            ->values();

        // Calculate running balances
        $runningBalance = $openingBalance;
        $ledgerRows = [];

        foreach ($transactions as $tx) {
            if ($tx['debit'] > 0) {
                $runningBalance += $tx['debit'];
            } else {
                $runningBalance -= $tx['credit'];
            }

            $tx['balance'] = round($runningBalance, 2);
            $ledgerRows[] = $tx;
        }

        $pdf = Pdf::loadView('reports.customer-statement', [
            'customer' => $customer,
            'openingBalance' => $openingBalance,
            'closingBalance' => round($runningBalance, 2),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'ledgerRows' => $ledgerRows,
            'business' => $customer->business,
        ]);

        return $pdf->stream('statement-'.$customer->id.'.pdf');
    }
}
