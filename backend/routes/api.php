<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Invoice\InvoiceController;
use App\Http\Controllers\Api\V1\Ledger\LedgerController;
use App\Http\Controllers\Api\V1\Voucher\VoucherController;
use App\Http\Controllers\Api\V1\Report\ReportController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Public Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    // Protected routes
    Route::middleware(['auth:sanctum'])->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::put('profile', [AuthController::class, 'updateProfile']);
        });

        // Dashboard
        Route::get('dashboard', [ReportController::class, 'dashboard']);

        // Invoices
        Route::prefix('invoices')->group(function () {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::post('/', [InvoiceController::class, 'store']);
            Route::get('{id}', [InvoiceController::class, 'show']);
            Route::delete('{id}', [InvoiceController::class, 'destroy']);
            Route::post('{id}/process-accounting', [InvoiceController::class, 'processAccounting']);
        });

        // Ledgers
        Route::prefix('ledgers')->group(function () {
            Route::get('/', [LedgerController::class, 'index']);
            Route::post('/', [LedgerController::class, 'store']);
            Route::get('{id}', [LedgerController::class, 'show']);
            Route::put('{id}', [LedgerController::class, 'update']);
            Route::delete('{id}', [LedgerController::class, 'destroy']);
            Route::post('{id}/sync-tally', [LedgerController::class, 'syncToTally']);
        });

        // Vouchers
        Route::prefix('vouchers')->group(function () {
            Route::get('/', [VoucherController::class, 'index']);
            Route::get('{id}', [VoucherController::class, 'show']);
            Route::post('{id}/sync-tally', [VoucherController::class, 'syncToTally']);
            Route::post('bulk-sync', [VoucherController::class, 'bulkSync']);
            Route::get('{id}/download-xml', [VoucherController::class, 'downloadXml']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('sales', [ReportController::class, 'salesReport']);
            Route::get('purchase', [ReportController::class, 'purchaseReport']);
            Route::get('gst', [ReportController::class, 'gstReport']);
            Route::get('tally-failed', [ReportController::class, 'tallyFailedReport']);
            Route::get('audit', [ReportController::class, 'auditReport']);
        });

        // User management (admin only)
        Route::prefix('users')->middleware('role:admin')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::put('{id}', [UserController::class, 'update']);
            Route::delete('{id}', [UserController::class, 'destroy']);
        });
    });
});
