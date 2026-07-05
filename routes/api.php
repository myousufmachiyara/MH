<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;

Route::post('login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthApiController::class, 'me']);
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::post('change-password', [AuthApiController::class, 'changePassword']);

    // Example for module APIs — guard each with its permission:
    Route::get('chart-of-accounts', [ChartOfAccountApiController::class, 'index'])->middleware('check.permission:coa.view');
});

