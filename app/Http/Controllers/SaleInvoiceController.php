<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SaleInvoiceController extends Controller
{
    public function __invoke(Request $request, Sale $sale): Response
    {
        $sale->loadMissing(['items.product', 'customer', 'paymentMethod']);

        if ($request->boolean('print')) {
            return response()->view('invoices.sale', [
                'sale' => $sale,
                'autoPrint' => true,
            ]);
        }

        $pdf = Pdf::loadView('invoices.sale', [
            'sale' => $sale,
        ])->setPaper('a5');

        return $pdf->stream('invoice-' . $sale->reference . '.pdf');
    }
}
