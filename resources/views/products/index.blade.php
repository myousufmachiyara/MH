@extends('layouts.app')

@section('title', 'Products | All Products')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">

      @if(session('success'))
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('error') }}
        </div>
      @endif

      <header class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="card-title">All Products</h2>
          @can('products.create')
          <a href="{{ route('products.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Product
          </a>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-products">
            <thead>
              <tr>
                <th>S.No</th>
                <th>Item Name</th>
                <th>SKU</th>
                <th>Category</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($products as $index => $product)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td><strong>{{ $product->name }}</strong></td>
                <td><code>{{ $product->sku }}</code></td>
                <td>
                  {{ $product->category->name ?? '—' }}
                  @if($product->subcategory)
                    <span class="text-muted">/ {{ $product->subcategory->name }}</span>
                  @endif
                </td>
                <td>{{ optional($product->measurementUnit)->shortcode ?? '—' }}</td>
                <td>
                  <span class="badge {{ $product->is_active ? 'bg-success' : 'bg-secondary' }}">
                    {{ $product->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>
                  @can('products.edit')
                  <a href="{{ route('products.edit', $product->id) }}" class="text-primary me-1" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('products.delete')
                  <form method="POST" action="{{ route('products.destroy', $product->id) }}" class="d-inline"
                        onsubmit="return confirm('Delete {{ addslashes($product->name) }}? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete">
                      <i class="fa fa-trash-alt"></i>
                    </button>
                  </form>
                  @endcan
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

@push('scripts')
<script>
$(document).ready(function () {
  $('#datatable-products').DataTable({
    pageLength: 100,
    order: [[1, 'asc']],
  });
});
</script>
@endpush

@endsection