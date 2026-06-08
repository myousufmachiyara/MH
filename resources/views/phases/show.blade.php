@extends('layouts.app')

@section('title', 'Phase ' . $phase->phase_order . ' — ' . $project->project_no)

@section('content')

{{-- ── Header ──────────────────────────────────────────────────────── --}}
<div class="row mb-3">
  <div class="col">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h3 class="mb-1">
          Phase {{ $phase->phase_order }}
          <span class="badge {{ $phase->getStatusBadge() }} ms-2">
            {{ $phase->getStatusLabel() }}
          </span>
        </h3>
        <small class="text-muted">
          Project: <a href="{{ route('projects.show', $project->id) }}">
            <strong>{{ $project->project_no }}</strong>
          </a>
          &nbsp;|&nbsp;
          Service: <strong>{{ optional(optional($phase->serviceVendor)->service)->name }}</strong>
          &nbsp;|&nbsp;
          Vendor: <strong>{{ optional(optional($phase->serviceVendor)->vendor)->name }}</strong>
          &nbsp;|&nbsp;
          Rate: <strong>{{ number_format($phase->rate, 2) }} PKR</strong>
        </small>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        @can('project_phases.edit')
          {{-- Dispatch button (only when pending) --}}
          @if($phase->status === 'pending')
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                  data-bs-target="#dispatchModal">
            <i class="fas fa-truck"></i> Dispatch
          </button>
          @endif

          {{-- Receive button (dispatched or partially received) --}}
          @if(in_array($phase->status, ['dispatched', 'partially_received']))
          <button type="button" class="btn btn-info btn-sm text-dark" data-bs-toggle="modal"
                  data-bs-target="#receiveModal">
            <i class="fas fa-arrow-down"></i> Record Receipt
          </button>
          @endif

          {{-- Approve / Reject (fully or partially received) --}}
          @if(in_array($phase->status, ['fully_received', 'partially_received']))
          <form method="POST"
                action="{{ route('projects.phases.status', [$project->id, $phase->id]) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="approved">
            <button type="submit" class="btn btn-success btn-sm"
                    onclick="return confirm('Approve this phase?')">
              <i class="fas fa-check"></i> Approve
            </button>
          </form>
          <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                  data-bs-target="#rejectModal">
            <i class="fas fa-times"></i> Reject
          </button>
          @endif

          @if($phase->status === 'pending')
          <a href="{{ route('projects.phases.edit', [$project->id, $phase->id]) }}"
             class="btn btn-outline-primary btn-sm">
            <i class="fas fa-edit"></i> Edit
          </a>
          @endif
        @endcan

        <a href="{{ route('projects.show', $project->id) }}" class="btn btn-default btn-sm">
          <i class="fas fa-arrow-left"></i> Back to Project
        </a>
      </div>
    </div>
  </div>
</div>

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

<div class="row">

  {{-- ── Phase Details ───────────────────────────────────────────── --}}
  <div class="col-md-5">
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0">Phase Details</h2>
      </header>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <th class="text-muted" style="width:160px">Phase Order</th>
            <td><strong>{{ $phase->phase_order }}</strong></td>
          </tr>
          <tr>
            <th class="text-muted">Service</th>
            <td>{{ optional(optional($phase->serviceVendor)->service)->name ?? '—' }}</td>
          </tr>
          <tr>
            <th class="text-muted">Vendor</th>
            <td>{{ optional(optional($phase->serviceVendor)->vendor)->name ?? '—' }}</td>
          </tr>
          <tr>
            <th class="text-muted">Rate</th>
            <td>
              {{ number_format($phase->rate, 2) }} PKR
              / {{ optional(optional(optional($phase->serviceVendor)->service)->unit)->shortcode ?? 'unit' }}
            </td>
          </tr>
          <tr>
            <th class="text-muted">Status</th>
            <td>
              <span class="badge {{ $phase->getStatusBadge() }}">
                {{ $phase->getStatusLabel() }}
              </span>
            </td>
          </tr>
          @if($phase->notes)
          <tr>
            <th class="text-muted">Notes</th>
            <td>{{ $phase->notes }}</td>
          </tr>
          @endif
          @if($phase->rejection_reason)
          <tr>
            <th class="text-muted">Rejection Reason</th>
            <td class="text-danger">{{ $phase->rejection_reason }}</td>
          </tr>
          @endif
        </table>
      </div>
    </section>

    {{-- Dispatch & Receipt Summary --}}
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0">Quantity Summary</h2>
      </header>
      <div class="card-body">
        @php
          $unit = optional(optional(optional($phase->serviceVendor)->service)->unit)->shortcode ?? 'units';
        @endphp
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <th class="text-muted">Dispatched</th>
            <td>
              {{ number_format($phase->quantity_dispatched, 3) }} {{ $unit }}
              @if($phase->dispatched_at)
                <small class="text-muted">on {{ $phase->dispatched_at->format('d M Y') }}</small>
              @endif
            </td>
          </tr>
          <tr>
            <th class="text-muted">Received</th>
            <td class="text-success">
              {{ number_format($phase->quantity_received, 3) }} {{ $unit }}
              @if($phase->received_at)
                <small class="text-muted">on {{ $phase->received_at->format('d M Y') }}</small>
              @endif
            </td>
          </tr>
          <tr>
            <th class="text-muted">Rejected</th>
            <td class="text-danger">
              {{ number_format($phase->quantity_rejected, 3) }} {{ $unit }}
            </td>
          </tr>
          <tr>
            <th class="text-muted">Outstanding</th>
            <td class="text-warning fw-bold">
              {{ number_format($phase->pending_quantity, 3) }} {{ $unit }}
            </td>
          </tr>
          <tr class="table-warning">
            <th>Service Cost</th>
            <td><strong>{{ number_format($phase->total_cost, 2) }} PKR</strong></td>
          </tr>
        </table>
      </div>
    </section>
  </div>

  {{-- ── Materials ───────────────────────────────────────────────── --}}
  <div class="col-md-7">
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0">Materials Used</h2>
      </header>
      <div class="card-body p-0">
        @if($phase->materials->isEmpty())
          <p class="text-muted p-3 mb-0">No materials recorded for this phase.</p>
        @else
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Total</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              @foreach($phase->materials as $mat)
              <tr>
                <td>{{ optional($mat->product)->name ?? '—' }}</td>
                <td>{{ number_format($mat->quantity, 3) }}</td>
                <td>{{ number_format($mat->rate, 2) }}</td>
                <td><strong>{{ number_format($mat->total_cost, 2) }}</strong></td>
                <td><small class="text-muted">{{ $mat->notes ?? '—' }}</small></td>
              </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr class="table-warning fw-bold">
                <td colspan="3" class="text-end">Materials Total:</td>
                <td>{{ number_format($phase->materials->sum('total_cost'), 2) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        @endif
      </div>
    </section>

    {{-- Total Cost Card --}}
    <section class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between">
          <span class="fw-bold">Service Cost</span>
          <span>{{ number_format($phase->total_cost, 2) }} PKR</span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="fw-bold">Materials Cost</span>
          <span>{{ number_format($phase->materials->sum('total_cost'), 2) }} PKR</span>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between fw-bold text-success">
          <span>Phase Total Cost</span>
          <span>{{ number_format($phase->total_cost + $phase->materials->sum('total_cost'), 2) }} PKR</span>
        </div>
      </div>
    </section>
  </div>

</div>

{{-- ── Dispatch Modal ───────────────────────────────────────────────── --}}
@can('project_phases.edit')
@if($phase->status === 'pending')
<div class="modal fade" id="dispatchModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"
            action="{{ route('projects.phases.dispatch', [$project->id, $phase->id]) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Dispatch Phase {{ $phase->phase_order }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">
              Quantity Dispatched
              ({{ optional(optional(optional($phase->serviceVendor)->service)->unit)->shortcode ?? 'units' }})
              <span class="text-danger">*</span>
            </label>
            <input type="number" name="quantity_dispatched" class="form-control"
                   step="any" min="0.001" required placeholder="0.000">
          </div>
          <div class="mb-3">
            <label class="form-label">Dispatch Date <span class="text-danger">*</span></label>
            <input type="date" name="dispatched_at" class="form-control"
                   value="{{ date('Y-m-d') }}" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-truck"></i> Confirm Dispatch
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

{{-- ── Receive Modal ────────────────────────────────────────────────── --}}
@if(in_array($phase->status, ['dispatched', 'partially_received']))
<div class="modal fade" id="receiveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"
            action="{{ route('projects.phases.receive', [$project->id, $phase->id]) }}">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Record Receipt — Phase {{ $phase->phase_order }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          @php $unit = optional(optional(optional($phase->serviceVendor)->service)->unit)->shortcode ?? 'units'; @endphp
          <div class="alert alert-info small mb-3">
            Dispatched: <strong>{{ number_format($phase->quantity_dispatched, 3) }} {{ $unit }}</strong>
            &nbsp;|&nbsp;
            Already received: <strong>{{ number_format($phase->quantity_received, 3) }} {{ $unit }}</strong>
            &nbsp;|&nbsp;
            Outstanding: <strong>{{ number_format($phase->pending_quantity, 3) }} {{ $unit }}</strong>
          </div>
          <div class="mb-3">
            <label class="form-label">
              Quantity Received ({{ $unit }}) <span class="text-danger">*</span>
            </label>
            <input type="number" name="quantity_received" class="form-control"
                   step="any" min="0" required placeholder="0.000">
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity Rejected ({{ $unit }})</label>
            <input type="number" name="quantity_rejected" class="form-control"
                   step="any" min="0" value="0" placeholder="0.000">
          </div>
          <div class="mb-3">
            <label class="form-label">Receipt Date <span class="text-danger">*</span></label>
            <input type="date" name="received_at" class="form-control"
                   value="{{ date('Y-m-d') }}" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-info text-dark">
            <i class="fas fa-arrow-down"></i> Confirm Receipt
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

{{-- ── Reject Modal ─────────────────────────────────────────────────── --}}
@if(in_array($phase->status, ['fully_received', 'partially_received']))
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"
            action="{{ route('projects.phases.status', [$project->id, $phase->id]) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="rejected">
        <div class="modal-header">
          <h5 class="modal-title">Reject Phase {{ $phase->phase_order }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
          <textarea name="rejection_reason" class="form-control" rows="3"
                    required placeholder="Why is this phase being rejected?"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject Phase</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endcan

@endsection