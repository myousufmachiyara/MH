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
        // ROLES & PERMISSIONS
        // ─────────────────────────────────────────────────────────────────

        $superAdminRole = Role::firstOrCreate(['name' => 'superadmin']);
        $adminRole      = Role::firstOrCreate(['name' => 'admin']);
        $managerRole    = Role::firstOrCreate(['name' => 'manager']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $operatorRole   = Role::firstOrCreate(['name' => 'operator']);
        $viewerRole     = Role::firstOrCreate(['name' => 'viewer']);

        $farhan->assignRole($superAdminRole);
        $yousuf->assignRole($adminRole);

        $modules = [
            'user_roles', 'users',
            'coa', 'shoa',
            'products', 'product_categories', 'product_subcategories',
            'attributes', 'measurement_units',
            'services', 'vendors', 'customers',
            'projects', 'project_phases', 'project_comments',
            'sampling', 'couriers',
            'purchase_orders', 'purchase_invoices', 'purchase_return',
            'sale_invoices', 'sale_return',
            'shipments',
            'vouchers',
        ];

        foreach ($modules as $module) {
            foreach (['index', 'create', 'edit', 'delete', 'print'] as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
            }
        }

        $reports = [
            'inventory', 'purchase', 'sales',
            'project_costing', 'project_profit_loss',
            'accounts_general_ledger', 'accounts_trial_balance',
            'accounts_profit_loss', 'accounts_balance_sheet',
            'accounts_receivables', 'accounts_payables',
            'accounts_party_ledger', 'accounts_cash_bank',
            'accounts_bank_reconciliation',
        ];

        foreach ($reports as $report) {
            Permission::firstOrCreate(['name' => "reports.{$report}"]);
        }

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
                  ->orWhere('name', 'like', 'purchase_invoices.%')
                  ->orWhere('name', 'like', 'sale_invoices.%')
                  ->orWhere('name', 'like', 'purchase_return.%')
                  ->orWhere('name', 'like', 'sale_return.%')
                  ->orWhere('name', 'like', 'reports.accounts%')
                  ->orWhereIn('name', ['reports.purchase', 'reports.sales']);
            })->get()
        );

        $operatorRole->syncPermissions(
            Permission::where(function ($q) {
                $q->where('name', 'like', 'projects.%')
                  ->orWhere('name', 'like', 'project_phases.%')
                  ->orWhere('name', 'like', 'project_comments.%')
                  ->orWhere('name', 'like', 'sampling.%')
                  ->orWhere('name', 'like', 'purchase_orders.%')
                  ->orWhere('name', 'like', 'shipments.%')
                  ->orWhereIn('name', ['reports.inventory'])
                  ->orWhere('name', 'like', 'reports.project%');
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
        //
        // ids 1–32   = permanent system accounts
        // ids 33–42  = dummy vendor COA accounts  (one per vendor)
        // ids 43–43  = dummy customer COA account
        //
        // Vendor COA accounts go under shoa_id=5 (Accounts Payable)
        // Customer COA account goes under shoa_id=3 (Accounts Receivable)
        // ─────────────────────────────────────────────────────────────────

        $coaBase = [
            'credit_limit' => 0, 'opening_balance' => 0,
            'opening_date' => $now, 'receivables' => 0, 'payables' => 0,
            'created_by'   => $userId, 'updated_by' => $userId,
            'created_at'   => $now,   'updated_at'  => $now,
        ];

        $coaRows = [
            // ── Assets ──────────────────────────────────────────────────
            [ 1, '101001',  1, 'Cash in Hand',                        'cash',         0, 0],
            [ 2, '102001',  2, 'Main Bank Account',                   'bank',         0, 0],
            [ 3, '103001',  3, 'Accounts Receivable — Control',       'receivable',   1, 0],
            [ 4, '104001',  4, 'Stock in Hand — Yarn',                'inventory',    0, 0],
            [ 5, '104002',  4, 'Stock in Hand — Greige Fabric',       'inventory',    0, 0],
            [ 6, '104003',  4, 'Stock in Hand — Finished Goods',      'inventory',    0, 0],
            [ 7, '104004',  4, 'Stock in Hand — Packaging Materials', 'inventory',    0, 0],
            // ── Liabilities ─────────────────────────────────────────────
            [ 8, '201001',  5, 'Accounts Payable — Control',          'liability',    0, 1],
            [ 9, '201002',  5, 'Service Vendors Payable — Control',   'liability',    0, 1],
            [10, '202001',  6, 'Loan Payable',                        'liability',    0, 0],
            // ── Equity ──────────────────────────────────────────────────
            [11, '301001',  7, 'Owner Capital',                       'equity',       0, 0],
            [12, '302001',  7, 'Owner Drawings',                      'equity',       0, 0],
            [13, '303001',  7, 'Retained Earnings',                   'equity',       0, 0],
            // ── Revenue ─────────────────────────────────────────────────
            [14, '401001',  8, 'Sales Revenue — Fabric',              'revenue',      0, 0],
            [15, '401002',  8, 'Freight Recovered from Customers',    'revenue',      0, 0],
            [16, '402001',  9, 'Other Income',                        'revenue',      0, 0],
            // ── Expenses ────────────────────────────────────────────────
            [17, '501001', 10, 'Cost of Goods Sold',                  'cogs',         0, 0],
            [18, '502001', 11, 'Salaries Expense',                    'expenses',     0, 0],
            [19, '503001', 12, 'Rent Expense',                        'expenses',     0, 0],
            [20, '504001', 13, 'Utilities Expense',                   'expenses',     0, 0],
            [21, '505001', 14, 'Miscellaneous Expense',               'expenses',     0, 0],
            // ── Freight ─────────────────────────────────────────────────
            [22, '506001', 15, 'Outward Freight Expense',             'freight',      0, 0],
            [23, '506002', 15, 'Inward Freight / Courier Expense',    'freight',      0, 0],
            // ── Service Costs ────────────────────────────────────────────
            [24, '507001', 16, 'Weaving Service Cost',                'service_cost', 0, 0],
            [25, '507002', 16, 'Processing / Printing Service Cost',  'service_cost', 0, 0],
            [26, '507003', 16, 'Dyeing Service Cost',                 'service_cost', 0, 0],
            [27, '507004', 16, 'Finishing Service Cost',              'service_cost', 0, 0],
            [28, '507005', 16, 'Packaging Labour Cost',               'service_cost', 0, 0],
            [29, '507006', 16, 'Other Service Cost',                  'service_cost', 0, 0],
            // ── Sampling ─────────────────────────────────────────────────
            [30, '508001', 17, 'Sample Production Expense',           'sampling',     0, 0],
            [31, '508002', 17, 'Sample Courier & Dispatch Expense',   'sampling',     0, 0],
            // ── Packaging ────────────────────────────────────────────────
            [32, '509001', 18, 'Packaging Material Expense',          'packaging',    0, 0],

            // ── Vendor COA accounts (one per dummy vendor) ────────────────
            // vendor_id=1  Spinning Mill  → shoa 5 (Payable)
            [33, '201101',  5, 'Al-Noor Spinning Mills',              'vendor',       0, 0],
            // vendor_id=2  Weaving Mill   → shoa 5
            [34, '201102',  5, 'Crescent Weaving Mills',              'vendor',       0, 0],
            // vendor_id=3  Processing Mill → shoa 5
            [35, '201103',  5, 'Royal Processing House',              'vendor',       0, 0],
            // vendor_id=4  Dyeing vendor  → shoa 5
            [36, '201104',  5, 'Colour Line Dyeing Unit',             'vendor',       0, 0],
            // vendor_id=5  Finishing vendor → shoa 5
            [37, '201105',  5, 'Pak Finishing Works',                 'vendor',       0, 0],
            // vendor_id=6  Packager       → shoa 5
            [38, '201106',  5, 'SafePack Industries',                 'vendor',       0, 0],
            // vendor_id=7  Courier        → shoa 5
            [39, '201107',  5, 'TCS Courier',                         'vendor',       0, 0],
            // vendor_id=8  Embroidery     → shoa 5
            [40, '201108',  5, 'Star Embroidery Works',               'vendor',       0, 0],
            // vendor_id=9  Printing       → shoa 5
            [41, '201109',  5, 'Digital Print Studio',                'vendor',       0, 0],
            // vendor_id=10 Other/General  → shoa 5
            [42, '201110',  5, 'General Contractor',                  'vendor',       0, 0],

            // ── Customer COA account ──────────────────────────────────────
            // customer_id=1 → shoa 3 (Receivable)
            [43, '103101',  3, 'ABC Customer',                        'customer',     0, 0],
        ];

        foreach ($coaRows as [$id, $code, $shoa, $name, $type, $rec, $pay]) {
            ChartOfAccounts::create(array_merge($coaBase, [
                'id'           => $id,
                'account_code' => $code,
                'shoa_id'      => $shoa,
                'name'         => $name,
                'account_type' => $type,
                'receivables'  => $rec,
                'payables'     => $pay,
            ]));
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
        // DUMMY VENDORS
        // One per service type, each with a unique vendor_type.
        // All inserted via DB::table() to bypass the VendorObserver
        // (COA accounts already created above).
        // ─────────────────────────────────────────────────────────────────

        DB::table('vendors')->insert([
            // id=1  Spinning mill — supplies yarn
            ['id' =>  1, 'coa_id' => 33, 'name' => 'Al-Noor Spinning Mills',  'vendor_type' => 'spinning_mill',   'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=2  Weaving mill — converts yarn → greige fabric
            ['id' =>  2, 'coa_id' => 34, 'name' => 'Crescent Weaving Mills',  'vendor_type' => 'weaving_mill',    'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=3  Processing mill — bleaching, mercerising
            ['id' =>  3, 'coa_id' => 35, 'name' => 'Royal Processing House',  'vendor_type' => 'processing_mill', 'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=4  Dyeing — reactive / vat dyeing
            ['id' =>  4, 'coa_id' => 36, 'name' => 'Colour Line Dyeing Unit', 'vendor_type' => 'processing_mill', 'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=5  Finishing — sanforizing, calendering
            ['id' =>  5, 'coa_id' => 37, 'name' => 'Pak Finishing Works',     'vendor_type' => 'processing_mill', 'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=6  Packager — folding, poly-bagging, boxing
            ['id' =>  6, 'coa_id' => 38, 'name' => 'SafePack Industries',     'vendor_type' => 'packager',        'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=7  Courier — dispatch of samples and shipments
            ['id' =>  7, 'coa_id' => 39, 'name' => 'TCS Courier',             'vendor_type' => 'courier',         'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=8  Embroidery — decorative stitching
            ['id' =>  8, 'coa_id' => 40, 'name' => 'Star Embroidery Works',   'vendor_type' => 'other',           'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=9  Digital printing
            ['id' =>  9, 'coa_id' => 41, 'name' => 'Digital Print Studio',    'vendor_type' => 'other',           'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            // id=10 General contractor / other
            ['id' => 10, 'coa_id' => 42, 'name' => 'General Contractor',      'vendor_type' => 'other',           'is_active' => 1, 'opening_balance' => 0, 'opening_balance_type' => 'credit', 'opening_balance_date' => now()->toDateString(), 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // DUMMY CUSTOMER
        // ─────────────────────────────────────────────────────────────────

        DB::table('customers')->insert([
            'id'                   => 1,
            'coa_id'               => 43,
            'name'                 => 'ABC Customer',
            'opening_balance'      => 0.00,
            'opening_balance_type' => 'debit',
            'opening_balance_date' => now()->toDateString(),
            'credit_limit'         => 0.00,
            'is_active'            => 1,
            'created_by'           => 2,
            'updated_by'           => 2,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        // ─────────────────────────────────────────────────────────────────
        // PRODUCT CATEGORY & PRODUCT
        // ─────────────────────────────────────────────────────────────────

        DB::table('product_categories')->insert([
            'id' => 1, 'name' => 'Yarn', 'code' => 'yarn',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('products')->insert([
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
        ]);

        // ─────────────────────────────────────────────────────────────────
        // SERVICES
        //
        // Full textile production workflow:
        //   1  Weaving          — yarn → greige fabric (lbs)
        //   2  Bleaching        — greige → white (lbs)
        //   3  Dyeing           — colour application (lbs)
        //   4  Printing         — pattern/digital print (yd)
        //   5  Processing       — combined scouring / mercerising (lbs)
        //   6  Finishing        — sanforize, calender, soften (yd)
        //   7  Embroidery       — decorative stitching (pcs)
        //   8  Cutting          — cut to size (pcs)
        //   9  Stitching        — stitching / hem (pcs)
        //  10  Packaging        — fold, poly-bag, box (bundle)
        //  11  Sampling         — sample development (pcs)
        //  12  Freight Inward   — inward courier/transport (lbs)
        //  13  Freight Outward  — outward dispatch to customer (box)
        //
        // expense_account_id references:
        //   24 = Weaving Service Cost
        //   25 = Processing / Printing Service Cost
        //   26 = Dyeing Service Cost
        //   27 = Finishing Service Cost
        //   28 = Packaging Labour Cost
        //   29 = Other Service Cost
        //   22 = Outward Freight Expense
        //   23 = Inward Freight Expense
        // ─────────────────────────────────────────────────────────────────

        DB::table('services')->insert([
            ['id' =>  1, 'name' => 'Weaving',          'description' => 'Convert yarn to greige fabric',         'unit_id' => 6, 'expense_account_id' => 24, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  2, 'name' => 'Bleaching',         'description' => 'Bleach greige to white base',          'unit_id' => 6, 'expense_account_id' => 25, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  3, 'name' => 'Dyeing',            'description' => 'Apply reactive / vat dyes to fabric',  'unit_id' => 6, 'expense_account_id' => 26, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  4, 'name' => 'Printing',          'description' => 'Screen or digital print on fabric',    'unit_id' => 7, 'expense_account_id' => 25, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  5, 'name' => 'Processing',        'description' => 'Scouring, mercerising, singeing',      'unit_id' => 6, 'expense_account_id' => 25, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  6, 'name' => 'Finishing',         'description' => 'Sanforizing, calendering, softening',  'unit_id' => 7, 'expense_account_id' => 27, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  7, 'name' => 'Embroidery',        'description' => 'Decorative machine embroidery',        'unit_id' => 3, 'expense_account_id' => 29, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  8, 'name' => 'Cutting',           'description' => 'Cut fabric to required dimensions',    'unit_id' => 3, 'expense_account_id' => 29, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' =>  9, 'name' => 'Stitching',         'description' => 'Hem, border, or garment stitching',    'unit_id' => 3, 'expense_account_id' => 29, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 10, 'name' => 'Packaging',         'description' => 'Fold, poly-bag, box finished goods',   'unit_id' => 5, 'expense_account_id' => 28, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Sampling',          'description' => 'Sample development and dispatch',      'unit_id' => 3, 'expense_account_id' => 29, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 12, 'name' => 'Freight Inward',    'description' => 'Inward courier / transport charges',   'unit_id' => 6, 'expense_account_id' => 23, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 13, 'name' => 'Freight Outward',   'description' => 'Outward dispatch to customer',         'unit_id' => 9, 'expense_account_id' => 22, 'is_active' => 1, 'created_by' => 2, 'updated_by' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // ─────────────────────────────────────────────────────────────────
        // SERVICE ↔ VENDOR PIVOT
        //
        // Each service linked to its dedicated vendor (rate=0, set via UI).
        // Additional vendors can be linked from the Services screen.
        //
        // Service → Primary Vendor mapping:
        //   Weaving (1)         → Crescent Weaving Mills (2)
        //   Bleaching (2)       → Royal Processing House (3)
        //   Dyeing (3)          → Colour Line Dyeing Unit (4)
        //   Printing (4)        → Digital Print Studio (9)
        //   Processing (5)      → Royal Processing House (3)
        //   Finishing (6)       → Pak Finishing Works (5)
        //   Embroidery (7)      → Star Embroidery Works (8)
        //   Cutting (8)         → General Contractor (10)
        //   Stitching (9)       → General Contractor (10)
        //   Packaging (10)      → SafePack Industries (6)
        //   Sampling (11)       → General Contractor (10)
        //   Freight Inward (12) → TCS Courier (7)
        //   Freight Outward(13) → TCS Courier (7)
        // ─────────────────────────────────────────────────────────────────

        DB::table('service_vendor')->insert([
            ['service_id' =>  1, 'vendor_id' =>  2, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Weaving → Crescent
            ['service_id' =>  2, 'vendor_id' =>  3, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Bleaching → Royal
            ['service_id' =>  3, 'vendor_id' =>  4, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Dyeing → Colour Line
            ['service_id' =>  4, 'vendor_id' =>  9, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Printing → Digital Print
            ['service_id' =>  5, 'vendor_id' =>  3, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Processing → Royal
            ['service_id' =>  6, 'vendor_id' =>  5, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Finishing → Pak Finishing
            ['service_id' =>  7, 'vendor_id' =>  8, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Embroidery → Star
            ['service_id' =>  8, 'vendor_id' => 10, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Cutting → General
            ['service_id' =>  9, 'vendor_id' => 10, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Stitching → General
            ['service_id' => 10, 'vendor_id' =>  6, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Packaging → SafePack
            ['service_id' => 11, 'vendor_id' => 10, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Sampling → General
            ['service_id' => 12, 'vendor_id' =>  7, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Freight In → TCS
            ['service_id' => 13, 'vendor_id' =>  7, 'rate' => 0, 'currency' => 'PKR', 'notes' => null, 'created_at' => $now, 'updated_at' => $now], // Freight Out → TCS
        ]);
    }
}