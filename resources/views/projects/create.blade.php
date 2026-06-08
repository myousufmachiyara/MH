@extends('layouts.app')

@section('title', 'Projects | New Project')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('projects.store') }}" method="POST"
          onkeydown="return event.key != 'Enter';">
      @csrf

      @if($errors->any())
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          <ul class="mb-0">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Project</h2>
          <a href="{{ route('projects.index') }}" class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </header>

        <div class="card-body">
          <div class="row">

            <div class="col-md-4 mb-3">
              <label class="form-label">Customer / Brand <span class="text-danger">*</span></label>
              <select name="customer_id" class="form-control select2-js" required>
                <option value="" disabled selected>Select Customer</option>
                @foreach($customers as $customer)
                  <option value="{{ $customer->id }}" {{ old('customer_id') == $customer->id ? 'selected' : '' }}>
                    {{ $customer->name }}
                  </option>
                @endforeach
              </select>
              @error('customer_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Customer PO Number</label>
              <input type="text" name="customer_po_no" class="form-control"
                     placeholder="PO number received from customer"
                     value="{{ old('customer_po_no') }}">
              @error('customer_po_no')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Order Date</label>
              <input type="date" name="order_date" class="form-control"
                     value="{{ old('order_date', date('Y-m-d')) }}">
              @error('order_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-8 mb-3">
              <label class="form-label">Project Title / Description <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control"
                     placeholder="Brief description of the order (e.g. 5000 Lbs Cotton Fabric — Red Stripe)"
                     value="{{ old('title') }}" required>
              @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Expected Delivery Date</label>
              <input type="date" name="delivery_date" class="form-control"
                     value="{{ old('delivery_date') }}">
              @error('delivery_date')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-12 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3"
                        placeholder="Any additional notes or instructions">{{ old('notes') }}</textarea>
            </div>

          </div>
        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('projects.index') }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Create Project
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
  $('select.select2-js').select2({ width: '100%' });
});
</script>
@endpush

@endsection