@extends('layouts.app')

@section('title', 'Sampling | New Sample — ' . $project->project_no)

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('projects.sampling.store', $project->id) }}" method="POST"
          onkeydown="return event.key != 'Enter';">
      @csrf

      @if($errors->any())
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          <ul class="mb-0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
          </ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">
            New Sample
            <small class="text-muted ms-2">{{ $project->project_no }} — {{ Str::limit($project->title, 40) }}</small>
          </h2>
          <a href="{{ route('projects.show', $project->id) }}" class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back to Project
          </a>
        </header>

        <div class="card-body">
          <div class="row">

            {{-- ── Courier & Dispatch ───────────────────────────── --}}
            <div class="col-md-3 mb-3">
              <label class="form-label">Courier Name</label>
              <input type="text" name="courier_name" class="form-control"
                     placeholder="e.g. TCS, Leopards" value="{{ old('courier_name') }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Tracking Number</label>
              <input type="text" name="tracking_no" class="form-control"
                     placeholder="Tracking / waybill number" value="{{ old('tracking_no') }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Dispatch Date</label>
              <input type="date" name="dispatched_at" class="form-control"
                     value="{{ old('dispatched_at', date('Y-m-d')) }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Customer Received Date</label>
              <input type="date" name="received_at" class="form-control"
                     value="{{ old('received_at') }}">
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"
                        placeholder="Any notes about this sample">{{ old('notes') }}</textarea>
            </div>

            <div class="col-md-6 mb-3 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" name="include_in_project_costing"
                       id="include_costing" value="1"
                       {{ old('include_in_project_costing') ? 'checked' : '' }}>
                <label class="form-check-label" for="include_costing">
                  Include sampling cost in project costing / invoice
                </label>
              </div>
            </div>

          </div>

          {{-- ── Cost Rows ────────────────────────────────────────── --}}
          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="card-title mb-0">Sampling Costs</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addCostRow">
              <i class="fas fa-plus"></i> Add Cost
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered" id="costsTable">
              <thead>
                <tr>
                  <th style="width:45%">Description <span class="text-danger">*</span></th>
                  <th style="width:10%">Amount (PKR) <span class="text-danger">*</span></th>
                  <th style="width:20%">Borne By</th>
                  <th style="width:20%">In Project Costing</th>
                  <th style="width:5%"></th>
                </tr>
              </thead>
              <tbody id="costRows">
                {{-- Default first row --}}
                <tr>
                  <td>
                    <input type="text" name="costs[0][description]" class="form-control"
                           placeholder="e.g. Sample production, Courier charge">
                  </td>
                  <td>
                    <input type="number" name="costs[0][amount]" class="form-control cost-amount"
                           step="any" min="0" value="0">
                  </td>
                  <td>
                    <select name="costs[0][borne_by]" class="form-control form-control-sm">
                      <option value="company">Company</option>
                      <option value="customer">Customer</option>
                    </select>
                  </td>
                  <td class="text-center align-middle">
                    <input type="checkbox" name="costs[0][include_in_project_costing]"
                           value="1">
                  </td>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-cost-row">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="table-light">
                  <td colspan="1" class="text-end fw-bold">Total:</td>
                  <td><strong id="totalCost">0.00</strong></td>
                  <td colspan="3"></td>
                </tr>
              </tfoot>
            </table>
          </div>

        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('projects.show', $project->id) }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Create Sample
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

@push('scripts')
<script>
var costIndex = 1;

function recalcTotal() {
  var total = 0;
  $('#costRows .cost-amount').each(function() {
    total += parseFloat($(this).val()) || 0;
  });
  $('#totalCost').text(total.toFixed(2));
}

$(document).ready(function() {
  $(document).on('input', '.cost-amount', recalcTotal);

  $('#addCostRow').on('click', function() {
    var html =
      '<tr>' +
        '<td><input type="text" name="costs[' + costIndex + '][description]" class="form-control" placeholder="e.g. Sample production, Courier charge"></td>' +
        '<td><input type="number" name="costs[' + costIndex + '][amount]" class="form-control cost-amount" step="any" min="0" value="0"></td>' +
        '<td>' +
          '<select name="costs[' + costIndex + '][borne_by]" class="form-control form-control-sm">' +
            '<option value="company">Company</option>' +
            '<option value="customer">Customer</option>' +
          '</select>' +
        '</td>' +
        '<td class="text-center align-middle">' +
          '<input type="checkbox" name="costs[' + costIndex + '][include_in_project_costing]" value="1">' +
        '</td>' +
        '<td class="text-center align-middle">' +
          '<button type="button" class="btn btn-sm btn-outline-danger remove-cost-row">' +
            '<i class="fas fa-times"></i>' +
          '</button>' +
        '</td>' +
      '</tr>';
    $('#costRows').append(html);
    costIndex++;
  });

  $(document).on('click', '.remove-cost-row', function() {
    $(this).closest('tr').remove();
    recalcTotal();
  });
});
</script>
@endpush

@endsection