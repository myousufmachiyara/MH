@extends('layouts.app')

@section('title', 'Phase | New Phase — ' . $project->project_no)

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('projects.phases.store', $project->id) }}" method="POST"
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

      @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('error') }}
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">
            New Production Phase
            <small class="text-muted ms-2">
              {{ $project->project_no }} — {{ Str::limit($project->title, 40) }}
            </small>
          </h2>
          <a href="{{ route('projects.show', $project->id) }}" class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back to Project
          </a>
        </header>

        <div class="card-body">
          <div class="row">

            {{-- Phase Order --}}
            <div class="col-md-2 mb-3">
              <label class="form-label">Phase Order <span class="text-danger">*</span></label>
              <input type="number" name="phase_order" class="form-control"
                     value="{{ old('phase_order', $nextOrder) }}" min="1" required>
              <small class="text-muted">Sequence within this project</small>
            </div>

            {{-- Service --}}
            <div class="col-md-4 mb-3">
              <label class="form-label">Service <span class="text-danger">*</span></label>
              <select name="service_id" id="service_id" class="form-control select2-js" required>
                <option value="" disabled selected>Select Service</option>
                @foreach($services as $service)
                  <option value="{{ $service->id }}"
                    {{ old('service_id') == $service->id ? 'selected' : '' }}>
                    {{ $service->name }}
                    @if($service->unit) ({{ $service->unit->shortcode }}) @endif
                  </option>
                @endforeach
              </select>
            </div>

            {{-- Vendor — populated via AJAX when service is selected --}}
            <div class="col-md-4 mb-3">
              <label class="form-label">Vendor <span class="text-danger">*</span></label>
              <select name="vendor_id" id="vendor_id" class="form-control" required disabled>
                <option value="">Select service first</option>
              </select>
              <small class="text-muted" id="vendor_help"></small>
            </div>

            {{-- Rate — auto-filled from service_vendor, editable --}}
            <div class="col-md-2 mb-3">
              <label class="form-label">Rate (PKR) <span class="text-danger">*</span></label>
              <input type="number" name="rate" id="rate" class="form-control"
                     step="any" min="0" value="{{ old('rate', 0) }}" required>
              <small class="text-muted">Per unit rate</small>
            </div>

            <div class="col-md-12 mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2"
                        placeholder="Any instructions or notes for this phase">{{ old('notes') }}</textarea>
            </div>

          </div>

          {{-- ── Materials Section ────────────────────────────────── --}}
          <hr>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <h5 class="mb-0">Materials Used in this Phase</h5>
              <small class="text-muted">Products consumed during this phase (packaging, chemicals, etc.)</small>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addMaterialRow">
              <i class="fas fa-plus"></i> Add Material
            </button>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered" id="materialsTable">
              <thead>
                <tr>
                  <th>Product <span class="text-danger">*</span></th>
                  <th style="width:130px">Quantity <span class="text-danger">*</span></th>
                  <th style="width:130px">Rate (PKR)</th>
                  <th style="width:130px">Total</th>
                  <th style="width:200px">Notes</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="materialRows">
                {{-- JS adds rows here --}}
              </tbody>
              <tfoot>
                <tr class="table-light">
                  <td colspan="3" class="text-end fw-bold">Materials Total:</td>
                  <td><strong id="materialsTotal">0.00</strong></td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </table>
          </div>

        </div>

        <footer class="card-footer text-end">
          <a href="{{ route('projects.show', $project->id) }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Create Phase
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

@push('scripts')
<script>
var matIndex   = 0;
var csrfToken  = $('meta[name="csrf-token"]').attr('content');

// Products for material rows
var allProducts = {!! $products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku])->toJson() !!};

$(document).ready(function () {
  $('#service_id').select2({ width: '100%' });

  // ── Service change: load vendors via AJAX ──────────────────────
  $('#service_id').on('change', function () {
    var serviceId = $(this).val();
    var vendorSel = $('#vendor_id');

    vendorSel.empty().append('<option value="">Loading…</option>').prop('disabled', true);
    $('#vendor_help').text('');
    $('#rate').val(0);

    if (!serviceId) return;

    $.ajax({
      url:  '/helpers/services/' + serviceId + '/vendors',
      type: 'GET',
      headers: { 'X-CSRF-TOKEN': csrfToken },
      success: function (data) {
        vendorSel.empty().append('<option value="" disabled selected>Select Vendor</option>');
        if (data.vendors && data.vendors.length > 0) {
          $.each(data.vendors, function (i, v) {
            vendorSel.append(
              '<option value="' + v.id + '" data-rate="' + v.rate + '">' +
                v.name + ' — ' + parseFloat(v.rate).toFixed(2) + ' PKR' +
              '</option>'
            );
          });
          vendorSel.prop('disabled', false);
          $('#vendor_help').text(data.vendors.length + ' vendor(s) available for this service.');
        } else {
          vendorSel.append('<option value="" disabled>No vendors linked to this service</option>');
          $('#vendor_help').html('<span class="text-danger">No vendors. Go to Services → Edit to add vendors.</span>');
        }
      },
      error: function () {
        vendorSel.empty().append('<option value="">Failed to load vendors</option>');
      }
    });
  });

  // ── Vendor change: auto-fill rate ─────────────────────────────
  $('#vendor_id').on('change', function () {
    var rate = $(this).find(':selected').data('rate');
    if (rate !== undefined) {
      $('#rate').val(parseFloat(rate).toFixed(2));
    }
  });

  // ── Materials: add row ────────────────────────────────────────
  $('#addMaterialRow').on('click', function () {
    addMaterialRow();
  });

  // ── Materials: remove row ─────────────────────────────────────
  $(document).on('click', '.remove-mat-row', function () {
    $(this).closest('tr').remove();
    recalcMaterialsTotal();
  });

  // ── Materials: recalc total on qty/rate change ────────────────
  $(document).on('input', '.mat-qty, .mat-rate', function () {
    var row   = $(this).closest('tr');
    var qty   = parseFloat(row.find('.mat-qty').val()) || 0;
    var rate  = parseFloat(row.find('.mat-rate').val()) || 0;
    var total = (qty * rate).toFixed(2);
    row.find('.mat-total').text(total);
    row.find('.mat-total-input').val(total);
    recalcMaterialsTotal();
  });
});

function addMaterialRow (product_id, product_name, quantity, rate, notes) {
  product_id   = product_id   || '';
  product_name = product_name || '';
  quantity     = quantity     || 0;
  rate         = rate         || 0;
  notes        = notes        || '';

  // Build product options
  var opts = '<option value="">Select Product</option>';
  allProducts.forEach(function (p) {
    var sel = (p.id == product_id) ? 'selected' : '';
    opts += '<option value="' + p.id + '" ' + sel + '>' + p.name + ' (' + p.sku + ')</option>';
  });

  var total = ((parseFloat(quantity) || 0) * (parseFloat(rate) || 0)).toFixed(2);

  var html =
    '<tr>' +
      '<td><select name="materials[' + matIndex + '][product_id]" class="form-control form-control-sm select2-mat">' + opts + '</select></td>' +
      '<td><input type="number" name="materials[' + matIndex + '][quantity]" class="form-control form-control-sm mat-qty" step="any" min="0" value="' + quantity + '"></td>' +
      '<td><input type="number" name="materials[' + matIndex + '][rate]" class="form-control form-control-sm mat-rate" step="any" min="0" value="' + rate + '"></td>' +
      '<td>' +
        '<span class="mat-total">' + total + '</span>' +
        '<input type="hidden" name="materials[' + matIndex + '][total_cost]" class="mat-total-input" value="' + total + '">' +
      '</td>' +
      '<td><input type="text" name="materials[' + matIndex + '][notes]" class="form-control form-control-sm" value="' + notes + '" placeholder="Optional"></td>' +
      '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-outline-danger remove-mat-row"><i class="fas fa-times"></i></button></td>' +
    '</tr>';

  $('#materialRows').append(html);
  $('#materialRows .select2-mat').last().select2({ width: '100%' });
  matIndex++;
  recalcMaterialsTotal();
}

function recalcMaterialsTotal () {
  var total = 0;
  $('#materialRows .mat-total').each(function () {
    total += parseFloat($(this).text()) || 0;
  });
  $('#materialsTotal').text(total.toFixed(2));
}
</script>
@endpush

@endsection