<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ChartOfAccountApiController;   // MISSING — add
use App\Http\Controllers\Api\VendorApiController;
use App\Http\Controllers\Api\CustomerApiController;

Route::post('login', [AuthApiController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthApiController::class, 'me']);
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::post('change-password', [AuthApiController::class, 'changePassword']);

    Route::get('chart-of-accounts', [ChartOfAccountApiController::class, 'index'])->middleware('check.permission:coa.view');
    Route::get('account-mappings', [AccountMappingApiController::class, 'index'])->middleware('check.permission:coa.view');

    Route::get('vendors',        [VendorApiController::class, 'index'])   ->middleware('check.permission:vendors.index');
    Route::get('vendors/search', [VendorApiController::class, 'search'])  ->middleware('check.permission:vendors.index');
    Route::get('vendors/{id}',   [VendorApiController::class, 'show'])    ->middleware('check.permission:vendors.index');
    Route::post('vendors',       [VendorApiController::class, 'store'])   ->middleware('check.permission:vendors.create');
    Route::put('vendors/{id}',   [VendorApiController::class, 'update'])  ->middleware('check.permission:vendors.edit');
    Route::delete('vendors/{id}',[VendorApiController::class, 'destroy']) ->middleware('check.permission:vendors.delete');

    Route::get('customers',        [CustomerApiController::class, 'index'])   ->middleware('check.permission:customers.index');
    Route::get('customers/search', [CustomerApiController::class, 'search'])  ->middleware('check.permission:customers.index');
    Route::get('customers/{id}',   [CustomerApiController::class, 'show'])    ->middleware('check.permission:customers.index');
    Route::post('customers',       [CustomerApiController::class, 'store'])   ->middleware('check.permission:customers.create');
    Route::put('customers/{id}',   [CustomerApiController::class, 'update'])  ->middleware('check.permission:customers.edit');
    Route::delete('customers/{id}',[CustomerApiController::class, 'destroy']) ->middleware('check.permission:customers.delete');
    
});

