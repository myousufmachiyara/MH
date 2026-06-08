@extends('layouts.app')

@section('title', 'Services')

@section('content')

<div class="row">
  <div class="col">
    <section class="card">

      @if(session('success'))
        <div class="alert alert-success alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('success') }}
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          {{ session('error') }}
        </div>
      @endif

      <header class="card-header">
        <div class="d-flex justify-content-between align-items-center">
          <h2 class="card-title">Services</h2>
          @can('services.create')
          <button type="button" class="modal-with-form btn btn-primary" href="#addServiceModal">
            <i class="fas fa-plus"></i> Add Service
          </button>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-services">
            <thead>
              <tr>
                <th>#</th>
                <th>Service Name</th>
                <th>Unit</th>
                <th>Expense Account</th>
                <th>Vendors</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($services as $service)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                  <strong>{{ $service->name }}</strong>
                  @if($service->description)
                    <br><small class="text-muted">{{ Str::limit($service->description, 60) }}</small>
                  @endif
                </td>
                <td>
                  @if($service->unit)
                    <code>{{ $service->unit->shortcode }}</code>
                    <small class="text-muted">{{ $service->unit->name }}</small>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  {{ optional($service->expenseAccount)->name ?? '—' }}
                </td>
                <td>
                  @if($service->vendors->count() > 0)
                    <span class="badge bg-info">{{ $service->vendors->count() }} vendor(s)</span>
                    <br>
                    @foreach($service->vendors->take(2) as $v)
                      <small class="text-muted">{{ $v->name }}</small><br>
                    @endforeach
                    @if($service->vendors->count() > 2)
                      <small class="text-muted">+{{ $service->vendors->count() - 2 }} more</small>
                    @endif
                  @else
                    <span class="text-muted">No vendors</span>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $service->is_active ? 'bg-success' : 'bg-secondary' }}">
                    {{ $service->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>
                  @can('services.edit')
                  <a href="javascript:void(0);" class="text-primary me-1"
                     onclick="editService({{ $service->id }})" title="Edit / Manage Vendors">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('services.delete')
                  <form action="{{ route('services.destroy', $service->id) }}" method="POST"
                        class="d-inline"
                        onsubmit="return confirm('Delete {{ addslashes($service->name) }}?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link p-0 m-0 text-danger" title="Delete">
                      <i class="fa fa-trash-alt"></i>
                    </button>
                  </form>
                  @endcan
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </section>

    {{-- ════════════════════════════════════════════════════════════
         ADD MODAL
    ════════════════════════════════════════════════════════════ --}}
    @can('services.create')
    <div id="addServiceModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('services.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">New Service</h2>
          </header>
          <div class="card-body">
            <div class="row">

              <div class="col-lg-6 mb-2">
                <label class="form-label">Service Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name"
                       placeholder="e.g. Weaving, Processing, Packaging" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Billing Unit</label>
                <select class="form-control select2-js" name="unit_id">
                  <option value="">— Select Unit —</option>
                  @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                  @endforeach
                </select>
                <small class="text-muted">Unit used to measure this service (e.g. Lbs for weaving)</small>
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Expense Account</label>
                <select class="form-control select2-js" name="expense_account_id">
                  <option value="">— Select Expense Account —</option>
                  @foreach($expenseAccounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->account_code }})</option>
                  @endforeach
                </select>
                <small class="text-muted">COA account debited when this service cost is posted</small>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Status</label>
                <select class="form-control" name="is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" rows="2"
                          placeholder="Optional description"></textarea>
              </div>

            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Create Service</button>
              <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>
    @endcan

    {{-- ════════════════════════════════════════════════════════════
         EDIT MODAL — includes inline vendor management
    ════════════════════════════════════════════════════════════ --}}
    @can('services.edit')
    <div id="editServiceModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="editServiceForm" action=""
              onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <header class="card-header">
            <h2 class="card-title">Edit Service</h2>
          </header>
          <div class="card-body">

            {{-- ── Service fields ──────────────────────────────── --}}
            <div class="row mb-3">

              <div class="col-lg-6 mb-2">
                <label class="form-label">Service Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name"
                       id="es_name" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Billing Unit</label>
                <select class="form-control select2-js" name="unit_id" id="es_unit_id">
                  <option value="">— Select Unit —</option>
                  @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Expense Account</label>
                <select class="form-control select2-js" name="expense_account_id"
                        id="es_expense_account_id">
                  <option value="">— Select Expense Account —</option>
                  @foreach($expenseAccounts as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->account_code }})</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Status</label>
                <select class="form-control" name="is_active" id="es_is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Description</label>
                <textarea class="form-control" name="description" id="es_description"
                          rows="2"></textarea>
              </div>

            </div>

            {{-- ── Vendor Management Section ────────────────────── --}}
            <hr>
            <h6 class="mb-3">
              <i class="fa fa-users me-1"></i> Vendors for this Service
              <small class="text-muted">(rates are locked per vendor before dispatch)</small>
            </h6>

            {{-- Existing linked vendors --}}
            <div id="es_vendor_list" class="mb-3">
              {{-- Populated by JS after fetch --}}
            </div>

            {{-- Add new vendor to this service --}}
            <div class="border rounded p-3 bg-light">
              <p class="mb-2 fw-bold text-muted small">Link a new vendor</p>
              <div class="row g-2 align-items-end">
                <div class="col-md-4">
                  <label class="form-label small">Vendor</label>
                  <select class="form-control select2-js" id="new_vendor_id">
                    <option value="">Select Vendor</option>
                    @foreach($vendors as $vendor)
                      <option value="{{ $vendor->id }}"
                              data-type="{{ $vendor->getTypeLabel() }}">
                        {{ $vendor->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Rate (PKR)</label>
                  <input type="number" class="form-control" id="new_vendor_rate"
                         step="any" min="0" placeholder="0.00">
                </div>
                <div class="col-md-3">
                  <label class="form-label small">Notes</label>
                  <input type="text" class="form-control" id="new_vendor_notes"
                         placeholder="Optional">
                </div>
                <div class="col-md-2">
                  <button type="button" class="btn btn-success w-100"
                          id="btn_attach_vendor">
                    <i class="fas fa-plus"></i> Link
                  </button>
                </div>
              </div>
              <div id="attach_vendor_msg" class="mt-2 d-none"></div>
            </div>

          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Update Service</button>
              <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>
    @endcan

  </div>
</div>

@push('scripts')
<script>
// Current service ID being edited — used by vendor attach/detach calls
var currentServiceId = null;

$(document).ready(function () {
  $('#datatable-services').DataTable({ pageLength: 50, order: [[1, 'asc']] });

  // Select2 inside add modal
  $('#addServiceModal .select2-js').select2({
    width: '100%',
    dropdownParent: $('#addServiceModal')
  });

  // Select2 inside edit modal
  $('#editServiceModal .select2-js').select2({
    width: '100%',
    dropdownParent: $('#editServiceModal')
  });
});

// ── Open edit modal ──────────────────────────────────────────────────
function editService(id) {
  currentServiceId = id;

  fetch('/services/' + id + '/edit', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function(res) {
    if (!res.ok) throw new Error('Server error: ' + res.status);
    return res.json();
  })
  .then(function(data) {
    $('#editServiceForm').attr('action', '/services/' + id);

    // Populate service fields
    $('#es_name').val(data.name);
    $('#es_description').val(data.description ?? '');
    $('#es_is_active').val(data.is_active ? '1' : '0');
    $('#es_unit_id').val(data.unit_id ?? '').trigger('change');
    $('#es_expense_account_id').val(data.expense_account_id ?? '').trigger('change');

    // Render vendor list
    renderVendorList(data.vendors);

    // Clear attach form
    $('#new_vendor_id').val('').trigger('change');
    $('#new_vendor_rate').val('');
    $('#new_vendor_notes').val('');
    $('#attach_vendor_msg').addClass('d-none').text('');

    $.magnificPopup.open({
      items: { src: '#editServiceModal' },
      type: 'inline'
    });
  })
  .catch(function(err) {
    console.error('[Service] editService failed:', err);
    alert('Could not load service data. Please try again.');
  });
}

// ── Render vendor list inside edit modal ─────────────────────────────
function renderVendorList(vendors) {
  var container = $('#es_vendor_list');
  container.empty();

  if (!vendors || vendors.length === 0) {
    container.html('<p class="text-muted small">No vendors linked yet.</p>');
    return;
  }

  var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
  html += '<thead><tr><th>Vendor</th><th>Type</th><th>Rate (PKR)</th><th>Notes</th><th></th></tr></thead><tbody>';

  vendors.forEach(function(v) {
    html +=
      '<tr id="vendor_row_' + v.id + '">' +
        '<td><strong>' + v.name + '</strong></td>' +
        '<td><small>' + v.type + '</small></td>' +
        '<td>' +
          '<input type="number" class="form-control form-control-sm vendor-rate-input" ' +
                 'data-vendor-id="' + v.id + '" value="' + v.rate + '" step="any" min="0" ' +
                 'style="width:110px">' +
        '</td>' +
        '<td>' +
          '<input type="text" class="form-control form-control-sm vendor-notes-input" ' +
                 'data-vendor-id="' + v.id + '" value="' + (v.notes ?? '') + '" ' +
                 'placeholder="Notes">' +
        '</td>' +
        '<td class="text-center">' +
          '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-update-vendor" ' +
                  'data-vendor-id="' + v.id + '" title="Save rate">' +
            '<i class="fas fa-check"></i>' +
          '</button>' +
          '<button type="button" class="btn btn-sm btn-outline-danger btn-detach-vendor" ' +
                  'data-vendor-id="' + v.id + '" data-vendor-name="' + v.name + '" title="Remove">' +
            '<i class="fas fa-times"></i>' +
          '</button>' +
        '</td>' +
      '</tr>';
  });

  html += '</tbody></table></div>';
  container.html(html);
}

// ── Update vendor rate ────────────────────────────────────────────────
$(document).on('click', '.btn-update-vendor', function() {
  var btn      = $(this);
  var vendorId = btn.data('vendor-id');
  var rate     = $('#vendor_row_' + vendorId + ' .vendor-rate-input').val();
  var notes    = $('#vendor_row_' + vendorId + ' .vendor-notes-input').val();

  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

  fetch('/services/' + currentServiceId + '/vendors/' + vendorId, {
    method:  'PUT',
    headers: {
      'Content-Type':   'application/json',
      'Accept':         'application/json',
      'X-CSRF-TOKEN':   $('meta[name="csrf-token"]').attr('content'),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ rate: rate, notes: notes, currency: 'PKR' }),
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
    if (data.success) {
      btn.removeClass('btn-outline-primary').addClass('btn-success');
      setTimeout(function() {
        btn.removeClass('btn-success').addClass('btn-outline-primary');
      }, 1500);
    } else {
      alert(data.message || 'Update failed.');
    }
  })
  .catch(function() {
    btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
    alert('Network error. Please try again.');
  });
});

// ── Detach vendor ─────────────────────────────────────────────────────
$(document).on('click', '.btn-detach-vendor', function() {
  var vendorId   = $(this).data('vendor-id');
  var vendorName = $(this).data('vendor-name');

  if (!confirm('Remove ' + vendorName + ' from this service?')) return;

  var btn = $(this);
  btn.prop('disabled', true);

  fetch('/services/' + currentServiceId + '/vendors/' + vendorId, {
    method:  'DELETE',
    headers: {
      'Accept':         'application/json',
      'X-CSRF-TOKEN':   $('meta[name="csrf-token"]').attr('content'),
      'X-Requested-With': 'XMLHttpRequest',
    },
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) {
      $('#vendor_row_' + vendorId).fadeOut(300, function() { $(this).remove(); });
    } else {
      btn.prop('disabled', false);
      alert(data.message || 'Could not remove vendor.');
    }
  })
  .catch(function() {
    btn.prop('disabled', false);
    alert('Network error. Please try again.');
  });
});

// ── Attach new vendor ─────────────────────────────────────────────────
$('#btn_attach_vendor').on('click', function() {
  var vendorId = $('#new_vendor_id').val();
  var rate     = $('#new_vendor_rate').val();
  var notes    = $('#new_vendor_notes').val();
  var msgEl    = $('#attach_vendor_msg');

  if (!vendorId) {
    msgEl.removeClass('d-none alert-success').addClass('alert alert-warning').text('Please select a vendor.');
    return;
  }
  if (!rate || parseFloat(rate) < 0) {
    msgEl.removeClass('d-none alert-success').addClass('alert alert-warning').text('Please enter a valid rate.');
    return;
  }

  var btn = $(this);
  btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  msgEl.addClass('d-none');

  fetch('/services/' + currentServiceId + '/vendors', {
    method:  'POST',
    headers: {
      'Content-Type':   'application/json',
      'Accept':         'application/json',
      'X-CSRF-TOKEN':   $('meta[name="csrf-token"]').attr('content'),
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ vendor_id: vendorId, rate: rate, notes: notes, currency: 'PKR' }),
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Link');

    if (data.success) {
      msgEl.removeClass('d-none alert-warning').addClass('alert alert-success')
           .text('Vendor linked successfully.');

      // Append new row to the vendor table
      var v = data.vendor;
      var newRow =
        '<tr id="vendor_row_' + v.id + '">' +
          '<td><strong>' + v.name + '</strong></td>' +
          '<td><small>' + v.type + '</small></td>' +
          '<td>' +
            '<input type="number" class="form-control form-control-sm vendor-rate-input" ' +
                   'data-vendor-id="' + v.id + '" value="' + v.rate + '" step="any" min="0" ' +
                   'style="width:110px">' +
          '</td>' +
          '<td>' +
            '<input type="text" class="form-control form-control-sm vendor-notes-input" ' +
                   'data-vendor-id="' + v.id + '" value="' + (v.notes ?? '') + '" placeholder="Notes">' +
          '</td>' +
          '<td class="text-center">' +
            '<button type="button" class="btn btn-sm btn-outline-primary me-1 btn-update-vendor" ' +
                    'data-vendor-id="' + v.id + '" title="Save rate">' +
              '<i class="fas fa-check"></i>' +
            '</button>' +
            '<button type="button" class="btn btn-sm btn-outline-danger btn-detach-vendor" ' +
                    'data-vendor-id="' + v.id + '" data-vendor-name="' + v.name + '" title="Remove">' +
              '<i class="fas fa-times"></i>' +
            '</button>' +
          '</td>' +
        '</tr>';

      // If table exists, append; else rebuild
      var tbody = $('#es_vendor_list table tbody');
      if (tbody.length) {
        tbody.append(newRow);
      } else {
        editService(currentServiceId); // re-fetch to render fresh
      }

      // Clear the add form
      $('#new_vendor_id').val('').trigger('change');
      $('#new_vendor_rate').val('');
      $('#new_vendor_notes').val('');

      setTimeout(function() { msgEl.addClass('d-none'); }, 2500);
    } else {
      msgEl.removeClass('d-none alert-success').addClass('alert alert-danger')
           .text(data.message || 'Failed to link vendor.');
    }
  })
  .catch(function() {
    btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Link');
    msgEl.removeClass('d-none').addClass('alert alert-danger')
         .text('Network error. Please try again.');
  });
});
</script>
@endpush

@endsection