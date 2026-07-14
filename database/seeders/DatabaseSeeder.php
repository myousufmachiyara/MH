<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\HeadOfAccounts;
use App\Models\SubHeadOfAccounts;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;
use App\Models\AccountMapping;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now    = now();
        $userId = 1; // farhan (superadmin)

        // ─────────────────────────────────────────────────────────────────
        // USERS
        // ─────────────────────────────────────────────────────────────────

        $farhan = User::firstOrCreate(
            ['username' => 'farhan'],
            ['name' => 'Farhan', 'email' => null, 'password' => Hash::make('12345678')]
        );

        $yousuf = User::firstOrCreate(
            ['username' => 'yousuf'],
            ['name' => 'Yousuf', 'email' => null, 'password' => Hash::make('12345678')]
        );

        // ─────────────────────────────────────────────────────────────────
        // ROLES
        // ─────────────────────────────────────────────────────────────────

        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);

        $farhan->assignRole($superAdminRole);
        $yousuf->assignRole($superAdminRole);

        // ─────────────────────────────────────────────────────────────────
        // MODULE PERMISSIONS
        //
        // Canonical module list — MUST match:
        //   • routes/web.php module list + dedicated route blocks
        //     (purchase invoices/orders/returns, gate passes, jobs,
        //      job receives each have their own route block — NOT the
        //      generic CRUD loop — but they share these same permission
        //      names, so the seeder still owns the permission strings)
        //   • sidebar-left.blade.php nav groups
        //   • mobile app dashboard tiles + bottom nav
        //
        // Core business flow (mirrors mobile exactly):
        //   Orders → Gate Pass → Purchase → Jobs → Job Receives → Sale
        //
        // Old Projects/Phases/Sampling/Shipments/Services concept is
        // RETIRED — mobile never used it, web and mobile now share one flow.
        // ─────────────────────────────────────────────────────────────────

        $modules = [
            // Users & roles
            'user_roles', 'users',

            // Accounts
            'coa', 'shoa',

            // Product master
            'products', 'product_categories', 'product_subcategories',
            'attributes', 'measurement_units', 'job_types',

            // Parties — separated tables, separate permissions
            'customers', 'vendors',

            // Core flow — matches mobile dashboard/bottom-nav 1:1
            'orders',
            'gate_passes',
            'purchase',
            'jobs',
            'job_receives',
            'sale',

            // Finance
            'vouchers',
            'expenses',
        ];

        foreach ($modules as $module) {
            foreach (['index', 'create', 'edit', 'delete', 'print'] as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // REPORT PERMISSIONS (unchanged — already match mobile's
        // Reports hub + Party Ledger tile)
        // ─────────────────────────────────────────────────────────────────

        $reports = [
            'inventory', 'purchase', 'sales',
            'accounts_general_ledger', 'accounts_trial_balance',
            'accounts_profit_loss', 'accounts_balance_sheet',
            'accounts_receivables', 'accounts_payables',
            'accounts_party_ledger', 'accounts_cash_bank',
            'accounts_bank_reconciliation',
        ];

        foreach ($reports as $report) {
            Permission::firstOrCreate(['name' => "reports.{$report}"]);
        }

        // ─────────────────────────────────────────────────────────────────
        // ROLE → PERMISSION ASSIGNMENT
        // ─────────────────────────────────────────────────────────────────

        $superAdminRole->syncPermissions(Permission::all());
        $adminRole->syncPermissions(Permission::all());

        $managerRole->syncPermissions(
            Permission::whereNotIn('name', [
                'user_roles.create', 'user_roles.edit', 'user_roles.delete',
                'users.delete', 'coa.delete', 'shoa.delete',
            ])->get()
        );

        $accountantRole->syncPermissions(
            Permission::where(function ($q) {
                $q->where('name', 'like', 'vouchers.%')
                  ->orWhere('name', 'like', 'expenses.%')
                  ->orWhere('name', 'like', 'purchase.%')
                  ->orWhere('name', 'like', 'sale.%')
                  ->orWhere('name', 'like', 'reports.accounts%')
                  ->orWhereIn('name', ['reports.purchase', 'reports.sales']);
            })->get()
        );

        $operatorRole->syncPermissions(
            Permission::where(function ($q) {
                $q->where('name', 'like', 'orders.%')
                  ->orWhere('name', 'like', 'gate_passes.%')
                  ->orWhere('name', 'like', 'jobs.%')
                  ->orWhere('name', 'like', 'job_receives.%')
                  ->orWhere('name', 'like', 'purchase.%')
                  ->orWhereIn('name', ['reports.inventory']);
            })->get()
        );

        $viewerRole->syncPermissions(
            Permission::where(function ($q) {
                $q->where('name', 'like', '%.index')
                  ->orWhere('name', 'like', '%.print');
            })->get()
        );

        // ─────────────────────────────────────────────────────────────────
        // HEADS OF ACCOUNTS
        // ─────────────────────────────────────────────────────────────────

        HeadOfAccounts::insert([
            ['id' => 1, 'name' => 'Assets',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Liabilities', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Equity',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Revenue',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Expenses',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // SUB HEADS OF ACCOUNTS
        // ─────────────────────────────────────────────────────────────────

        SubHeadOfAccounts::insert([
            ['id' =>  1, 'hoa_id' => 1, 'name' => 'Cash',                'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'hoa_id' => 1, 'name' => 'Bank',                'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'hoa_id' => 1, 'name' => 'Accounts Receivable', 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'hoa_id' => 1, 'name' => 'Inventory',           'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'hoa_id' => 2, 'name' => 'Accounts Payable',    'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'hoa_id' => 2, 'name' => 'Loans Payable',       'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'hoa_id' => 3, 'name' => 'Owner Capital',       'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'hoa_id' => 4, 'name' => 'Sales',               'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'hoa_id' => 4, 'name' => 'Other Income',        'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'hoa_id' => 5, 'name' => 'Cost of Goods Sold',  'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'hoa_id' => 5, 'name' => 'Salaries',            'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'hoa_id' => 5, 'name' => 'Rent',                'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'hoa_id' => 5, 'name' => 'Utilities',           'created_at' => $now, 'updated_at' => $now],
            ['id' => 14, 'hoa_id' => 5, 'name' => 'Other Expenses',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 15, 'hoa_id' => 5, 'name' => 'Freight & Logistics', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 16, 'hoa_id' => 5, 'name' => 'Service Costs',       'created_at' => $now, 'updated_at' => $now],
            ['id' => 17, 'hoa_id' => 5, 'name' => 'Sampling Expenses',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 18, 'hoa_id' => 5, 'name' => 'Packaging Expenses',  'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // CHART OF ACCOUNTS
        // ─────────────────────────────────────────────────────────────────

        $coaBase = [
            'opening_balance' => 0,
            'opening_date'    => $now,
            'created_by'      => $userId,
            'updated_by'      => $userId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ];

        $coaRows = [
            // ── Assets ──────────────────────────────────────────────────
            [ 1, '101001',  1, 'Cash in Hand',                        'cash'],
            [ 2, '102001',  2, 'Main Bank Account',                   'bank'],
            [ 3, '103001',  3, 'Accounts Receivable — Control',       'receivable'],
            [ 4, '104001',  4, 'Stock in Hand — Yarn',                'inventory'],
            [ 5, '104002',  4, 'Stock in Hand — Greige Fabric',       'inventory'],
            [ 6, '104003',  4, 'Stock in Hand — Finished Goods',      'inventory'],
            [ 7, '104004',  4, 'Stock in Hand — Packaging Materials', 'inventory'],
            // ── Liabilities ─────────────────────────────────────────────
            [ 8, '201001',  5, 'Accounts Payable — Control',          'liability'],
            [ 9, '201002',  5, 'Service Vendors Payable — Control',   'liability'],
            [10, '202001',  6, 'Loan Payable',                        'liability'],
            // ── Equity ──────────────────────────────────────────────────
            [11, '301001',  7, 'Owner Capital',                       'equity'],
            [12, '302001',  7, 'Owner Drawings',                      'equity'],
            [13, '303001',  7, 'Retained Earnings',                   'equity'],
            // ── Revenue ─────────────────────────────────────────────────
            [14, '401001',  8, 'Sales Revenue — Fabric',              'revenue'],
            [15, '401002',  8, 'Freight Recovered from Customers',    'revenue'],
            [16, '402001',  9, 'Other Income',                        'revenue'],
            // ── Expenses ────────────────────────────────────────────────
            [17, '501001', 10, 'Cost of Goods Sold',                  'cogs'],
            [18, '502001', 11, 'Salaries Expense',                    'expenses'],
            [19, '503001', 12, 'Rent Expense',                        'expenses'],
            [20, '504001', 13, 'Utilities Expense',                   'expenses'],
            [21, '505001', 14, 'Miscellaneous Expense',               'expenses'],
            [22, '506001', 15, 'Outward Freight Expense',             'freight'],
            [23, '506002', 15, 'Inward Freight / Courier Expense',    'freight'],
            [24, '507001', 16, 'Weaving Service Cost',                'service_cost'],
            [25, '507002', 16, 'Processing / Printing Service Cost',  'service_cost'],
            [26, '507003', 16, 'Dyeing Service Cost',                 'service_cost'],
            [27, '507004', 16, 'Finishing Service Cost',              'service_cost'],
            [28, '507005', 16, 'Packaging Labour Cost',               'service_cost'],
            [29, '507006', 16, 'Other Service Cost',                  'service_cost'],
            [30, '508001', 17, 'Sample Production Expense',           'sampling'],
            [31, '508002', 17, 'Sample Courier & Dispatch Expense',   'sampling'],
            [32, '509001', 18, 'Packaging Material Expense',          'packaging'],
            // ── Additional (Tax accounts) ──────────────────────────────
            [33, '201003',  5, 'Sales Tax Payable',                   'liability'],
            [34, '104005',  4, 'Purchase Tax (Input Tax)',            'inventory'],
        ];

        foreach ($coaRows as [$id, $code, $shoa, $name, $type]) {
            ChartOfAccounts::firstOrCreate(
                ['id' => $id],
                array_merge($coaBase, [
                    'account_code' => $code,
                    'shoa_id'      => $shoa,
                    'name'         => $name,
                    'account_type' => $type,
                ])
            );
        }

        // ─────────────────────────────────────────────────────────────────
        // ACCOUNT MAPPINGS — pre-mapped to the seeded COA accounts.
        // stock_in_hand / processing_charges act as FALLBACKS only —
        // Purchase/Job Receive prefer the per-category / per-job-type
        // account when the product's category or job's type has one set;
        // these two roles are used only when that's not configured.
        // Editable anytime from Chart of Accounts → Account Mappings.
        // ─────────────────────────────────────────────────────────────────

        $mappingDefaults = [
            'accounts_receivable' => 3,  // Accounts Receivable — Control
            'accounts_payable'    => 8,  // Accounts Payable — Control
            'sales_revenue'       => 14, // Sales Revenue — Fabric
            'sales_tax_payable'   => 33, // Sales Tax Payable
            'purchase_tax'        => 34, // Purchase Tax (Input Tax)
            'stock_in_hand'       => 4,  // Stock in Hand — Yarn (fallback)
            'cogs'                => 17, // Cost of Goods Sold
            'cash'                => 1,  // Cash in Hand
            'bank'                => 2,  // Main Bank Account
            'processing_charges'  => 29, // Other Service Cost (fallback)
        ];

        foreach ($mappingDefaults as $roleKey => $accountId) {
            AccountMapping::updateOrCreate(
                ['role_key' => $roleKey],
                ['account_id' => $accountId]
            );
        }

        // ─────────────────────────────────────────────────────────────────
        // MEASUREMENT UNITS
        // ─────────────────────────────────────────────────────────────────

        MeasurementUnit::insert([
            ['id' => 1, 'name' => 'Kilogram', 'shortcode' => 'kg',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Meter',    'shortcode' => 'm',      'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Pieces',   'shortcode' => 'pcs',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Bag',      'shortcode' => 'bag',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Bundle',   'shortcode' => 'bundle', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Pounds',   'shortcode' => 'lbs',    'created_at' => $now, 'updated_at' => $now],
            ['id' => 7, 'name' => 'Yard',     'shortcode' => 'yd',     'created_at' => $now, 'updated_at' => $now],
            ['id' => 8, 'name' => 'Roll',     'shortcode' => 'roll',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 9, 'name' => 'Box',      'shortcode' => 'box',    'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // JOB TYPES — each maps to its service-cost COA account
        // ─────────────────────────────────────────────────────────────────

        DB::table('job_types')->insertOrIgnore([
            ['id' => 1, 'name' => 'Weaving',               'service_cost_account_id' => 24, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Processing / Printing', 'service_cost_account_id' => 25, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Dyeing',                'service_cost_account_id' => 26, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Finishing',             'service_cost_account_id' => 27, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Packaging',             'service_cost_account_id' => 28, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 6, 'name' => 'Other',                 'service_cost_account_id' => 29, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // DUMMY VENDORS (separated table — no coa_id)
        // ─────────────────────────────────────────────────────────────────

        DB::table('vendors')->insertOrIgnore([
            ['id' =>  1, 'name' => 'Al-Noor Spinning Mills',  'vendor_type' => 'spinning_mill',   'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'name' => 'Crescent Weaving Mills',  'vendor_type' => 'weaving_mill',    'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'name' => 'Royal Processing House',  'vendor_type' => 'processing_mill', 'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'name' => 'Colour Line Dyeing Unit', 'vendor_type' => 'processing_mill', 'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'name' => 'Pak Finishing Works',     'vendor_type' => 'processing_mill', 'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'name' => 'SafePack Industries',     'vendor_type' => 'packager',        'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'name' => 'TCS Courier',             'vendor_type' => 'courier',         'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'name' => 'Star Embroidery Works',   'vendor_type' => 'other',           'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'name' => 'Digital Print Studio',    'vendor_type' => 'other',           'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => 'General Contractor',      'vendor_type' => 'other',           'opening_balance' => 0, 'opening_type' => 'payable', 'is_active' => 1, 'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // DUMMY CUSTOMER (separated table — no coa_id)
        // ─────────────────────────────────────────────────────────────────

        DB::table('customers')->insertOrIgnore([
            'id'              => 1,
            'name'            => 'ABC Customer',
            'opening_balance' => 0.00,
            'opening_type'    => 'receivable',
            'is_active'       => 1,
            'created_by'      => $userId,
            'updated_by'      => $userId,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // ─────────────────────────────────────────────────────────────────
        // PRODUCT CATEGORIES — Yarn + Greige, each linked to its own
        // stock/COGS account
        // ─────────────────────────────────────────────────────────────────

        DB::table('product_categories')->insertOrIgnore([
            ['id' => 1, 'name' => 'Yarn',           'code' => 'yarn',   'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Greige Fabric',  'code' => 'greige', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('product_categories')->where('id', 1)->update([
            'stock_account_id' => 4,  // Stock in Hand — Yarn
            'cogs_account_id'  => 17, // Cost of Goods Sold
        ]);

        DB::table('product_categories')->where('id', 2)->update([
            'stock_account_id' => 5,  // Stock in Hand — Greige Fabric
            'cogs_account_id'  => 17, // Cost of Goods Sold
        ]);

        // ─────────────────────────────────────────────────────────────────
        // PRODUCTS — Yarn-0001 (Yarn category) + Greige-0001 (Greige category)
        // ─────────────────────────────────────────────────────────────────

        DB::table('products')->insertOrIgnore([
            [
                'id'               => 1,
                'category_id'      => 1,
                'subcategory_id'   => null,
                'name'             => 'yarn-0001',
                'sku'              => 'YARN-0001',
                'description'      => null,
                'opening_stock'    => 0.00,
                'selling_price'    => 0.00,
                'measurement_unit' => 6, // lbs
                'is_active'        => 1,
                'track_lots'       => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'id'               => 2,
                'category_id'      => 2,
                'subcategory_id'   => null,
                'name'             => 'greige-0001',
                'sku'              => 'GREIGE-0001',
                'description'      => null,
                'opening_stock'    => 0.00,
                'selling_price'    => 0.00,
                'measurement_unit' => 2, // meter
                'is_active'        => 1,
                'track_lots'       => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ]);
    }
}