@extends('layouts.app')

@section('title', 'Gate Pass | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('gate_passes.update', $gatePass->doc_no) }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Gate Pass — {{ $gatePass->doc_no }}</h2>
        </header>

        <div class="card-body">

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <div class="alert alert-warning py-2">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Editing will replace all items on this gate pass. If any item has already
            been issued to a job, editing/saving will be blocked to protect stock traceability.
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Date <span class="text-danger">*</span></label>
              <input type="date" name="entry_date" class="form-control" value="{{ $gatePass->entry_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-4 mb-3">
              <label>Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}" @selected($vendor->id == $gatePass->vendor_id)>{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label>Remarks</label>
              <input type="text" name="remarks" class="form-control" value="{{ $gatePass->remarks }}">
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Product</th>
                  <th>Quantity</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                @foreach($gatePass->items as $i => $item)
                <tr class="item-row">
                  <td class="serial-no">{{ $i + 1 }}</td>
                  <td>
                    <select name="items[{{ $i }}][product_id]" class="form-control select2-js">
                      <option value="">Select Product</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($product->id == $item->product_id)>{{ $product->name }} ({{ $product->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control" step="any" min="0.001" value="{{ $item->quantity }}" required></td>
                  <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                </tr>
                @endforeach
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn"><i class="fas fa-plus"></i> Add Item</button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Gate Pass</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var rowIndex = {{ $gatePass->items->count() }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  function updateSerialNumbers() {
    $('#itemsBody tr').each(function (i) {
      $(this).find('.serial-no').text(i + 1);
    });
  }

  function removeRow(button) {
    if ($('#itemsBody tr').length > 1) {
      $(button).closest('tr').remove();
      updateSerialNumbers();
    }
  }

  $('#addRowBtn').on('click', function () {
    const newRow = `
      <tr class="item-row">
        <td class="serial-no"></td>
        <td>
          <select name="items[${rowIndex}][product_id]" class="form-control select2-js">
            <option value="">Select Product</option>
            @foreach ($products as $product)
              <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
            @endforeach
          </select>
        </td>
        <td><input type="number" name="items[${rowIndex}][quantity]" class="form-control" step="any" min="0.001" required></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
      </tr>
    `;
    $('#itemsBody').append(newRow);
    $('#itemsBody tr:last .select2-js').select2({ width: '100%' });
    rowIndex++;
    updateSerialNumbers();
  });
</script>
@endsection