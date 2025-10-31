<?php

use App\Http\Controllers\SaleInvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('invoices/sales/{sale}', SaleInvoiceController::class)
    ->name('sales.invoice');

Route::redirect('/', '/admin');
