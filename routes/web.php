<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\{
    DashboardController,

    // ── Accounts ────────────────────────────────────────────────────────
    SubHeadOfAccController,
    COAController,

    // ── User management ─────────────────────────────────────────────────
    UserController,
    RoleController,
    PermissionController,

    // ── Product master ───────────────────────────────────────────────────
    ProductController,
    ProductCategoryController,
    ProductSubcategoryController,
    AttributeController,
    MeasurementUnitController,

    // ── Vendor & Customer master ─────────────────────────────────────────
    VendorController,
    CustomerController,

    // ── Services ─────────────────────────────────────────────────────────
    ServiceController,

    // ── Projects ─────────────────────────────────────────────────────────
    ProjectController,
    ProjectPhaseController,
    ProjectCommentController,

    // ── Sampling ─────────────────────────────────────────────────────────
    SamplingController,
    CourierController,

    // ── Purchase ─────────────────────────────────────────────────────────
    PurchaseOrderController,
    PurchaseInvoiceController,
    PurchaseReturnController,

    // ── Sales ────────────────────────────────────────────────────────────
    SaleInvoiceController,
    SaleReturnController,

    // ── Shipping ─────────────────────────────────────────────────────────
    ShipmentController,

    // ── Vouchers ─────────────────────────────────────────────────────────
    VoucherController,

    // ── Reports ──────────────────────────────────────────────────────────
    InventoryReportController,
    PurchaseReportController,
    SalesReportController,
    ProjectReportController,
    AccountsReportController,
};

Auth::routes();

Route::middleware(['auth'])->group(function () {

    // ────────────────────────────────────────────────────────────────────
    // DASHBOARD
    // ────────────────────────────────────────────────────────────────────

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ────────────────────────────────────────────────────────────────────
    // USER MANAGEMENT — standalone helpers
    // ────────────────────────────────────────────────────────────────────

    Route::prefix('users')->name('users.')->group(function () {
        Route::put('{id}/change-password', [UserController::class, 'changePassword'])->name('changePassword');
        Route::put('{id}/toggle-active',   [UserController::class, 'toggleActive'])->name('toggleActive');
        Route::post('change-my-password',  [UserController::class, 'changeMyPassword'])->name('changeMyPassword');
    });

    // ────────────────────────────────────────────────────────────────────
    // PRODUCT HELPERS  (AJAX endpoints used by select2 / dropdowns)
    // ────────────────────────────────────────────────────────────────────

    Route::prefix('helpers')->name('helpers.')->group(function () {

        // Products
        Route::get('products/details',                   [ProductController::class, 'details'])->name('product.details');
        Route::get('products/{product}/variations',      [ProductController::class, 'getVariations'])->name('product.variations');
        Route::get('products/{product}/invoices',        [PurchaseInvoiceController::class, 'getProductInvoices'])->name('product.invoices');
        Route::get('categories/{category}/subcategories',[ProductCategoryController::class, 'getSubcategories'])->name('category.subcategories');

        // Vendor & customer selects
        Route::get('vendors/search',   [VendorController::class, 'search'])->name('vendors.search');
        Route::get('customers/search', [CustomerController::class, 'search'])->name('customers.search');

        // Services: get vendors that can do a given service (used in phase assignment)
        Route::get('services/{service}/vendors',         [ServiceController::class, 'getVendors'])->name('service.vendors');
        // Services: get services a given vendor can provide
        Route::get('vendors/{vendor}/services',          [VendorController::class, 'getServices'])->name('vendor.services');

        // Projects: get open POs for a customer (used in sale invoice)
        Route::get('customers/{customer}/purchase-orders', [PurchaseOrderController::class, 'getByCustomer'])->name('customer.purchase_orders');

        // Projects: pull all accumulated costing for a project (used in sale invoice pre-fill)
        Route::get('projects/{project}/costing',         [ProjectController::class, 'getCosting'])->name('project.costing');

        // Phases: get available product items for dispatch/receipt
        Route::get('project-phases/{phase}/items',       [ProjectPhaseController::class, 'getItems'])->name('phase.items');

        // COA: search accounts for voucher line entry
        Route::get('accounts/search',                    [COAController::class, 'search'])->name('accounts.search');
    });

    // ────────────────────────────────────────────────────────────────────
    // STANDARD CRUD MODULES
    //
    // Pattern: uri => [controller, permission_prefix]
    //
    // Each entry auto-generates:
    //   GET    /{uri}               → index
    //   GET    /{uri}/create        → create
    //   POST   /{uri}               → store
    //   GET    /{uri}/{id}          → show
    //   GET    /{uri}/{id}/edit     → edit
    //   PUT    /{uri}/{id}          → update
    //   DELETE /{uri}/{id}          → destroy
    //   GET    /{uri}/{id}/print    → print
    // ────────────────────────────────────────────────────────────────────

    $modules = [

        // ── User management ───────────────────────────────────────────
        'roles'                  => [RoleController::class,               'user_roles'],
        'permissions'            => [PermissionController::class,         'user_roles'],
        'users'                  => [UserController::class,               'users'],

        // ── Accounts master ───────────────────────────────────────────
        'coa'                    => [COAController::class,                'coa'],
        'shoa'                   => [SubHeadOfAccController::class,       'shoa'],

        // ── Product master ────────────────────────────────────────────
        'products'               => [ProductController::class,            'products'],
        'product_categories'     => [ProductCategoryController::class,    'product_categories'],
        'product_subcategories'  => [ProductSubcategoryController::class, 'product_subcategories'],
        'attributes'             => [AttributeController::class,          'attributes'],
        'measurement_units'      => [MeasurementUnitController::class,    'measurement_units'],

        // ── Vendor & customer master ──────────────────────────────────
        // Vendors and customers each get their own CoA account created
        // automatically by a model observer on save.
        'vendors'                => [VendorController::class,             'vendors'],
        'customers'              => [CustomerController::class,           'customers'],

        // ── Services ─────────────────────────────────────────────────
        // Service CRUD + vendor–service pivot managed via nested route below
        'services'               => [ServiceController::class,            'services'],

        // ── Couriers (used in sampling dispatch) ──────────────────────
        'couriers'               => [CourierController::class,            'couriers'],

        // ── Purchase orders ───────────────────────────────────────────
        'purchase_orders'        => [PurchaseOrderController::class,      'purchase_orders'],

        // ── Purchase invoices & returns ───────────────────────────────
        'purchase_invoices'      => [PurchaseInvoiceController::class,    'purchase_invoices'],
        'purchase_return'        => [PurchaseReturnController::class,     'purchase_return'],

        // ── Sale invoices & returns ───────────────────────────────────
        'sale_invoices'          => [SaleInvoiceController::class,        'sale_invoices'],
        'sale_return'            => [SaleReturnController::class,         'sale_return'],

        // ── Shipments ─────────────────────────────────────────────────
        'shipments'              => [ShipmentController::class,           'shipments'],
    ];

    foreach ($modules as $uri => [$controller, $permission]) {
        $param = match($uri) {
            'roles' => '{role}',
            default => '{id}',
        };

        Route::get("$uri",             [$controller, 'index'])  ->middleware("check.permission:$permission.index")  ->name("$uri.index");
        Route::get("$uri/create",      [$controller, 'create']) ->middleware("check.permission:$permission.create") ->name("$uri.create");
        Route::post("$uri",            [$controller, 'store'])  ->middleware("check.permission:$permission.create") ->name("$uri.store");
        Route::get("$uri/$param",      [$controller, 'show'])   ->middleware("check.permission:$permission.index")  ->name("$uri.show");
        Route::get("$uri/$param/edit", [$controller, 'edit'])   ->middleware("check.permission:$permission.edit")   ->name("$uri.edit");
        Route::put("$uri/$param",      [$controller, 'update']) ->middleware("check.permission:$permission.edit")   ->name("$uri.update");
        Route::delete("$uri/$param",   [$controller, 'destroy'])->middleware("check.permission:$permission.delete") ->name("$uri.destroy");
        Route::get("$uri/$param/print",[$controller, 'print'])  ->middleware("check.permission:$permission.print")  ->name("$uri.print");
    }

    // ────────────────────────────────────────────────────────────────────
    // SERVICE → VENDOR PIVOT
    // Manage which vendors can perform a service and at what rate.
    // ────────────────────────────────────────────────────────────────────

    Route::prefix('services/{service}/vendors')->name('services.vendors.')->middleware('check.permission:services.edit')->group(function () {
        Route::post('/',        [ServiceController::class, 'attachVendor'])->name('attach');   // add vendor to service
        Route::put('/{vendor}', [ServiceController::class, 'updateVendor'])->name('update');   // update rate / notes
        Route::delete('/{vendor}', [ServiceController::class, 'detachVendor'])->name('detach'); // remove vendor from service
    });

    // ────────────────────────────────────────────────────────────────────
    // PROJECTS
    // ────────────────────────────────────────────────────────────────────

    Route::prefix('projects')->name('projects.')->group(function () {

        // Project CRUD
        Route::get('/',             [ProjectController::class, 'index'])  ->middleware('check.permission:projects.index')  ->name('index');
        Route::get('/create',       [ProjectController::class, 'create']) ->middleware('check.permission:projects.create') ->name('create');
        Route::post('/',            [ProjectController::class, 'store'])  ->middleware('check.permission:projects.create') ->name('store');
        Route::get('/{id}',         [ProjectController::class, 'show'])   ->middleware('check.permission:projects.index')  ->name('show');
        Route::get('/{id}/edit',    [ProjectController::class, 'edit'])   ->middleware('check.permission:projects.edit')   ->name('edit');
        Route::put('/{id}',         [ProjectController::class, 'update']) ->middleware('check.permission:projects.edit')   ->name('update');
        Route::delete('/{id}',      [ProjectController::class, 'destroy'])->middleware('check.permission:projects.delete') ->name('destroy');
        Route::get('/{id}/print',   [ProjectController::class, 'print'])  ->middleware('check.permission:projects.print')  ->name('print');

        // Project status transitions (called via AJAX buttons on project detail page)
        Route::patch('/{id}/status', [ProjectController::class, 'updateStatus'])->middleware('check.permission:projects.edit')->name('status');

        // ── SAMPLING (nested under project) ──────────────────────────

        Route::prefix('/{project}/sampling')->name('sampling.')->group(function () {
            Route::get('/',             [SamplingController::class, 'index'])   ->middleware('check.permission:sampling.index')  ->name('index');
            Route::get('/create',       [SamplingController::class, 'create'])  ->middleware('check.permission:sampling.create') ->name('create');
            Route::post('/',            [SamplingController::class, 'store'])   ->middleware('check.permission:sampling.create') ->name('store');
            Route::get('/{id}',         [SamplingController::class, 'show'])    ->middleware('check.permission:sampling.index')  ->name('show');
            Route::get('/{id}/edit',    [SamplingController::class, 'edit'])    ->middleware('check.permission:sampling.edit')   ->name('edit');
            Route::put('/{id}',         [SamplingController::class, 'update'])  ->middleware('check.permission:sampling.edit')   ->name('update');
            Route::delete('/{id}',      [SamplingController::class, 'destroy']) ->middleware('check.permission:sampling.delete') ->name('destroy');
            Route::get('/{id}/print',   [SamplingController::class, 'print'])   ->middleware('check.permission:sampling.print')  ->name('print');

            // Sample status: approve / reject / resample / drop
            Route::patch('/{id}/status', [SamplingController::class, 'updateStatus'])->middleware('check.permission:sampling.edit')->name('status');
        });

        // ── PROJECT PHASES (nested under project) ─────────────────────

        Route::prefix('/{project}/phases')->name('phases.')->group(function () {
            Route::get('/',             [ProjectPhaseController::class, 'index'])   ->middleware('check.permission:project_phases.index')  ->name('index');
            Route::get('/create',       [ProjectPhaseController::class, 'create'])  ->middleware('check.permission:project_phases.create') ->name('create');
            Route::post('/',            [ProjectPhaseController::class, 'store'])   ->middleware('check.permission:project_phases.create') ->name('store');
            Route::get('/{id}',         [ProjectPhaseController::class, 'show'])    ->middleware('check.permission:project_phases.index')  ->name('show');
            Route::get('/{id}/edit',    [ProjectPhaseController::class, 'edit'])    ->middleware('check.permission:project_phases.edit')   ->name('edit');
            Route::put('/{id}',         [ProjectPhaseController::class, 'update'])  ->middleware('check.permission:project_phases.edit')   ->name('update');
            Route::delete('/{id}',      [ProjectPhaseController::class, 'destroy']) ->middleware('check.permission:project_phases.delete') ->name('destroy');
            Route::get('/{id}/print',   [ProjectPhaseController::class, 'print'])   ->middleware('check.permission:project_phases.print')  ->name('print');

            // Phase status: pending → dispatched → partially_received → fully_received → approved / rejected
            Route::patch('/{id}/status', [ProjectPhaseController::class, 'updateStatus'])->middleware('check.permission:project_phases.edit')->name('status');

            // Dispatch goods to vendor for this phase
            Route::post('/{id}/dispatch',   [ProjectPhaseController::class, 'dispatch'])->middleware('check.permission:project_phases.edit')->name('dispatch');

            // Record receipt from vendor for this phase
            Route::post('/{id}/receive',    [ProjectPhaseController::class, 'receive'])->middleware('check.permission:project_phases.edit')->name('receive');
        });

        // ── COMMENTS / FOLLOW-UP (nested under project) ───────────────
        // Comments can optionally be linked to a specific phase via
        // a phase_id in the request body.

        Route::prefix('/{project}/comments')->name('comments.')->group(function () {
            Route::get('/',        [ProjectCommentController::class, 'index'])   ->middleware('check.permission:project_comments.index')  ->name('index');
            Route::post('/',       [ProjectCommentController::class, 'store'])   ->middleware('check.permission:project_comments.create') ->name('store');
            Route::put('/{id}',    [ProjectCommentController::class, 'update'])  ->middleware('check.permission:project_comments.edit')   ->name('update');
            Route::delete('/{id}', [ProjectCommentController::class, 'destroy']) ->middleware('check.permission:project_comments.delete') ->name('destroy');
        });
    });

    // ────────────────────────────────────────────────────────────────────
    // VOUCHERS
    // Voucher type is part of the URL so each type has its own
    // named route set (vouchers.receipt.index, vouchers.payment.index …)
    //
    // Valid types: receipt | payment | journal | contra
    // ────────────────────────────────────────────────────────────────────

    Route::prefix('vouchers/{type}')->name('vouchers.')->group(function () {
        Route::get('/',             [VoucherController::class, 'index'])   ->middleware('check.permission:vouchers.index')  ->name('index');
        Route::get('/create',       [VoucherController::class, 'create'])  ->middleware('check.permission:vouchers.create') ->name('create');
        Route::post('/',            [VoucherController::class, 'store'])   ->middleware('check.permission:vouchers.create') ->name('store');
        Route::get('/{id}',         [VoucherController::class, 'show'])    ->middleware('check.permission:vouchers.index')  ->name('show');
        Route::get('/{id}/edit',    [VoucherController::class, 'edit'])    ->middleware('check.permission:vouchers.edit')   ->name('edit');
        Route::put('/{id}',         [VoucherController::class, 'update'])  ->middleware('check.permission:vouchers.edit')   ->name('update');
        Route::delete('/{id}',      [VoucherController::class, 'destroy']) ->middleware('check.permission:vouchers.delete') ->name('destroy');
        Route::get('/{id}/print',   [VoucherController::class, 'print'])   ->middleware('check.permission:vouchers.print')  ->name('print');
    });

    // ────────────────────────────────────────────────────────────────────
    // REPORTS  (read-only, each guarded by its own permission)
    // ────────────────────────────────────────────────────────────────────

    Route::prefix('reports')->name('reports.')->group(function () {

        // ── Inventory ─────────────────────────────────────────────────
        Route::get('inventory', [InventoryReportController::class, 'index'])
            ->middleware('check.permission:reports.inventory')
            ->name('inventory');

        // ── Purchase ──────────────────────────────────────────────────
        Route::get('purchase', [PurchaseReportController::class, 'index'])
            ->middleware('check.permission:reports.purchase')
            ->name('purchase');

        // ── Sales ─────────────────────────────────────────────────────
        Route::get('sales', [SalesReportController::class, 'index'])
            ->middleware('check.permission:reports.sales')
            ->name('sales');

        // ── Project reports ───────────────────────────────────────────
        Route::get('project-costing', [ProjectReportController::class, 'costing'])
            ->middleware('check.permission:reports.project_costing')
            ->name('project_costing');

        Route::get('project-profit-loss', [ProjectReportController::class, 'profitLoss'])
            ->middleware('check.permission:reports.project_profit_loss')
            ->name('project_profit_loss');

        // ── Accounting reports ────────────────────────────────────────
        Route::get('general-ledger', [AccountsReportController::class, 'generalLedger'])
            ->middleware('check.permission:reports.accounts_general_ledger')
            ->name('accounts_general_ledger');

        Route::get('trial-balance', [AccountsReportController::class, 'trialBalance'])
            ->middleware('check.permission:reports.accounts_trial_balance')
            ->name('accounts_trial_balance');

        Route::get('profit-loss', [AccountsReportController::class, 'profitLoss'])
            ->middleware('check.permission:reports.accounts_profit_loss')
            ->name('accounts_profit_loss');

        Route::get('balance-sheet', [AccountsReportController::class, 'balanceSheet'])
            ->middleware('check.permission:reports.accounts_balance_sheet')
            ->name('accounts_balance_sheet');

        Route::get('receivables', [AccountsReportController::class, 'receivables'])
            ->middleware('check.permission:reports.accounts_receivables')
            ->name('accounts_receivables');

        Route::get('payables', [AccountsReportController::class, 'payables'])
            ->middleware('check.permission:reports.accounts_payables')
            ->name('accounts_payables');

        Route::get('party-ledger', [AccountsReportController::class, 'partyLedger'])
            ->middleware('check.permission:reports.accounts_party_ledger')
            ->name('accounts_party_ledger');

        Route::get('cash-bank', [AccountsReportController::class, 'cashBank'])
            ->middleware('check.permission:reports.accounts_cash_bank')
            ->name('accounts_cash_bank');

        Route::get('bank-reconciliation', [AccountsReportController::class, 'bankReconciliation'])
            ->middleware('check.permission:reports.accounts_bank_reconciliation')
            ->name('accounts_bank_reconciliation');
    });
});