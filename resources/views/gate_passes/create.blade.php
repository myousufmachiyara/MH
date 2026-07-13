@extends('layouts.app')

@section('title', 'Gate Pass | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('gate_passes.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Gate Pass</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-3 mb-3">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="entry_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Product <span class="text-danger">*</span></label>
              <select name="product_id" class="form-control select2-js" required>
                <option value="">Select Product</option>
                @foreach ($products as $product)
                  <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Quantity <span class="text-danger">*</span></label>
              <input type="number" name="quantity" class="form-control" step="any" min="0.001" required>
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. sent for weaving job"></textarea>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Gate Pass</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });
</script>
@endsection