<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\TenancyChargeController;
use App\Http\Controllers\TenancyController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UnitChargeController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\VacateNoticesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\PaymentAllocationController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('dashboard', [DashboardController::class, 'summary']);

    // Readable by every signed-in user. Each controller returns only the rows
    // that user may see (see App\Services\Access).
    Route::apiResource('properties', PropertyController::class)->only(['index', 'show']);
    Route::apiResource('units', UnitController::class)->only(['index', 'show']);
    Route::apiResource('tenancies', TenancyController::class)->only(['index', 'show']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);
    Route::apiResource('deposits', DepositController::class)->only(['index', 'show']);

    // Tenants may also report a payment and raise a maintenance request.
    Route::apiResource('payments', PaymentController::class)->only(['index', 'show', 'store']);
    Route::apiResource('maintenance-tickets', MaintenanceController::class)->only(['index', 'show', 'store']);

    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/mark-read', [NotificationController::class, 'markRead']);
    Route::apiResource('notifications', NotificationController::class)->only(['index', 'show', 'update']);

    // Day-to-day operations: admins and agents.
    Route::middleware('role:admin,agent')->group(function () {
        Route::apiResource('tenants', TenantController::class);
        Route::apiResource('properties', PropertyController::class)->except(['index', 'show']);
        Route::apiResource('units', UnitController::class)->except(['index', 'show']);
        Route::apiResource('unit-charges', UnitChargeController::class);

        Route::post('tenancies/{id}/activate', [TenancyController::class, 'activate']);
        Route::post('tenancies/{id}/end', [TenancyController::class, 'end']);
        Route::apiResource('tenancies', TenancyController::class)->except(['index', 'show']);
        Route::apiResource('tenancy-charges', TenancyChargeController::class);
        Route::apiResource('deposits', DepositController::class)->except(['index', 'show']);
        Route::apiResource('vacate-notices', VacateNoticesController::class);

        Route::post('invoices/generate', [InvoiceController::class, 'generate']);
        Route::post('invoices/{id}/void', [InvoiceController::class, 'void']);
        Route::apiResource('invoices', InvoiceController::class)->except(['index', 'show']);
        Route::apiResource('invoice-items', InvoiceItemController::class);

        Route::post('payments/{id}/confirm', [PaymentController::class, 'confirm']);
        Route::apiResource('payments', PaymentController::class)->only(['update', 'destroy']);
        Route::apiResource('payment-allocations', PaymentAllocationController::class);

        Route::post('maintenance-tickets/{id}/assign', [MaintenanceController::class, 'assign']);
        Route::post('maintenance-tickets/{id}/resolve', [MaintenanceController::class, 'resolve']);
        Route::apiResource('maintenance-tickets', MaintenanceController::class)->only(['update', 'destroy']);

        Route::post('listings/{id}/publish', [ListingController::class, 'publish']);
        Route::post('listings/{id}/take-down', [ListingController::class, 'takeDown']);
        Route::apiResource('listings', ListingController::class);
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('listings/{id}/approve', [ListingController::class, 'approve']);
        Route::apiResource('owners', OwnerController::class);
        Route::apiResource('agents', AgentController::class);
        Route::apiResource('refunds', RefundController::class);
        Route::apiResource('commissions', CommissionController::class);
        Route::apiResource('notifications', NotificationController::class)->only(['store', 'destroy']);
    });
});
