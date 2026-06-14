<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\ApartmentController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('owners', OwnerController::class);
    Route::apiResource('agents', AgentController::class);
    Route::apiResource('tenants', TenantController::class);
    Route::apiResource('apartments', ApartmentController::class);
});