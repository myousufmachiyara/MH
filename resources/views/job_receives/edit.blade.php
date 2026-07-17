@extends('layouts.app')

@section('title', 'Job Receive | Edit')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('job_receives.update', $receive->id) }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">Edit Job Receive — {{ $receive->receive_no }}</h2>
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
            Saving will reverse and redo this receive's stock/leftover/voucher entries against current data.
          </div>

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Job Order</label>
              <input type="text" class="form-control" value="{{ $receive->jobOrder->job_no ?? 'N/A' }} — {{ $receive->jobOrder->vendor->name ?? '' }}" disabled>
              <small class="text-muted">Job order cannot be changed after creation.</small>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receive Date</label>
              <input type="date" name="receive_date" class="form-control" value="{{ $receive->receive_date->format('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Add More Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
              @if($receive->attachments)
                <small class="text-muted d-block mt-1">
                  Existing:
                  @foreach($receive->attachments as $path)
                    <a href="{{ Storage::url($path) }}" target="_blank"><i class="fas fa-file"></i></a>
                  @endforeach
                </small>
              @endif
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2">{{ $receive->remarks }}</textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Raw Product</th>
                  <th>Qty Consumed</th>
                  <th>Output Product</th>
                  <th>Output Qty</th>
                  <th>Rate / Unit</th>
                  <th>Amount</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody">
                @foreach($receive->items as $i => $item)
                <tr class="item-row">
                  <td>
                    <select name="items[{{ $i }}][raw_product_id]" class="form-control select2-js" required>
                      <option value="">Select Product</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $item->raw_product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $i }}][quantity_consumed]" class="form-control" step="any" min="0" value="{{ $item->quantity_consumed }}"></td>
                  <td>
                    <select name="items[{{ $i }}][output_product_id]" class="form-control select2-js">
                      <option value="">— None —</option>
                      @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($p->id == $item->output_product_id)>{{ $p->name }} ({{ $p->sku }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[{{ $i }}][quantity_output]" class="form-control output-qty-input" step="any" min="0" value="{{ $item->quantity_output }}"></td>
                  <td><input type="number" name="items[{{ $i }}][conversion_rate]" class="form-control rate-input" step="any" min="0" value="{{ $item->conversion_rate }}"></td>
                  <td class="line-amount-cell text-end">{{ number_format($item->processing_amount, 2) }}</td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="5" class="text-end fw-bold">Calculated Total:</td>
                  <td class="fw-bold" id="calcTotal">{{ number_format($receive->items->sum('processing_amount'), 2) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add Item
            </button>
          </div>

          <div class="row mt-3">
            <div class="col-md-4">
              <label>Processing Charge Override (optional)</label>
              <input type="number" name="processing_charge_override" id="chargeOverride" class="form-control" step="any" min="0"
                     value="{{ $receive->processing_charge != $receive->items->sum('processing_amount') ? $receive->processing_charge : '' }}"
                     placeholder="Leave blank to use calculated total">
              <small class="text-muted">Only fill this if the vendor's actual invoice differs from the calculated amount above.</small>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Receive</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  let rowIndex = {{ $receive->items->count() }};

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  function outputProductOptionsHtml() {
    let html = '<option value="">— None —</option>';
    @foreach ($products as $p)
      html += `<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>`;
    @endforeach
    return html;
  }

  function rawProductOptionsHtml() {
    let html = '<option value="">Select Product</option>';
    @foreach ($products as $p)
      html += `<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>`;
    @endforeach
    return html;
  }

  $('#addRowBtn').on('click', function () {
    const idx = rowIndex++;
    const row = $(`
      <tr class="item-row">
        <td>
          <select name="items[${idx}][raw_product_id]" class="form-control select2-js" required>
            ${rawProductOptionsHtml()}
          </select>
        </td>
        <td><input type="number" name="items[${idx}][quantity_consumed]" class="form-control" step="any" min="0" value="0"></td>
        <td>
          <select name="items[${idx}][output_product_id]" class="form-control select2-js">
            ${outputProductOptionsHtml()}
          </select>
        </td>
        <td><input type="number" name="items[${idx}][quantity_output]" class="form-control output-qty-input" step="any" min="0" value="0"></td>
        <td><input type="number" name="items[${idx}][conversion_rate]" class="form-control rate-input" step="any" min="0" value="0"></td>
        <td class="line-amount-cell text-end">0.00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);
    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  });

  $(document).on('input', '.output-qty-input, .rate-input', function () {
    recalcLine($(this).closest('tr'));
  });

  function recalcLine(row) {
    const outputQty = parseFloat(row.find('.output-qty-input').val()) || 0;
    const rate = parseFloat(row.find('.rate-input').val()) || 0;
    const amount = outputQty * rate;
    row.find('.line-amount-cell').text(amount.toFixed(2));
    recalcTotal();
  }

  function recalcTotal() {
    let total = 0;
    $('.item-row').each(function () {
      total += parseFloat($(this).find('.line-amount-cell').text()) || 0;
    });
    $('#calcTotal').text(total.toFixed(2));
  }

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
      recalcTotal();
    }
  });
</script>
@endsection