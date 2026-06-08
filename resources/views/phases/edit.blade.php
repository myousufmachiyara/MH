@extends('layouts.app')

@section('title', 'Phase | Edit — ' . $project->project_no)

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('projects.phases.update', [$project->id, $phase->id]) }}"
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

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('error') }}
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">
            Edit Phase {{ $phase->phase_order }}
            <small class="text-muted ms-2">{{ $project->project_no }}</small>
          </h2>
          <a href="{{ route('projects.phases.show', [$project->id, $phase->id]) }}"
             class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </header>

        <div class="card-body">
          <div class="row">

            <div class="col-md-2 mb-3">
              <label class="form-label">Phase Order <span class="text-danger">*</span></label>
              <input type="number" name="phase_order" class="form-control"
                     value="{{ old('phase_order', $phase->phase_order) }}" min="1" required>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Service <span class="text-danger">*</span></label>
              <select name="service_id" id="service_id" class="form-control select2-js" required>
                <option value="" disabled>Select Service</option>
                @foreach($services as $service)
                  <option value="{{ $service->id }}"
                    {{ optional(optional($phase->serviceVendor)->service)->id == $service->id ? 'selected' : '' }}>
                    {{ $service->name }}
                    @if($service->unit) ({{ $service->unit->shortcode }}) @endif
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" id="vendor_id" class="form-control" required>
                {{-- Pre-populated via JS on load --}}
                @if($phase->serviceVendor)
                <option value="{{ optional($phase->serviceVendor)->vendor_id }}" selected
                        data-rate="{{ $phase->serviceVendor->rate }}">
                  {{ optional(optional($phase->serviceVendor)->vendor)->name }}
                </option>
                @endif
              </select>
              <small class="text-muted" id="vendor_help"></small>
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Rate (PKR) <span class="text-danger">*</span></label>
              <input type="number" name="rate" id="rate" class="form-control"
                     step="any" min="0"
                     value="{{ old('rate', $phase->rate) }}" required>
            </div>

            <div class="col-md-12 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control"
                        rows="2">{{ old('notes', $phase->notes) }}</textarea>
            </div>

          </div>

          {{-- ── Materials ────────────────────────────────────────── --}}
          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h5 class="mb-0">Materials Used in this Phase</h5>
              <small class="text-muted">Products consumed (packaging, chemicals, etc.)</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addMaterialRow">
              <i class="fas fa-plus"></i> Add Material
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered" id="materialsTable">
              <thead>
                <tr>
                  <th>Product</th>
                  <th style="width:130px">Quantity</th>
                  <th style="width:130px">Rate (PKR)</th>
                  <th style="width:130px">Total</th>
                  <th style="width:200px">Notes</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="materialRows">
                @foreach($phase->materials as $i => $mat)
                <tr>
                  <td>
                    <select name="materials[{{ $i }}][product_id]"
                            class="form-control form-control-sm select2-mat">
                      <option value="">Select Product</option>
                      @foreach($products as $product)
                        <option value="{{ $product->id }}"
                          {{ $mat->product_id == $product->id ? 'selected' : '' }}>
                          {{ $product->name }} ({{ $product->sku }})
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" name="materials[{{ $i }}][quantity]"
                           class="form-control form-control-sm mat-qty"
                           step="any" min="0" value="{{ $mat->quantity }}">
                  </td>
                  <td>
                    <input type="number" name="materials[{{ $i }}][rate]"
                           class="form-control form-control-sm mat-rate"
                           step="any" min="0" value="{{ $mat->rate }}">
                  </td>
                  <td>
                    <span class="mat-total">{{ number_format($mat->total_cost, 2) }}</span>
                    <input type="hidden" name="materials[{{ $i }}][total_cost]"
                           class="mat-total-input" value="{{ $mat->total_cost }}">
                  </td>
                  <td>
                    <input type="text" name="materials[{{ $i }}][notes]"
                           class="form-control form-control-sm"
                           value="{{ $mat->notes }}" placeholder="Optional">
                  </td>
                  <td class="text-center align-middle">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-mat-row">
                      <i class="fas fa-times"></i>
                    </button>
                  </td>
                </tr>
                @endforeach
              </tbody>
              <tfoot>
                <tr class="table-light">
                  <td colspan="3" class="text-end fw-bold">Materials Total:</td>
                  <td><strong id="materialsTotal">{{ number_format($phase->materials->sum('total_cost'), 2) }}</strong></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>

        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('projects.phases.show', [$project->id, $phase->id]) }}"
             class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Update Phase
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

@push('scripts')
<script>
var matIndex  = {{ $phase->materials->count() }};
var csrfToken = $('meta[name="csrf-token"]').attr('content');

var allProducts = {!! $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->toJson() !!};

// Current service/vendor for pre-population
var currentServiceId = {{ optional(optional($phase->serviceVendor)->service)->id ?? 'null' }};
var currentVendorId  = {{ optional($phase->serviceVendor)->vendor_id ?? 'null' }};
var currentRate      = {{ $phase->rate }};

$(document).ready(function () {
  $('#service_id').select2({ width: '100%' });
  $('.select2-mat').select2({ width: '100%' });

  // Load vendors for current service on page load
  if (currentServiceId) {
    loadVendors(currentServiceId, currentVendorId, currentRate);
  }

  $('#service_id').on('change', function () {
    loadVendors($(this).val(), null, null);
  });

  $('#vendor_id').on('change', function () {
    var rate = $(this).find(':selected').data('rate');
    if (rate !== undefined) $('#rate').val(parseFloat(rate).toFixed(2));
  });

  $('#addMaterialRow').on('click', addMaterialRow);

  $(document).on('click', '.remove-mat-row', function () {
    $(this).closest('tr').remove();
    recalcMaterialsTotal();
  });

  $(document).on('input', '.mat-qty, .mat-rate', function () {
    var row   = $(this).closest('tr');
    var qty   = parseFloat(row.find('.mat-qty').val()) || 0;
    var rate  = parseFloat(row.find('.mat-rate').val()) || 0;
    var total = (qty * rate).toFixed(2);
    row.find('.mat-total').text(total);
    row.find('.mat-total-input').val(total);
    recalcMaterialsTotal();
  });

  recalcMaterialsTotal();
});

function loadVendors(serviceId, selectedVendorId, selectedRate) {
  var vendorSel = $('#vendor_id');
  vendorSel.empty().append('<option value="">Loading…</option>').prop('disabled', true);

  $.ajax({
    url:     '/helpers/services/' + serviceId + '/vendors',
    type:    'GET',
    headers: { 'X-CSRF-TOKEN': csrfToken },
    success: function (data) {
      vendorSel.empty().append('<option value="" disabled selected>Select Vendor</option>');
      if (data.vendors && data.vendors.length > 0) {
        $.each(data.vendors, function (i, v) {
          var sel  = (v.id == selectedVendorId) ? 'selected' : '';
          var rate = (v.id == selectedVendorId && selectedRate !== null) ? selectedRate : v.rate;
          vendorSel.append(
            '<option value="' + v.id + '" data-rate="' + v.rate + '" ' + sel + '>' +
              v.name + ' — ' + parseFloat(v.rate).toFixed(2) + ' PKR' +
            '</option>'
          );
        });
        vendorSel.prop('disabled', false);
        // Set rate from selected vendor
        if (selectedRate !== null) {
          $('#rate').val(parseFloat(selectedRate).toFixed(2));
        } else {
          var firstRate = vendorSel.find('option:selected').data('rate');
          if (firstRate) $('#rate').val(parseFloat(firstRate).toFixed(2));
        }
      } else {
        vendorSel.append('<option value="" disabled>No vendors linked</option>');
      }
    }
  });
}

function addMaterialRow() {
  var opts = '<option value="">Select Product</option>';
  allProducts.forEach(function (p) {
    opts += '<option value="' + p.id + '">' + p.name + ' (' + p.sku + ')</option>';
  });

  var html =
    '<tr>' +
      '<td><select name="materials[' + matIndex + '][product_id]" class="form-control form-control-sm select2-mat">' + opts + '</select></td>' +
      '<td><input type="number" name="materials[' + matIndex + '][quantity]" class="form-control form-control-sm mat-qty" step="any" min="0" value="0"></td>' +
      '<td><input type="number" name="materials[' + matIndex + '][rate]" class="form-control form-control-sm mat-rate" step="any" min="0" value="0"></td>' +
      '<td><span class="mat-total">0.00</span><input type="hidden" name="materials[' + matIndex + '][total_cost]" class="mat-total-input" value="0"></td>' +
      '<td><input type="text" name="materials[' + matIndex + '][notes]" class="form-control form-control-sm" placeholder="Optional"></td>' +
      '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-mat-row"><i class="fas fa-times"></i></button></td>' +
    '</tr>';

  $('#materialRows').append(html);
  $('#materialRows .select2-mat').last().select2({ width: '100%' });
  matIndex++;
}

function recalcMaterialsTotal() {
  var total = 0;
  $('#materialRows .mat-total').each(function () {
    total += parseFloat($(this).text()) || 0;
  });
  $('#materialsTotal').text(total.toFixed(2));
}
</script>
@endpush

@endsection