@extends('layouts.app')

@section('title', 'Sample — ' . $sample->sample_no)

@section('content')

{{-- ── Header ──────────────────────────────────────────────────────── --}}
<div class="row mb-3">
  <div class="col">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h3 class="mb-1">
          {{ $sample->sample_no }}
          <span class="badge {{ $sample->getStatusBadge() }} ms-2">
            {{ $sample->getStatusLabel() }}
          </span>
        </h3>
        <small class="text-muted">
          Project: <a href="{{ route('projects.show', $project->id) }}">
            <strong>{{ $project->project_no }}</strong>
          </a>
          &nbsp;|&nbsp; Customer: <strong>{{ optional($project->customer)->name }}</strong>
        </small>
      </div>

      <div class="d-flex gap-2 flex-wrap">

        {{-- Status action buttons --}}
        @can('sampling.edit')
          @if($sample->status === 'pending')
            {{-- Approve --}}
            <form method="POST"
                  action="{{ route('projects.sampling.status', [$project->id, $sample->id]) }}">
              @csrf @method('PATCH')
              <input type="hidden" name="status" value="approved">
              <button type="submit" class="btn btn-success btn-sm"
                      onclick="return confirm('Mark this sample as APPROVED?')">
                <i class="fas fa-check"></i> Approve
              </button>
            </form>

            {{-- Reject --}}
            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                    data-bs-target="#rejectModal">
              <i class="fas fa-times"></i> Reject
            </button>
          @endif

          @if($sample->status === 'rejected')
            {{-- Resample --}}
            <form method="POST"
                  action="{{ route('projects.sampling.status', [$project->id, $sample->id]) }}">
              @csrf @method('PATCH')
              <input type="hidden" name="status" value="resampled">
              <button type="submit" class="btn btn-info btn-sm text-dark"
                      onclick="return confirm('Create a new sample for resample?')">
                <i class="fas fa-redo"></i> Resample
              </button>
            </form>

            {{-- Drop --}}
            <form method="POST"
                  action="{{ route('projects.sampling.status', [$project->id, $sample->id]) }}">
              @csrf @method('PATCH')
              <input type="hidden" name="status" value="dropped">
              <button type="submit" class="btn btn-secondary btn-sm"
                      onclick="return confirm('Drop this sample and mark project as DROPPED? This cannot be undone.')">
                <i class="fas fa-ban"></i> Drop Project
              </button>
            </form>
          @endif

          @if(in_array($sample->status, ['pending', 'rejected']))
          <a href="{{ route('projects.sampling.edit', [$project->id, $sample->id]) }}"
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

  {{-- ── Sample Details ─────────────────────────────────────────── --}}
  <div class="col-md-6">
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0">Sample Details</h2>
      </header>
      <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
          <tr>
            <th style="width:180px" class="text-muted">Sample Number</th>
            <td><strong>{{ $sample->sample_no }}</strong></td>
          </tr>
          <tr>
            <th class="text-muted">Status</th>
            <td>
              <span class="badge {{ $sample->getStatusBadge() }}">
                {{ $sample->getStatusLabel() }}
              </span>
            </td>
          </tr>
          <tr>
            <th class="text-muted">Courier</th>
            <td>{{ $sample->courier_name ?? '—' }}</td>
          </tr>
          <tr>
            <th class="text-muted">Tracking No.</th>
            <td>{{ $sample->tracking_no ?? '—' }}</td>
          </tr>
          <tr>
            <th class="text-muted">Dispatched At</th>
            <td>
              {{ $sample->dispatched_at ? $sample->dispatched_at->format('d M Y') : '—' }}
            </td>
          </tr>
          <tr>
            <th class="text-muted">Received By Customer</th>
            <td>
              {{ $sample->received_at ? $sample->received_at->format('d M Y') : '—' }}
            </td>
          </tr>
          <tr>
            <th class="text-muted">In Project Costing</th>
            <td>
              <span class="badge {{ $sample->include_in_project_costing ? 'bg-success' : 'bg-secondary' }}">
                {{ $sample->include_in_project_costing ? 'Yes' : 'No' }}
              </span>
            </td>
          </tr>
          @if($sample->rejection_reason)
          <tr>
            <th class="text-muted">Rejection Reason</th>
            <td class="text-danger">{{ $sample->rejection_reason }}</td>
          </tr>
          @endif
          @if($sample->notes)
          <tr>
            <th class="text-muted">Notes</th>
            <td>{{ $sample->notes }}</td>
          </tr>
          @endif
        </table>
      </div>
    </section>
  </div>

  {{-- ── Cost Breakdown ─────────────────────────────────────────── --}}
  <div class="col-md-6">
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0">Cost Breakdown</h2>
      </header>
      <div class="card-body p-0">
        @if($sample->costs->isEmpty())
          <p class="text-muted p-3 mb-0">No costs recorded.</p>
        @else
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>Description</th>
                <th>Amount</th>
                <th>Borne By</th>
                <th>In Costing</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sample->costs as $cost)
              <tr>
                <td>{{ $cost->description }}</td>
                <td>{{ number_format($cost->amount, 2) }}</td>
                <td>{{ ucfirst($cost->borne_by) }}</td>
                <td>
                  <span class="badge {{ $cost->include_in_project_costing ? 'bg-success' : 'bg-secondary' }}">
                    {{ $cost->include_in_project_costing ? 'Yes' : 'No' }}
                  </span>
                </td>
              </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr class="table-warning fw-bold">
                <td>Total</td>
                <td>{{ number_format($sample->total_cost, 2) }}</td>
                <td colspan="2"></td>
              </tr>
              @if($sample->project_cost > 0)
              <tr class="table-success">
                <td>In Project Costing</td>
                <td>{{ number_format($sample->project_cost, 2) }}</td>
                <td colspan="2"></td>
              </tr>
              @endif
            </tfoot>
          </table>
        @endif
      </div>
    </section>
  </div>

</div>

{{-- ── Reject Modal ─────────────────────────────────────────────────── --}}
@can('sampling.edit')
@if($sample->status === 'pending')
<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST"
            action="{{ route('projects.sampling.status', [$project->id, $sample->id]) }}">
        @csrf @method('PATCH')
        <input type="hidden" name="status" value="rejected">
        <div class="modal-header">
          <h5 class="modal-title">Reject Sample</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
          <textarea name="rejection_reason" class="form-control" rows="3"
                    placeholder="Why is this sample being rejected?" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Reject Sample</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endcan

@endsection