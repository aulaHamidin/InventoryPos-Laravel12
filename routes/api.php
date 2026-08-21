<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\ItemSupplierController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ShoppingListController;
use App\Http\Controllers\Api\StockOpnameController;
use App\Http\Controllers\ReportController;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', SetTenantContext::class])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/items', [InventoryController::class, 'index']);
        Route::get('/items/scan/{barcode}', [InventoryController::class, 'scan']);
        Route::post('/items/{id}/smart-threshold', [InventoryController::class, 'applySmartThreshold']);
        Route::post('/items/{item}/suppliers', [ItemSupplierController::class, 'store']);
        Route::get('/items/{item}/suppliers', [ItemSupplierController::class, 'index']);
        Route::post('/item-suppliers/{link}/preferred', [ItemSupplierController::class, 'preferred']);
        Route::put('/item-suppliers/{link}', [ItemSupplierController::class, 'update']);
        Route::delete('/item-suppliers/{link}', [ItemSupplierController::class, 'destroy']);

        Route::post('/stock/movements/in', [InventoryController::class, 'stockIn']);
        Route::post('/stock/movements/out', [InventoryController::class, 'stockOut']);
        Route::post('/stock/movements/adjustment', [InventoryController::class, 'adjustStock']);

        Route::get('/opname', [StockOpnameController::class, 'index']);
        Route::post('/opname', [StockOpnameController::class, 'store']);
        Route::put('/opname/{id}/details', [StockOpnameController::class, 'updateDetails']);
        Route::post('/opname/{id}/finalize', [StockOpnameController::class, 'finalize']);

        Route::post('/pos/checkout', [PosController::class, 'checkout']);
        Route::post('/pos/transactions/{id}/pay-cash', [PosController::class, 'payCash']);
        Route::post('/pos/transactions/{id}/pay-manual', [PosController::class, 'payManual']);
        Route::post('/pos/transactions/{id}/void', [PosController::class, 'void']);
        Route::post('/pos/transactions/{id}/return', [PosController::class, 'returnItems']);
        Route::post('/pos/payments/{id}/mark-refunded', [PosController::class, 'markRefunded']);
        Route::get('/pos/transactions/{id}/status', [PosController::class, 'status']);

        Route::get('/shopping-lists', [ShoppingListController::class, 'index']);
        Route::post('/shopping-lists/generate', [ShoppingListController::class, 'generate']);
        Route::get('/shopping-lists/{id}', [ShoppingListController::class, 'show']);
        Route::post('/shopping-lists/{id}/submit', [ShoppingListController::class, 'submit']);
        Route::post('/shopping-lists/{id}/receive', [ShoppingListController::class, 'receive']);

        Route::post('/reports/exports', [ReportController::class, 'queue']);
        Route::get('/reports/exports/{export}', [ReportController::class, 'status']);
        Route::get('/reports/exports/{export}/download', [ReportController::class, 'download']);
    });
});
