<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

Route::middleware([ForceJsonResponse::class])->prefix('v1')->as('api.')->group(function () {

    // --- Public ---
    Route::post('tokens/create', [AuthController::class, 'createToken'])
         ->middleware('throttle:token-creation');

    // --- Protected ---
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('tokens/revoke', [AuthController::class, 'revokeToken'])
            ->middleware('throttle:api');

        Route::middleware('throttle:api-reads')->group(function () {
            Route::apiResource('clients', ClientController::class)->only(['index', 'show']);
            Route::apiResource('projects', ProjectController::class)->only(['index', 'show']);
            Route::get('tags', [TagController::class, 'index']);
            Route::get('tags/{tag}', [TagController::class, 'show']);
        });

        Route::middleware('throttle:api')->group(function () {
            Route::apiResource('clients', ClientController::class)->except(['index', 'show']);
            Route::apiResource('projects', ProjectController::class)->except(['index', 'show']);
        });

    });

});
