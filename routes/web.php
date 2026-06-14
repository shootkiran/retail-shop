<?php

use App\Http\Controllers\CustomerStatementController;
use App\Http\Controllers\InventoryCountSheetController;
use App\Http\Controllers\SaleInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('invoices/sales/{sale}', SaleInvoiceController::class)
    ->name('sales.invoice');

Route::get('inventory/count-sheet.pdf', InventoryCountSheetController::class)
    ->name('inventory.count-sheet.pdf');

Route::get('customers/{customer}/statement', [CustomerStatementController::class, 'show'])
    ->name('customers.statement');

Route::impersonate();

Route::redirect('/', '/admin');
