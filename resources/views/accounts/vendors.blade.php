@extends('layouts.app')

@section('title', 'Vendors')

@section('content')

@php
    $vendorTypes = \App\Models\Vendor::TYPES;
    $typeBadge   = [
        'spinning_mill'   => 'bg-primary',
        'weaving_mill'    => 'bg-info',
        'processing_mill' => 'bg-warning text-dark',
        'packager'        => 'bg-success',
        'courier'         => 'bg-secondary',
        'other'           => 'bg-dark',
    ];
@endphp

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
          <h2 class="card-title">All Vendors</h2>
          @can('vendors.create')
          <button type="button" class="modal-with-form btn btn-primary" href="#addVendorModal">
            <i class="fas fa-plus"></i> Add Vendor
          </button>
          @endcan
        </div>
      </header>

      <div class="card-body">
        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-vendors">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Type</th>
                <th>Phone</th>
                <th>City</th>
                <th>Opening Balance</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($vendors as $vendor)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td>
                  <strong>{{ $vendor->name }}</strong>
                  @if($vendor->contact_person)
                    <br><small class="text-muted">{{ $vendor->contact_person }}</small>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $typeBadge[$vendor->vendor_type] ?? 'bg-secondary' }}">
                    {{ $vendorTypes[$vendor->vendor_type] ?? $vendor->vendor_type }}
                  </span>
                </td>
                <td>{{ $vendor->phone ?? '—' }}</td>
                <td>{{ $vendor->city ?? '—' }}</td>
                <td>
                  @if($vendor->opening_balance > 0)
                    <span class="text-{{ $vendor->opening_balance_type === 'debit' ? 'success' : 'danger' }}">
                      {{ number_format($vendor->opening_balance, 2) }}
                      <small>({{ ucfirst($vendor->opening_balance_type) }})</small>
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  <span class="badge {{ $vendor->is_active ? 'bg-success' : 'bg-secondary' }}">
                    {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td>
                  @can('vendors.edit')
                  <a href="javascript:void(0);" class="text-primary me-1"
                     onclick="editVendor({{ $vendor->id }})" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('vendors.delete')
                  <form action="{{ route('vendors.destroy', $vendor->id) }}" method="POST"
                        class="d-inline"
                        onsubmit="return confirm('Delete {{ addslashes($vendor->name) }}? This cannot be undone.')">
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
    @can('vendors.create')
    <div id="addVendorModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" action="{{ route('vendors.store') }}"
              onkeydown="return event.key != 'Enter';">
          @csrf
          <header class="card-header">
            <h2 class="card-title">Add New Vendor</h2>
          </header>
          <div class="card-body">
            <div class="row">

              <div class="col-lg-6 mb-2">
                <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name"
                       placeholder="Vendor name" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="vendor_type" required>
                  <option value="" disabled selected>Select Type</option>
                  @foreach($vendorTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person"
                       placeholder="Contact person name">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone"
                       placeholder="Phone number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email"
                       placeholder="Email address">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city"
                       placeholder="City">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" rows="2"
                          placeholder="Full address"></textarea>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">NTN / Tax Number</label>
                <input type="text" class="form-control" name="ntn"
                       placeholder="National Tax Number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Status</label>
                <select class="form-control" name="is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              {{-- Opening Balance Section --}}
              <div class="col-12 mt-2 mb-1">
                <hr class="my-1">
                <small class="text-muted">Opening Balance (optional)</small>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Opening Balance</label>
                <input type="number" class="form-control" name="opening_balance"
                       value="0" step="any" min="0" placeholder="0.00">
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Type</label>
                <select class="form-control" name="opening_balance_type">
                  <option value="credit">Credit (we owe vendor)</option>
                  <option value="debit">Debit (vendor owes us)</option>
                </select>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Date</label>
                <input type="date" class="form-control" name="opening_balance_date"
                       value="{{ date('Y-m-d') }}">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" rows="2"
                          placeholder="Any additional notes"></textarea>
              </div>

            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Add Vendor</button>
              <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
            </div>
          </footer>
        </form>
      </section>
    </div>
    @endcan

    {{-- ════════════════════════════════════════════════════════════
         EDIT MODAL
    ════════════════════════════════════════════════════════════ --}}
    @can('vendors.edit')
    <div id="editVendorModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST" id="editVendorForm" action=""
              onkeydown="return event.key != 'Enter';">
          @csrf
          @method('PUT')
          <header class="card-header">
            <h2 class="card-title">Edit Vendor</h2>
          </header>
          <div class="card-body">
            <div class="row">

              <div class="col-lg-6 mb-2">
                <label class="form-label">Vendor Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name"
                       id="ev_name" placeholder="Vendor name" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                <select class="form-control select2-js" name="vendor_type"
                        id="ev_vendor_type" required>
                  @foreach($vendorTypes as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Contact Person</label>
                <input type="text" class="form-control" name="contact_person"
                       id="ev_contact_person" placeholder="Contact person name">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone"
                       id="ev_phone" placeholder="Phone number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email"
                       id="ev_email" placeholder="Email address">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city"
                       id="ev_city" placeholder="City">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" id="ev_address"
                          rows="2" placeholder="Full address"></textarea>
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">NTN / Tax Number</label>
                <input type="text" class="form-control" name="ntn"
                       id="ev_ntn" placeholder="National Tax Number">
              </div>

              <div class="col-lg-6 mb-2">
                <label class="form-label">Status</label>
                <select class="form-control" name="is_active" id="ev_is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>

              <div class="col-12 mt-2 mb-1">
                <hr class="my-1">
                <small class="text-muted">Opening Balance</small>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Opening Balance</label>
                <input type="number" class="form-control" name="opening_balance"
                       id="ev_opening_balance" step="any" min="0">
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Type</label>
                <select class="form-control" name="opening_balance_type"
                        id="ev_opening_balance_type">
                  <option value="credit">Credit (we owe vendor)</option>
                  <option value="debit">Debit (vendor owes us)</option>
                </select>
              </div>

              <div class="col-lg-4 mb-2">
                <label class="form-label">Balance Date</label>
                <input type="date" class="form-control" name="opening_balance_date"
                       id="ev_opening_balance_date">
              </div>

              <div class="col-lg-12 mb-2">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="notes" id="ev_notes"
                          rows="2" placeholder="Any additional notes"></textarea>
              </div>

            </div>
          </div>
          <footer class="card-footer">
            <div class="text-end">
              <button type="submit" class="btn btn-primary">Update Vendor</button>
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
$(document).ready(function () {
  $('#datatable-vendors').DataTable({
    pageLength: 50,
    order: [[1, 'asc']],
  });

  // Init Select2 inside modals
  $('#addVendorModal .select2-js').select2({ width: '100%', dropdownParent: $('#addVendorModal') });
  $('#editVendorModal .select2-js').select2({ width: '100%', dropdownParent: $('#editVendorModal') });
});

function editVendor(id) {
  fetch('/vendors/' + id + '/edit', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function(res) {
    if (!res.ok) throw new Error('Server error: ' + res.status);
    return res.json();
  })
  .then(function(data) {
    // Set form action
    $('#editVendorForm').attr('action', '/vendors/' + id);

    // Populate fields
    $('#ev_name').val(data.name);
    $('#ev_contact_person').val(data.contact_person ?? '');
    $('#ev_phone').val(data.phone ?? '');
    $('#ev_email').val(data.email ?? '');
    $('#ev_city').val(data.city ?? '');
    $('#ev_address').val(data.address ?? '');
    $('#ev_ntn').val(data.ntn ?? '');
    $('#ev_notes').val(data.notes ?? '');
    $('#ev_opening_balance').val(data.opening_balance ?? 0);
    $('#ev_opening_balance_date').val(data.opening_balance_date
      ? data.opening_balance_date.substring(0, 10) : '');

    // Select2 fields — trigger('change') to update visual
    $('#ev_vendor_type').val(data.vendor_type).trigger('change');
    $('#ev_opening_balance_type').val(data.opening_balance_type ?? 'credit').trigger('change');
    $('#ev_is_active').val(data.is_active ? '1' : '0').trigger('change');

    $.magnificPopup.open({
      items: { src: '#editVendorModal' },
      type: 'inline'
    });
  })
  .catch(function(err) {
    console.error('[Vendor] editVendor failed:', err);
    alert('Could not load vendor data. Please try again.');
  });
}
</script>
@endpush

@endsection