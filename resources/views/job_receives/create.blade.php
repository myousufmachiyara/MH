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
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Raw Product</th>
                  <th>Outstanding (Issued)</th>
                  <th>Qty Consumed</th>
                  <th>Leftover (auto)</th>
                  <th>Output Product</th>
                  <th>Output Quantity</th>
                </tr>
              </thead>
              <tbody id="itemsBody"></tbody>
            </table>
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
  const productOptions = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name]));

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%' });
  });

  $('#job_order_select').on('change', function () {
    const jobId = $(this).val();
    if (!jobId) {
      $('#itemsSection').hide();
      $('#loadingMsg').show().text('Select a job order to see outstanding raw material.');
      return;
    }

    $('#loadingMsg').show().text('Loading outstanding stock...');

    fetch(`/job-receives/outstanding/${jobId}`)
      .then(res => res.json())
      .then(data => {
        if (data.length === 0) {
          $('#itemsSection').hide();
          $('#loadingMsg').show().text('No outstanding raw material for this job order — it may already be fully received.');
          return;
        }

        $('#loadingMsg').hide();
        const tbody = $('#itemsBody');
        tbody.empty();

        const outputOptionsHtml = '<option value="">— None —</option>' +
          productOptions.map(p => `<option value="${p.id}">${p.name}</option>`).join('');

        data.forEach((item, idx) => {
          tbody.append(`
            <tr>
              <td>
                ${item.product_name}
                <input type="hidden" name="items[${idx}][raw_product_id]" value="${item.product_id}">
              </td>
              <td>${item.outstanding}</td>
              <td><input type="number" name="items[${idx}][quantity_consumed]" class="form-control consumed-input"
                    value="0" step="any" min="0" max="${item.outstanding}"
                    data-outstanding="${item.outstanding}" onchange="calcLeftover(this)"></td>
              <td class="leftover-cell">${item.outstanding}</td>
              <td>
                <select name="items[${idx}][output_product_id]" class="form-control select2-js">
                  ${outputOptionsHtml}
                </select>
              </td>
              <td><input type="number" name="items[${idx}][quantity_output]" class="form-control" value="0" step="any" min="0"></td>
            </tr>
          `);
        });

        $('#itemsSection').show();
        $('.select2-js').select2({ width: '100%' });
      });
  });

  function calcLeftover(input) {
    const outstanding = parseFloat($(input).data('outstanding')) || 0;
    const consumed = parseFloat($(input).val()) || 0;
    const leftover = Math.max(0, outstanding - consumed);
    $(input).closest('tr').find('.leftover-cell').text(leftover.toFixed(3));
  }
</script>
@endsection