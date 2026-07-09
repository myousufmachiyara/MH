<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ChartOfAccountApiController;   // MISSING — add

Route::post('login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthApiController::class, 'me']);
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::post('change-password', [AuthApiController::class, 'changePassword']);

    Route::get('chart-of-accounts', [ChartOfAccountApiController::class, 'index'])->middleware('check.permission:coa.view');
    Route::get('account-mappings', [AccountMappingApiController::class, 'index'])->middleware('check.permission:coa.view');
    
});

