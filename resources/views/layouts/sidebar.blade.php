<aside id="sidebar-left" class="sidebar-left">

    <div class="sidebar-header">
        <div class="sidebar-title d-flex justify-content-between">
            <a href="{{ route('dashboard') }}" class="logo">
                <img src="{{ asset('assets/img/billtrix-logo-1.png') }}" class="sidebar-logo" alt="MH Fabrics" />
            </a>
            <div class="d-md-none toggle-sidebar-left col-1"
                 data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
                <i class="fas fa-times" aria-label="Close sidebar"></i>
            </div>
        </div>
        <div class="sidebar-toggle d-none d-md-block"
             data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
            <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
        </div>
    </div>

    <div class="nano">
        <div class="nano-content">
            <nav id="menu" class="nav-main" role="navigation">
                <ul class="nav nav-main">

                    {{-- ── Dashboard (mobile: Home) ─────────────── --}}
                    <li class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('dashboard') }}">
                            <i class="fa fa-tachometer-alt" aria-hidden="true"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    {{-- ── Users & Roles ─────────────────────────── --}}
                    @if(auth()->user()->canAny(['user_roles.index', 'users.index']))
                    <li class="nav-parent {{ request()->routeIs('roles.*', 'users.*', 'permissions.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-user-shield" aria-hidden="true"></i>
                            <span>Users</span>
                        </a>
                        <ul class="nav nav-children">
                            @can('user_roles.index')
                            <li class="{{ request()->routeIs('roles.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('roles.index') }}">Roles &amp; Permissions</a>
                            </li>
                            @endcan
                            @can('users.index')
                            <li class="{{ request()->routeIs('users.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('users.index') }}">All Users</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    {{-- ── Accounts (mobile: Accounts tile) ──────── --}}
                    @if(auth()->user()->canAny(['coa.index', 'shoa.index']))
                    <li class="nav-parent {{ request()->routeIs('coa.*', 'shoa.*', 'account-mappings.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-book" aria-hidden="true"></i>
                            <span>Accounts</span>
                        </a>
                        <ul class="nav nav-children">
                            @can('coa.index')
                            <li class="{{ request()->routeIs('coa.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('coa.index') }}">Chart of Accounts</a>
                            </li>
                            @endcan
                            @can('shoa.index')
                            <li class="{{ request()->routeIs('shoa.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('shoa.index') }}">Sub Heads</a>
                            </li>
                            @endcan
                            @can('coa.index')
                            <li class="{{ request()->routeIs('account-mappings.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('account-mappings.index') }}">Account Mappings</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    {{-- ── Parties (mobile: Parties tile) ────────── --}}
                    @if(auth()->user()->canAny(['customers.index', 'vendors.index']))
                    <li class="nav-parent {{ request()->routeIs('customers.*', 'vendors.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-address-book" aria-hidden="true"></i>
                            <span>Parties</span>
                        </a>
                        <ul class="nav nav-children">
                            @can('customers.index')
                            <li class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('customers.index') }}">Customers</a>
                            </li>
                            @endcan
                            @can('vendors.index')
                            <li class="{{ request()->routeIs('vendors.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('vendors.index') }}">Vendors</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    {{-- ── Products (mobile: Products tile) ──────── --}}
                    @php
                        $productPerms = [
                            'product_categories.index', 'product_subcategories.index',
                            'attributes.index', 'measurement_units.index', 'products.index'
                        ];
                    @endphp
                    @if(auth()->user()->canAny($productPerms))
                    <li class="nav-parent {{ request()->routeIs('product_categories.*','product_subcategories.*','attributes.*','measurement_units.*','products.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-layer-group" aria-hidden="true"></i>
                            <span>Products</span>
                        </a>
                        <ul class="nav nav-children">
                            @can('measurement_units.index')
                            <li class="{{ request()->routeIs('measurement_units.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('measurement_units.index') }}">M.Units</a>
                            </li>
                            @endcan
                            @can('product_categories.index')
                            <li class="{{ request()->routeIs('product_categories.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('product_categories.index') }}">Categories</a>
                            </li>
                            @endcan
                            @can('product_subcategories.index')
                            <li class="{{ request()->routeIs('product_subcategories.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('product_subcategories.index') }}">Sub Categories</a>
                            </li>
                            @endcan
                            @can('attributes.index')
                            <li class="{{ request()->routeIs('attributes.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('attributes.index') }}">Attributes</a>
                            </li>
                            @endcan
                            @can('products.index')
                            <li class="{{ request()->routeIs('products.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('products.index') }}">All Products</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    {{-- ── Orders (mobile: Orders tile) ──────────── --}}
                    {{-- NOT YET BUILT — OrderController referenced in routes but not implemented.
                         Uncomment once built.
                    @can('orders.index')
                    <li class="{{ request()->routeIs('orders.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('orders.index') }}">
                            <i class="fa fa-shopping-bag" aria-hidden="true"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    @endcan
                    --}}

                    {{-- ── Gate Passes ────────────────────────────── --}}
                    @can('gate_passes.index')
                    <li class="{{ request()->routeIs('gate_passes.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('gate_passes.index') }}">
                            <i class="fa fa-truck-loading" aria-hidden="true"></i>
                            <span>Gate Passes</span>
                        </a>
                    </li>
                    @endcan

                    {{-- ── Purchase (Invoice / Order / Return) ───── --}}
                    @if(auth()->user()->canAny(['purchase.index']))
                    <li class="nav-parent {{ request()->routeIs('purchase_invoices.*', 'purchase_orders.*', 'purchase_returns.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-shopping-cart" aria-hidden="true"></i>
                            <span>Purchase</span>
                        </a>
                        <ul class="nav nav-children">
                            @can('purchase.index')
                            <li class="{{ request()->routeIs('purchase_orders.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('purchase_orders.index') }}">Purchase Orders</a>
                            </li>
                            <li class="{{ request()->routeIs('purchase_invoices.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('purchase_invoices.index') }}">Purchase Invoices</a>
                            </li>
                            <li class="{{ request()->routeIs('purchase_returns.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('purchase_returns.index') }}">Purchase Returns</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    {{-- ── Jobs + Job Receives + Job Types (mobile: Jobs / Receives tiles) ── --}}
                    @if(auth()->user()->canAny(['jobs.index', 'job_receives.index', 'job_types.index']))
                    <li class="nav-parent {{ request()->routeIs('jobs.*', 'job_receives.*', 'job_types.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-briefcase" aria-hidden="true"></i>
                            <span>Jobs</span>
                        </a>
                        <ul class="nav nav-children">
                            @can('jobs.index')
                            <li class="{{ request()->routeIs('jobs.*') && !request()->routeIs('jobs.receive.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('jobs.index') }}">All Jobs</a>
                            </li>
                            @endcan
                            @can('job_receives.index')
                            <li class="{{ request()->routeIs('job_receives.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('job_receives.index') }}">Job Receives</a>
                            </li>
                            @endcan
                            @can('job_types.index')
                            <li class="{{ request()->routeIs('job_types.*') ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('job-types.index') }}">Job Types</a>
                            </li>
                            @endcan
                        </ul>
                    </li>
                    @endif

                    {{-- ── Sale (Order / Invoice / Return) ───────── --}}
                    {{-- NOT YET BUILT — SaleController is a placeholder only.
                         Uncomment once the Sale module (Order/Invoice/Return) ships.
                    @can('sale.index')
                    <li class="{{ request()->routeIs('sale.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('sale.index') }}">
                            <i class="fa fa-cash-register" aria-hidden="true"></i>
                            <span>Sale</span>
                        </a>
                    </li>
                    @endcan
                    --}}

                    {{-- ── Vouchers (mobile: Payments tile) ──────── --}}
                    @can('vouchers.index')
                    <li class="nav-parent {{ request()->routeIs('vouchers.*') ? 'nav-expanded active' : '' }}">
                        <a class="nav-link" href="#">
                            <i class="fa fa-money-check-alt" aria-hidden="true"></i>
                            <span>Vouchers</span>
                        </a>
                        <ul class="nav nav-children">
                            @foreach(['receipt' => 'Receipt', 'payment' => 'Payment', 'journal' => 'Journal', 'contra' => 'Contra'] as $vtype => $vlabel)
                            <li class="{{ request()->is("vouchers/{$vtype}*") ? 'active' : '' }}">
                                <a class="nav-link" href="{{ route('vouchers.index', $vtype) }}">{{ $vlabel }}</a>
                            </li>
                            @endforeach
                        </ul>
                    </li>
                    @endcan

                    {{-- ── Expenses (mobile: Expenses tile) ──────── --}}
                    {{-- NOT YET BUILT — model/migration exist, ExpenseController not implemented.
                         Uncomment once built.
                    @can('expenses.index')
                    <li class="{{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('expenses.index') }}">
                            <i class="fa fa-receipt" aria-hidden="true"></i>
                            <span>Expenses</span>
                        </a>
                    </li>
                    @endcan
                    --}}

                    {{-- ── Reports ── --}}
                    @php
                        $accountsReportPerms = [
                            'reports.accounts_general_ledger', 'reports.accounts_trial_balance',
                            'reports.accounts_profit_loss', 'reports.accounts_balance_sheet',
                            'reports.accounts_receivables', 'reports.accounts_payables',
                            'reports.accounts_party_ledger', 'reports.accounts_cash_bank',
                            'reports.accounts_bank_reconciliation',
                        ];
                    @endphp
                    @if(
                        auth()->user()->can('reports.inventory') ||
                        auth()->user()->can('reports.purchase')  ||
                        auth()->user()->can('reports.sales')     ||
                        auth()->user()->can('reports.accounts')
                    )
                    <li class="nav-parent {{ request()->routeIs('reports.*') ? 'nav-expanded active' : '' }}">
                    <a class="nav-link" href="#">
                        <i class="fa fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <ul class="nav nav-children">
                        @can('reports.inventory')
                        <li class="{{ request()->routeIs('reports.inventory') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('reports.inventory') }}">Inventory</a>
                        </li>
                        @endcan
                        {{-- @can('reports.purchase')
                        <li class="{{ request()->routeIs('reports.purchase') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('reports.purchase') }}">Purchase</a>
                        </li>
                        @endcan
                        @can('reports.sales')
                        <li class="{{ request()->routeIs('reports.sales') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('reports.sales') }}">Sales</a>
                        </li>
                        @endcan
                        @can('reports.sales')
                        <li class="{{ request()->routeIs('reports.accounts') ? 'active' : '' }}">
                            <a class="nav-link" href="{{ route('reports.accounts') }}">Accounts</a>
                        </li>
                        @endcan --}}
                    </ul>
                    </li>
                    @endif

                </ul>
            </nav>
        </div>

        <script>
            (function () {
                if (typeof localStorage === 'undefined') return;
                var pos = localStorage.getItem('sidebar-left-position');
                if (pos !== null) {
                    var el = document.querySelector('#sidebar-left .nano-content');
                    if (el) el.scrollTop = parseInt(pos, 10);
                }
            })();
        </script>
    </div>
</aside>