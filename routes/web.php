<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,

    // Accounts
    SubHeadOfAccController,
    COAController,
    AccountMappingController,

    // User management
    UserController,
    RoleController,
    PermissionController,

    // Product master
    ProductController,
    ProductCategoryController,
    ProductSubcategoryController,
    AttributeController,
    MeasurementUnitController,

    // Parties
    VendorController,
    CustomerController,

    // Core flow
    OrderController,
    PurchaseInvoiceController,
    PurchaseOrderController,
    PurchaseReturnController,
    GatePassController,
    JobOrderController,
    JobOrderReceiveController,
    SaleController,

    // Finance
    VoucherController,
    ExpenseController,

    // Reports
    InventoryReportController,
    PurchaseReportController,
    SalesReportController,
    AccountsReportController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    // ────────────────────────────────────────────────────────────────
    // DASHBOARD
    // ────────────────────────────────────────────────────────────────
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ────────────────────────────────────────────────────────────────
    // USER MANAGEMENT — standalone helpers
    // ────────────────────────────────────────────────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::put('{id}/change-password', [UserController::class, 'changePassword'])->name('changePassword');
        Route::put('{id}/toggle-active',   [UserController::class, 'toggleActive'])->name('toggleActive');
        Route::post('change-my-password',  [UserController::class, 'changeMyPassword'])->name('changeMyPassword');
    });

    // ────────────────────────────────────────────────────────────────
    // ACCOUNT MAPPINGS
    // ────────────────────────────────────────────────────────────────
    Route::get('accounts/mapping', [AccountMappingController::class, 'index'])->name('account-mappings.index');
    Route::put('accounts/mapping', [AccountMappingController::class, 'update'])->name('account-mappings.update');

    // ────────────────────────────────────────────────────────────────
    // HELPERS (AJAX endpoints for dropdowns/search)
    // ────────────────────────────────────────────────────────────────
    Route::prefix('helpers')->name('helpers.')->group(function () {
        Route::get('products/details',              [ProductController::class, 'details'])->name('product.details');
        Route::get('products/{product}/variations', [ProductController::class, 'getVariations'])->name('product.variations');
        Route::get('categories/{category}/subcategories', [ProductCategoryController::class, 'getSubcategories'])->name('category.subcategories');

        Route::get('vendors/search',   [VendorController::class, 'search'])->name('vendors.search');
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');

        Route::get('accounts/search',  [COAController::class, 'search'])->name('accounts.search');
    });

    // ────────────────────────────────────────────────────────────────
    // STANDARD CRUD MODULES (auto index/create/store/show/edit/update/destroy/print)
    //
    // NOTE: 'purchase', 'jobs', 'job_receives' are DELIBERATELY EXCLUDED
    // from this generic loop — each has its own dedicated, fully-built
    // route block further down (PurchaseInvoice/Order/Return, JobOrder,
    // JobOrderReceive). Including them here would shadow those routes.
    // ────────────────────────────────────────────────────────────────

    $modules = [
        // User management
        'roles'                 => [RoleController::class,               'user_roles'],
        'permissions'            => [PermissionController::class,        'user_roles'],
        'users'                  => [UserController::class,               'users'],

        // Accounts master
        'coa'                    => [COAController::class,                'coa'],
        'shoa'                   => [SubHeadOfAccController::class,       'shoa'],

        // Product master
        'products'               => [ProductController::class,            'products'],
        'product_categories'     => [ProductCategoryController::class,    'product_categories'],
        'product_subcategories'  => [ProductSubcategoryController::class, 'product_subcategories'],
        'attributes'             => [AttributeController::class,          'attributes'],
        'measurement_units'      => [MeasurementUnitController::class,    'measurement_units'],

        // Parties (separated — own tables, no COA link)
        'vendors'                => [VendorController::class,             'vendors'],
        'customers'               => [CustomerController::class,          'customers'],

        // Client-facing order commitments
        'orders'                 => [OrderController::class,              'orders'],

        // Sale — placeholder until Sale module is built
        'sale'                    => [SaleController::class,              'sale'],

        // Finance
        'expenses'               => [ExpenseController::class,            'expenses'],
    ];

    foreach ($modules as $uri => [$controller, $permission]) {
        $param = match ($uri) {
            'roles' => '{role}',
            default => '{id}',
        };

        Route::get("$uri",              [$controller, 'index'])  ->middleware("check.permission:$permission.index")  ->name("$uri.index");
        Route::get("$uri/create",       [$controller, 'create']) ->middleware("check.permission:$permission.create") ->name("$uri.create");
        Route::post("$uri",             [$controller, 'store'])  ->middleware("check.permission:$permission.create") ->name("$uri.store");
        Route::get("$uri/$param",       [$controller, 'show'])   ->middleware("check.permission:$permission.index")  ->name("$uri.show");
        Route::get("$uri/$param/edit",  [$controller, 'edit'])   ->middleware("check.permission:$permission.edit")   ->name("$uri.edit");
        Route::put("$uri/$param",       [$controller, 'update']) ->middleware("check.permission:$permission.edit")   ->name("$uri.update");
        Route::delete("$uri/$param",    [$controller, 'destroy'])->middleware("check.permission:$permission.delete") ->name("$uri.destroy");
        Route::get("$uri/$param/print", [$controller, 'print'])  ->middleware("check.permission:$permission.print")  ->name("$uri.print");
    }

    // ────────────────────────────────────────────────────────────────
    // VOUCHERS
    // Voucher type is part of the URL (receipt/payment/journal/contra)
    // ────────────────────────────────────────────────────────────────
    Route::prefix('vouchers/{type}')->name('vouchers.')->group(function () {
        Route::get('/',              [VoucherController::class, 'index'])   ->middleware('check.permission:vouchers.index')  ->name('index');
        Route::get('/create',        [VoucherController::class, 'create'])  ->middleware('check.permission:vouchers.create') ->name('create');
        Route::post('/',             [VoucherController::class, 'store'])   ->middleware('check.permission:vouchers.create') ->name('store');
        Route::get('/{id}',          [VoucherController::class, 'show'])    ->middleware('check.permission:vouchers.index')  ->name('show');
        Route::get('/{id}/edit',     [VoucherController::class, 'edit'])    ->middleware('check.permission:vouchers.edit')   ->name('edit');
        Route::put('/{id}',          [VoucherController::class, 'update'])  ->middleware('check.permission:vouchers.edit')   ->name('update');
        Route::delete('/{id}',       [VoucherController::class, 'destroy']) ->middleware('check.permission:vouchers.delete') ->name('destroy');
        Route::get('/{id}/print',    [VoucherController::class, 'print'])   ->middleware('check.permission:vouchers.print')  ->name('print');
    });

    // ────────────────────────────────────────────────────────────────
    // REPORTS
    // ────────────────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('inventory', [InventoryReportController::class, 'index'])
            ->middleware('check.permission:reports.inventory')->name('inventory');

        Route::get('purchase', [PurchaseReportController::class, 'index'])
            ->middleware('check.permission:reports.purchase')->name('purchase');

        Route::get('sales', [SalesReportController::class, 'index'])
            ->middleware('check.permission:reports.sales')->name('sales');

        Route::get('general-ledger', [AccountsReportController::class, 'generalLedger'])
            ->middleware('check.permission:reports.accounts_general_ledger')->name('accounts_general_ledger');

        Route::get('trial-balance', [AccountsReportController::class, 'trialBalance'])
            ->middleware('check.permission:reports.accounts_trial_balance')->name('accounts_trial_balance');

        Route::get('profit-loss', [AccountsReportController::class, 'profitLoss'])
            ->middleware('check.permission:reports.accounts_profit_loss')->name('accounts_profit_loss');

        Route::get('balance-sheet', [AccountsReportController::class, 'balanceSheet'])
            ->middleware('check.permission:reports.accounts_balance_sheet')->name('accounts_balance_sheet');

        Route::get('receivables', [AccountsReportController::class, 'receivables'])
            ->middleware('check.permission:reports.accounts_receivables')->name('accounts_receivables');

        Route::get('payables', [AccountsReportController::class, 'payables'])
            ->middleware('check.permission:reports.accounts_payables')->name('accounts_payables');

        Route::get('party-ledger', [AccountsReportController::class, 'partyLedger'])
            ->middleware('check.permission:reports.accounts_party_ledger')->name('accounts_party_ledger');

        Route::get('cash-bank', [AccountsReportController::class, 'cashBank'])
            ->middleware('check.permission:reports.accounts_cash_bank')->name('accounts_cash_bank');

        Route::get('bank-reconciliation', [AccountsReportController::class, 'bankReconciliation'])
            ->middleware('check.permission:reports.accounts_bank_reconciliation')->name('accounts_bank_reconciliation');
    });

    // ────────────────────────────────────────────────────────────────
    // PURCHASE INVOICE
    // ────────────────────────────────────────────────────────────────
    Route::prefix('purchases')->name('purchase_invoices.')->group(function () {
        Route::get('/',              [PurchaseInvoiceController::class, 'index'])   ->name('index')  ->middleware('check.permission:purchase.index');
        Route::get('create',         [PurchaseInvoiceController::class, 'create'])  ->name('create') ->middleware('check.permission:purchase.create');
        Route::post('/',             [PurchaseInvoiceController::class, 'store'])   ->name('store')  ->middleware('check.permission:purchase.create');
        Route::get('{id}/edit',      [PurchaseInvoiceController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:purchase.edit');
        Route::put('{id}',           [PurchaseInvoiceController::class, 'update'])  ->name('update') ->middleware('check.permission:purchase.edit');
        Route::delete('{id}',        [PurchaseInvoiceController::class, 'destroy']) ->name('destroy')->middleware('check.permission:purchase.delete');
        Route::patch('{id}/restore', [PurchaseInvoiceController::class, 'restore']) ->name('restore')->middleware('check.permission:purchase.edit');
        Route::get('{id}/print',     [PurchaseInvoiceController::class, 'print'])   ->name('print')  ->middleware('check.permission:purchase.print');
    });

    // ────────────────────────────────────────────────────────────────
    // PURCHASE ORDER
    // ────────────────────────────────────────────────────────────────
    Route::prefix('purchase-orders')->name('purchase_orders.')->group(function () {
        Route::get('/',              [PurchaseOrderController::class, 'index'])   ->name('index')  ->middleware('check.permission:purchase.index');
        Route::get('create',         [PurchaseOrderController::class, 'create'])  ->name('create') ->middleware('check.permission:purchase.create');
        Route::post('/',             [PurchaseOrderController::class, 'store'])   ->name('store')  ->middleware('check.permission:purchase.create');
        Route::get('{id}/edit',      [PurchaseOrderController::class, 'edit'])    ->name('edit')   ->middleware('check.permission:purchase.edit');
        Route::put('{id}',           [PurchaseOrderController::class, 'update'])  ->name('update') ->middleware('check.permission:purchase.edit');
        Route::delete('{id}',        [PurchaseOrderController::class, 'destroy']) ->name('destroy')->middleware('check.permission:purchase.delete');
        Route::patch('{id}/restore', [PurchaseOrderController::class, 'restore']) ->name('restore')->middleware('check.permission:purchase.edit');
        Route::get('{id}/print',     [PurchaseOrderController::class, 'print'])   ->name('print')  ->middleware('check.permission:purchase.print');
    });

    // ────────────────────────────────────────────────────────────────
    // PURCHASE RETURN
    // ────────────────────────────────────────────────────────────────
    Route::prefix('purchase-returns')->name('purchase_returns.')->group(function () {
        Route::get('/',                          [PurchaseReturnController::class, 'index'])         ->name('index')          ->middleware('check.permission:purchase.index');
        Route::get('create',                     [PurchaseReturnController::class, 'create'])        ->name('create')         ->middleware('check.permission:purchase.create');
        Route::get('purchase/{purchaseId}/items', [PurchaseReturnController::class, 'purchaseItems']) ->name('purchase_items')->middleware('check.permission:purchase.index');
        Route::post('/',                          [PurchaseReturnController::class, 'store'])         ->name('store')          ->middleware('check.permission:purchase.create');
        Route::get('{id}/edit',                   [PurchaseReturnController::class, 'edit'])          ->name('edit')           ->middleware('check.permission:purchase.edit');
        Route::put('{id}',                        [PurchaseReturnController::class, 'update'])        ->name('update')         ->middleware('check.permission:purchase.edit');
        Route::delete('{id}',                     [PurchaseReturnController::class, 'destroy'])       ->name('destroy')        ->middleware('check.permission:purchase.delete');
        Route::get('{id}/print',                  [PurchaseReturnController::class, 'print'])         ->name('print')          ->middleware('check.permission:purchase.print');
    });

    // ────────────────────────────────────────────────────────────────
    // GATE PASS
    // ────────────────────────────────────────────────────────────────
    Route::prefix('gate-passes')->name('gate_passes.')->group(function () {
        Route::get('/',        [GatePassController::class, 'index'])   ->name('index')  ->middleware('check.permission:purchase.index');
        Route::get('create',   [GatePassController::class, 'create'])  ->name('create') ->middleware('check.permission:purchase.create');
        Route::post('/',       [GatePassController::class, 'store'])   ->name('store')  ->middleware('check.permission:purchase.create');
        Route::delete('{id}',  [GatePassController::class, 'destroy']) ->name('destroy')->middleware('check.permission:purchase.delete');
        Route::get('{id}/print', [GatePassController::class, 'print']) ->name('print')  ->middleware('check.permission:purchase.print');
    });

    // ────────────────────────────────────────────────────────────────
    // JOB ORDERS (job order creation = job issue, per finalized design)
    // ────────────────────────────────────────────────────────────────
    Route::prefix('jobs')->name('jobs.')->group(function () {
        Route::get('/',                [JobOrderController::class, 'index'])         ->name('index')          ->middleware('check.permission:jobs.index');
        Route::get('create',           [JobOrderController::class, 'create'])        ->name('create')         ->middleware('check.permission:jobs.create');
        Route::get('available-stock',  [JobOrderController::class, 'availableStock'])->name('available_stock')->middleware('check.permission:jobs.index');
        Route::post('/',               [JobOrderController::class, 'store'])         ->name('store')          ->middleware('check.permission:jobs.create');
        Route::get('{id}',             [JobOrderController::class, 'show'])          ->name('show')           ->middleware('check.permission:jobs.index');
        Route::delete('{id}',          [JobOrderController::class, 'destroy'])       ->name('destroy')        ->middleware('check.permission:jobs.delete');
        Route::get('{id}/print',       [JobOrderController::class, 'print'])         ->name('print')          ->middleware('check.permission:jobs.print');

        Route::post('{id}/comments',                  [JobOrderController::class, 'addComment'])   ->name('comments.store')  ->middleware('check.permission:jobs.index');
        Route::delete('{jobId}/comments/{commentId}', [JobOrderController::class, 'deleteComment'])->name('comments.destroy')->middleware('check.permission:jobs.index');
    });

    // ────────────────────────────────────────────────────────────────
    // JOB ORDER RECEIVES
    // ────────────────────────────────────────────────────────────────
    Route::prefix('job-receives')->name('job_receives.')->group(function () {
        Route::get('/',                        [JobOrderReceiveController::class, 'index'])       ->name('index')      ->middleware('check.permission:job_receives.index');
        Route::get('create',                   [JobOrderReceiveController::class, 'create'])      ->name('create')     ->middleware('check.permission:job_receives.create');
        Route::get('outstanding/{jobOrderId}', [JobOrderReceiveController::class, 'outstanding'])  ->name('outstanding')->middleware('check.permission:job_receives.index');
        Route::post('/',                        [JobOrderReceiveController::class, 'store'])       ->name('store')      ->middleware('check.permission:job_receives.create');
        Route::get('{id}',                      [JobOrderReceiveController::class, 'show'])         ->name('show')       ->middleware('check.permission:job_receives.index');
        Route::delete('{id}',                   [JobOrderReceiveController::class, 'destroy'])     ->name('destroy')    ->middleware('check.permission:job_receives.delete');
        Route::get('{id}/print',                [JobOrderReceiveController::class, 'print'])        ->name('print')      ->middleware('check.permission:job_receives.print');
    });
});