<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ProjectController;
use Illuminate\Support\Facades\Route;

// Public routes — no token required
Route::prefix('v1')->group(function () {

    // Token management
    Route::post('/tokens/create', [AuthController::class, 'createToken']);

});

// Protected routes — Sanctum token required
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    Route::post('/tokens/revoke', [AuthController::class, 'revokeToken']);

    // Clients
    Route::apiResource('clients', ClientController::class);

    // Projects
    Route::apiResource('projects', ProjectController::class);

});