<?php

use App\Http\Controllers\Api\AccountMappingApiController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('account-mappings', [AccountMappingApiController::class, 'index']);
    Route::put('account-mappings', [AccountMappingApiController::class, 'update']);
});