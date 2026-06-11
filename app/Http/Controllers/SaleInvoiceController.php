<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Support\CurrentBusiness;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SaleInvoiceController extends Controller
{
    public function __invoke(Request $request, Sale $sale): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless($sale->business_id === app(CurrentBusiness::class)->id(), 404);

        $sale->loadMissing(['items.product', 'customer', 'paymentMethod', 'business.settings', 'terminal']);
        $showReceipt = $sale->amount_paid > 0 && $sale->payment_status !== 'pending';

        if ($request->boolean('print')) {
            return response()->view('invoices.sale', [
                'sale' => $sale,
                'autoPrint' => true,
                'showReceipt' => $showReceipt,
            ]);
        }

        $pdf = Pdf::loadView('invoices.sale', [
            'sale' => $sale,
            'showReceipt' => $showReceipt,
        ])->setPaper('a5');

        return $pdf->stream('invoice-' . $sale->reference . '.pdf');
    }
}
