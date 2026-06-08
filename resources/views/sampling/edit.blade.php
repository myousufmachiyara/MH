@extends('layouts.app')

@section('title', 'Sample | Edit — ' . $sample->sample_no)

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('projects.sampling.update', [$project->id, $sample->id]) }}"
          method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      @method('PUT')

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
            Edit Sample
            <small class="text-muted ms-2">{{ $sample->sample_no }}</small>
          </h2>
          <a href="{{ route('projects.sampling.show', [$project->id, $sample->id]) }}"
             class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </header>

        <div class="card-body">
          <div class="row">

            <div class="col-md-3 mb-3">
              <label class="form-label">Courier Name</label>
              <input type="text" name="courier_name" class="form-control"
                     value="{{ old('courier_name', $sample->courier_name) }}"
                     placeholder="e.g. TCS, Leopards">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Tracking Number</label>
              <input type="text" name="tracking_no" class="form-control"
                     value="{{ old('tracking_no', $sample->tracking_no) }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Dispatch Date</label>
              <input type="date" name="dispatched_at" class="form-control"
                     value="{{ old('dispatched_at', optional($sample->dispatched_at)->format('Y-m-d')) }}">
            </div>

            <div class="col-md-3 mb-3">
              <label class="form-label">Customer Received Date</label>
              <input type="date" name="received_at" class="form-control"
                     value="{{ old('received_at', optional($sample->received_at)->format('Y-m-d')) }}">
            </div>

            <div class="col-md-6 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control"
                        rows="2">{{ old('notes', $sample->notes) }}</textarea>
            </div>

            <div class="col-md-6 mb-3 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" class="form-check-input"
                       name="include_in_project_costing" id="include_costing" value="1"
                       {{ old('include_in_project_costing', $sample->include_in_project_costing) ? 'checked' : '' }}>
                <label class="form-check-label" for="include_costing">
                  Include sampling cost in project costing / invoice
                </label>
              </div>
            </div>

          </div>

          {{-- ── Cost Rows ────────────────────────────────────────── --}}
          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Sampling Costs</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addCostRow">
              <i class="fas fa-plus"></i> Add Cost
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered" id="costsTable">
              <thead>
                <tr>
                  <th>Description</th>
                  <th style="width:140px">Amount (PKR)</th>
                  <th style="width:120px">Borne By</th>
                  <th style="width:140px">In Project Costing</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="costRows">
                @forelse($sample->costs as $i => $cost)
                <tr>
                  <td>
                    <input type="text" name="costs[{{ $i }}][description]"
                           class="form-control" value="{{ $cost->description }}">
                  </td>
                  <td>
                    <input type="number" name="costs[{{ $i }}][amount]"
                           class="form-control cost-amount" step="any" min="0"
                           value="{{ $cost->amount }}">
                  </td>
                  <td>
                    <select name="costs[{{ $i }}][borne_by]"
                            class="form-control form-control-sm">
                      <option value="company" {{ $cost->borne_by === 'company' ? 'selected' : '' }}>
                        Company
                      </option>
                      <option value="customer" {{ $cost->borne_by === 'customer' ? 'selected' : '' }}>
                        Customer
                      </option>
                    </select>
                  </td>
                  <td class="text-center align-middle">
                    <input type="checkbox" class="form-check-input"
                           name="costs[{{ $i }}][include_in_project_costing]" value="1"
                           {{ $cost->include_in_project_costing ? 'checked' : '' }}>
                  </td>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn btn-sm btn-outline-danger remove-cost-row">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
                @empty
                <tr>
                  <td>
                    <input type="text" name="costs[0][description]" class="form-control"
                           placeholder="e.g. Sample production, Courier charge">
                  </td>
                  <td>
                    <input type="number" name="costs[0][amount]"
                           class="form-control cost-amount" step="any" min="0" value="0">
                  </td>
                  <td>
                    <select name="costs[0][borne_by]" class="form-control form-control-sm">
                      <option value="company">Company</option>
                      <option value="customer">Customer</option>
                    </select>
                  </td>
                  <td class="text-center align-middle">
                    <input type="checkbox" class="form-check-input"
                           name="costs[0][include_in_project_costing]" value="1">
                  </td>
                  <td class="text-center align-middle">
                    <button type="button"
                            class="btn btn-sm btn-outline-danger remove-cost-row">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
                @endforelse
              </tbody>
              <tfoot>
                <tr class="table-light">
                  <td class="text-end fw-bold">Total:</td>
                  <td><strong id="totalCost">{{ number_format($sample->total_cost, 2) }}</strong></td>
                  <td colspan="3"></td>
                </tr>
              </tfoot>
            </table>
          </div>

        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('projects.sampling.show', [$project->id, $sample->id]) }}"
             class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Sample
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

@push('scripts')
<script>
var costIndex = {{ $sample->costs->count() ?: 1 }};

function recalcTotal() {
  var total = 0;
  $('#costRows .cost-amount').each(function() {
    total += parseFloat($(this).val()) || 0;
  });
  $('#totalCost').text(total.toFixed(2));
}

$(document).ready(function() {
  recalcTotal();
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
          '<input type="checkbox" name="costs[' + costIndex + '][include_in_project_costing]" value="1" class="form-check-input">' +
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