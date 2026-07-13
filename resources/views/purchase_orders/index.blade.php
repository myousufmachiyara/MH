@extends('layouts.app')

@section('title', 'Purchase Orders | All')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @elseif (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">{{ request('view_deleted') ? 'Deleted' : 'All' }} Purchase Orders</h2>
                <div>
                    @if(request('view_deleted'))
                        <a href="{{ route('purchase_orders.index') }}" class="btn btn-default mr-2">
                            <i class="fas fa-list"></i> View Active
                        </a>
                    @else
                        <a href="{{ route('purchase_orders.index', ['view_deleted' => 1]) }}" class="btn btn-danger mr-2">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    @endif
                    <a href="{{ route('purchase_orders.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Purchase Order
                    </a>
                </div>
            </header>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="purchaseOrderTable">
                        <thead>
                            <tr>
                                <th width="4%">#</th>
                                <th>Order Date</th>
                                <th>Order #</th>
                                <th>Vendor</th>
                                <th>Expected</th>
                                <th>Status</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($orders as $index => $order)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ \Carbon\Carbon::parse($order->order_date)->format('d-M-Y') }}</td>
                                <td class="text-primary">{{ $order->order_no }}</td>
                                <td>{{ $order->vendor->name ?? 'N/A' }}</td>
                                <td>{{ $order->expected_date ? \Carbon\Carbon::parse($order->expected_date)->format('d-M-Y') : '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $order->status === 'Converted' ? 'success' : ($order->status === 'Cancelled' ? 'secondary' : 'warning text-dark') }}">
                                        {{ $order->status }}
                                    </span>
                                </td>
                                <td>
                                    @if($order->trashed())
                                        <form action="{{ route('purchase_orders.restore', $order->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-link p-0 text-success" title="Restore">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    @else
                                        @if($order->status === 'Pending')
                                        <a href="{{ route('purchase_invoices.create', ['from_order' => $order->id]) }}" class="text-success mr-2" title="Convert to Invoice">
                                            <i class="fas fa-exchange-alt"></i>
                                        </a>
                                        @endif
                                        <a href="{{ route('purchase_orders.edit', $order->id) }}" class="text-primary mr-2" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="{{ route('purchase_orders.print', $order->id) }}" target="_blank" class="text-success mr-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <form action="{{ route('purchase_orders.destroy', $order->id) }}" method="POST" style="display:inline;">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" onclick="return confirm('Move to trash?')" title="Delete">
                                                <i class="fa fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#purchaseOrderTable').DataTable({ pageLength: 50, order: [[0, 'desc']] });
    });
</script>
@endsection