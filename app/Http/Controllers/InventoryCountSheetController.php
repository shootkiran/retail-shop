<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\InventoryCountSheetService;
use App\Support\CurrentBusiness;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InventoryCountSheetController extends Controller
{
    public function __construct(protected InventoryCountSheetService $service)
    {
        //
    }

    public function __invoke(Request $request): Response
    {
        abort_unless(Auth::check(), 403);

        $business = app(CurrentBusiness::class)->get();
        abort_unless($business instanceof Business, 404);

        $filters = [
            'search' => $request->string('search')->toString(),
            'category_id' => $request->integer('category_id') ?: null,
            'vendor_id' => $request->integer('vendor_id') ?: null,
            'stock_status' => $request->string('stock_status')->toString() ?: 'all',
        ];

        $rows = $this->service->rows($filters);
        $summary = $this->service->summary($rows);

        $pdf = Pdf::loadView('invoices.inventory-count-sheet', [
            'business' => $business,
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $filters,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('inventory-count-sheet-' . now()->format('Y-m-d') . '.pdf');
    }
}
