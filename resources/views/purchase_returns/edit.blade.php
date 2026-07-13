@extends('layouts.app')

@section('title', 'Purchase Return | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_returns.update', $return->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Purchase Return — {{ $return->return_no }}</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Purchase Invoice</label>
              <input type="text" class="form-control" value="{{ $return->purchase->purchase_no ?? 'N/A' }} — {{ $return->vendor->name ?? '' }}" disabled>
              <small class="text-muted">Source invoice cannot be changed after creation.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>Return Date</label>
              <input type="date" name="return_date" class="form-control" value="{{ $return->return_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($return->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($return->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-2 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1">{{ $return->remarks }}</textarea>
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
                @foreach($return->items as $i => $item)
                <tr>
                  <td>
                    {{ $item->product->name ?? 'N/A' }}
                    <input type="hidden" name="items[{{ $i }}][purchase_item_id]" value="{{ $item->purchase_item_id }}">
                    <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $item->product_id }}">
                  </td>
                  <td>{{ $item->purchaseItem->quantity ?? '—' }}</td>
                  <td><input type="number" name="items[{{ $i }}][quantity]" class="form-control return-qty" value="{{ $item->quantity }}" step="any" onchange="rowTotal({{ $i }})"></td>
                  <td><input type="number" name="items[{{ $i }}][unit_price]" class="form-control return-price" value="{{ $item->unit_price }}" step="any" onchange="rowTotal({{ $i }})"></td>
                  <td><input type="number" id="return_amount{{ $i }}" class="form-control" value="{{ $item->amount }}" step="any" disabled></td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Return Total: <strong class="text-danger">PKR <span id="netTotal">{{ number_format($return->total_amount, 2) }}</span></strong></h4>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Return</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  $(document).ready(function () { tableTotal(); });

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