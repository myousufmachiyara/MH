@extends('layouts.app')

@section('title', 'Job Receive | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('job_receives.store') }}" method="POST" onkeydown="return event.key != 'Enter';" enctype="multipart/form-data">
      @csrf
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">New Job Receive</h2>
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

          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Job Order <span class="text-danger">*</span></label>
              <select name="job_order_id" id="job_order_select" class="form-control select2-js" required>
                <option value="">Select Job Order</option>
                @foreach ($jobOrders as $job)
                  <option value="{{ $job->id }}">
                    {{ $job->job_no }} — {{ $job->vendor->name ?? '' }} ({{ $job->status }})
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Receive Date</label>
              <input type="date" name="receive_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Processing Charge</label>
              <input type="number" name="processing_charge" class="form-control" step="any" min="0" value="0">
              <small class="text-muted">Vendor's labour fee — posts as an accounts payable.</small>
            </div>

            <div class="col-md-2 mb-3">
              <label>Attachments</label>
              <input type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.zip">
            </div>

            <div class="col-md-12 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <div class="alert alert-info py-2" id="loadingMsg">
            Select a job order to see outstanding raw material.
          </div>

          <div class="table-responsive mb-3" id="itemsSection" style="display:none">
            <table class="table table-bordered" id="itemsTable">
              <thead>
                <tr>
                  <th>Raw Product</th>
                  <th>Outstanding</th>
                  <th>Qty Consumed</th>
                  <th>Leftover (auto)</th>
                  <th>Output Product</th>
                  <th>Output Quantity</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="itemsBody"></tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" id="addRowBtn">
              <i class="fas fa-plus"></i> Add Another Item
            </button>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Receive</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  const outputProducts = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name]));
  let outstandingItems = []; // current job order's outstanding raw products
  let rowIndex = 0;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#job_order_select').on('change', function () {
    const jobId = $(this).val();
    $('#itemsBody').empty();
    rowIndex = 0;

    if (!jobId) {
      $('#itemsSection').hide();
      $('#loadingMsg').show().text('Select a job order to see outstanding raw material.');
      return;
    }

    $('#loadingMsg').show().text('Loading outstanding stock...');

    fetch(`/job-receives/outstanding/${jobId}`)
      .then(res => res.json())
      .then(data => {
        outstandingItems = data;

        if (data.length === 0) {
          $('#itemsSection').hide();
          $('#loadingMsg').show().text('No outstanding raw material for this job order — it may already be fully received.');
          return;
        }

        $('#loadingMsg').hide();
        $('#itemsSection').show();

        // Pre-add one row per outstanding product, same as before,
        // but now the row itself is a normal free-form row (product
        // is selectable/changeable, and more rows can be added).
        data.forEach(item => addRow(item.product_id, item.outstanding));
      });
  });

  function rawProductOptionsHtml(selectedId) {
    let html = '<option value="">Select Product</option>';
    outstandingItems.forEach(item => {
      const sel = item.product_id == selectedId ? 'selected' : '';
      html += `<option value="${item.product_id}" data-outstanding="${item.outstanding}" ${sel}>
                 ${item.product_name} (outstanding: ${item.outstanding})
               </option>`;
    });
    return html;
  }

  function outputProductOptionsHtml() {
    let html = '<option value="">— None —</option>';
    outputProducts.forEach(p => {
      html += `<option value="${p.id}">${p.name}</option>`;
    });
    return html;
  }

  function addRow(preselectProductId = null, preselectOutstanding = null) {
    const idx = rowIndex++;

    const row = $(`
      <tr class="item-row">
        <td>
          <select name="items[${idx}][raw_product_id]" class="form-control select2-js raw-product-select" required>
            ${rawProductOptionsHtml(preselectProductId)}
          </select>
        </td>
        <td class="outstanding-cell">${preselectOutstanding ?? '—'}</td>
        <td>
          <input type="number" name="items[${idx}][quantity_consumed]" class="form-control consumed-input"
                 value="0" step="any" min="0" max="${preselectOutstanding ?? ''}"
                 data-outstanding="${preselectOutstanding ?? 0}">
        </td>
        <td class="leftover-cell">${preselectOutstanding ?? 0}</td>
        <td>
          <select name="items[${idx}][output_product_id]" class="form-control select2-js">
            ${outputProductOptionsHtml()}
          </select>
        </td>
        <td>
          <input type="number" name="items[${idx}][quantity_output]" class="form-control" value="0" step="any" min="0">
        </td>
        <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
      </tr>
    `);

    $('#itemsBody').append(row);
    row.find('.select2-js').select2({ width: '100%' });
  }

  $('#addRowBtn').on('click', function () {
    addRow();
  });

  $(document).on('change', '.raw-product-select', function () {
    const selected = $(this).find('option:selected');
    const outstanding = parseFloat(selected.data('outstanding')) || 0;
    const row = $(this).closest('tr');

    row.find('.outstanding-cell').text(outstanding || '—');
    row.find('.consumed-input').attr('max', outstanding).attr('data-outstanding', outstanding);
    row.find('.leftover-cell').text(outstanding.toFixed(3));
  });

  $(document).on('input', '.consumed-input', function () {
    const outstanding = parseFloat($(this).data('outstanding')) || 0;
    const consumed = parseFloat($(this).val()) || 0;
    const leftover = Math.max(0, outstanding - consumed);
    $(this).closest('tr').find('.leftover-cell').text(leftover.toFixed(3));
  });

  $(document).on('click', '.remove-row', function () {
    if ($('.item-row').length > 1) {
      $(this).closest('tr').remove();
    }
  });
</script>
@endsection