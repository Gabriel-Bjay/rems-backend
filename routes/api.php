<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MaintenanceController;
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

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('tenancies/{id}/activate', [TenancyController::class, 'activate']);
    Route::post('tenancies/{id}/end', [TenancyController::class, 'end']);
    Route::post('payments/{id}/confirm', [PaymentController::class, 'confirm']);
    Route::post('maintenance-tickets/{id}/assign', [MaintenanceController::class, 'assign']);
    Route::post('maintenance-tickets/{id}/resolve', [MaintenanceController::class, 'resolve']);
    Route::post('listings/{id}/publish', [ListingController::class, 'publish']);
    Route::post('listings/{id}/take-down', [ListingController::class, 'takeDown']);

    Route::apiResource('owners', OwnerController::class);
    Route::apiResource('agents', AgentController::class);
    Route::apiResource('tenants', TenantController::class);
    Route::apiResource('properties', PropertyController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('unit-charges', UnitChargeController::class);
    Route::apiResource('tenancies', TenancyController::class);
    Route::apiResource('tenancy-charges', TenancyChargeController::class);
    Route::apiResource('deposits', DepositController::class);
    Route::apiResource('vacate-notices', VacateNoticesController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('refunds', RefundController::class);
    Route::apiResource('commissions', CommissionController::class);
    Route::apiResource('maintenance-tickets', MaintenanceController::class);
    Route::post('listings/{id}/approve', [ListingController::class, 'approve']);
    Route::apiResource('listings', ListingController::class);
});