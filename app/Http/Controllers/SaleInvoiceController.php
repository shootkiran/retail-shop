<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleInvoiceController extends Controller
{
    public function __invoke(Sale $sale): BinaryFileResponse
    {
        $sale->loadMissing(['items.product', 'customer', 'paymentMethod']);

        $pdf = Pdf::loadView('invoices.sale', [
            'sale' => $sale,
        ])->setPaper('a5');

        return $pdf->download('invoice-' . $sale->reference . '.pdf');
    }
}
