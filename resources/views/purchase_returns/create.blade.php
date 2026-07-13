@extends('layouts.app')

@section('title', 'Purchase Return | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_returns.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Return</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Purchase Invoice <span class="text-danger">*</span></label>
              <select name="purchase_id" id="purchase_select" class="form-control select2-js" required>
                <option value="">Select Purchase Invoice</option>
                @foreach ($purchases as $purchase)
                  <option value="{{ $purchase->id }}">
                    {{ $purchase->purchase_no }} — {{ $purchase->vendor->name ?? '' }} ({{ $purchase->purchase_date->format('d-M-Y') }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-2 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="returnTable">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Purchased Qty</th>
                  <th>Return Qty</th>
                  <th>Unit Price</th>
                  <th>Amount</th>
                </tr>
              </thead>
              <tbody id="returnItemsBody">
                <tr><td colspan="5" class="text-center text-muted">Select a purchase invoice to load its items.</td></tr>
              </tbody>
            </table>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Return Total: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Return</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#purchase_select').on('change', function () {
    const purchaseId = $(this).val();
    if (!purchaseId) return;

    fetch(`/purchase-returns/purchase/${purchaseId}/items`)
      .then(res => res.json())
      .then(data => {
        const tbody = $('#returnItemsBody');
        tbody.empty();

        data.items.forEach((item, idx) => {
          tbody.append(`
            <tr>
              <td>
                ${item.product_name}
                <input type="hidden" name="items[${idx}][purchase_item_id]" value="${item.purchase_item_id}">
                <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
              </td>
              <td>${item.purchased_qty}</td>
              <td><input type="number" name="items[${idx}][quantity]" class="form-control return-qty" value="0" step="any" max="${item.purchased_qty}" onchange="rowTotal(${idx})"></td>
              <td><input type="number" name="items[${idx}][unit_price]" class="form-control return-price" value="${item.unit_price}" step="any" onchange="rowTotal(${idx})"></td>
              <td><input type="number" id="return_amount${idx}" class="form-control" value="0" step="any" disabled></td>
            </tr>
          `);
        });
      });
  });

  function rowTotal(idx) {
    const row = $(`input[name="items[${idx}][quantity]"]`).closest('tr');
    const qty = parseFloat(row.find('.return-qty').val()) || 0;
    const price = parseFloat(row.find('.return-price').val()) || 0;
    $(`#return_amount${idx}`).val((qty * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0;
    $('#returnItemsBody input[id^="return_amount"]').each(function () {
      total += parseFloat($(this).val()) || 0;
    });
    $('#netTotal').text(total.toFixed(2));
  }
</script>
@endsection