@extends('layouts.app')

@section('title', 'Projects | Edit — ' . $project->project_no)

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('projects.update', $project->id) }}" method="POST"
          onkeydown="return event.key != 'Enter';">
      @csrf
      @method('PUT')

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
          <h2 class="card-title">
            Edit Project
            <small class="text-muted ms-2">{{ $project->project_no }}</small>
          </h2>
          <a href="{{ route('projects.show', $project->id) }}" class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back to Project
          </a>
        </header>

        <div class="card-body">
          <div class="row">

            <div class="col-md-4 mb-3">
              <label class="form-label">Customer / Brand <span class="text-danger">*</span></label>
              <select name="customer_id" class="form-control select2-js" required>
                @foreach($customers as $customer)
                  <option value="{{ $customer->id }}"
                    {{ old('customer_id', $project->customer_id) == $customer->id ? 'selected' : '' }}>
                    {{ $customer->name }}
                  </option>
                @endforeach
              </select>
              @error('customer_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Customer PO Number</label>
              <input type="text" name="customer_po_no" class="form-control"
                     value="{{ old('customer_po_no', $project->customer_po_no) }}"
                     placeholder="PO number from customer">
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Order Date</label>
              <input type="date" name="order_date" class="form-control"
                     value="{{ old('order_date', optional($project->order_date)->format('Y-m-d')) }}">
            </div>

            <div class="col-md-8 mb-3">
              <label class="form-label">Project Title / Description <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control"
                     value="{{ old('title', $project->title) }}" required>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Expected Delivery Date</label>
              <input type="date" name="delivery_date" class="form-control"
                     value="{{ old('delivery_date', optional($project->delivery_date)->format('Y-m-d')) }}">
            </div>

            <div class="col-md-12 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3">{{ old('notes', $project->notes) }}</textarea>
            </div>

          </div>
        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('projects.show', $project->id) }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Project
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