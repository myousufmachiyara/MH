@extends('layouts.app')

@section('title', 'Project — ' . $project->project_no)

@section('content')

{{-- ── Project Header ─────────────────────────────────────────────── --}}
<div class="row mb-3">
  <div class="col">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

      <div>
        <h3 class="mb-1">
          {{ $project->project_no }}
          <span class="badge {{ $project->getStatusBadge() }} ms-2">
            {{ $project->getStatusLabel() }}
          </span>
        </h3>
        <p class="text-muted mb-0">{{ $project->title }}</p>
        <small class="text-muted">
          Customer: <strong>{{ optional($project->customer)->name }}</strong>
          @if($project->customer_po_no)
            &nbsp;|&nbsp; Customer PO: <strong>{{ $project->customer_po_no }}</strong>
          @endif
          @if($project->order_date)
            &nbsp;|&nbsp; Order Date: <strong>{{ $project->order_date->format('d M Y') }}</strong>
          @endif
          @if($project->delivery_date)
            &nbsp;|&nbsp; Delivery: <strong>{{ $project->delivery_date->format('d M Y') }}</strong>
          @endif
        </small>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        @can('projects.edit')
        <div class="dropdown">
          <button class="btn btn-outline-secondary btn-sm dropdown-toggle"
                  type="button" data-bs-toggle="dropdown">
            Change Status
          </button>
          <ul class="dropdown-menu">
            @foreach(\App\Models\Project::STATUSES as $key => $label)
              @if($key !== $project->status)
              <li>
                <a class="dropdown-item" href="javascript:void(0)"
                   onclick="changeStatus('{{ $key }}', '{{ $label }}')">
                  {{ $label }}
                </a>
              </li>
              @endif
            @endforeach
          </ul>
        </div>
        <a href="{{ route('projects.edit', $project->id) }}" class="btn btn-outline-primary btn-sm">
          <i class="fas fa-edit"></i> Edit
        </a>
        @endcan
        <a href="{{ route('projects.index') }}" class="btn btn-default btn-sm">
          <i class="fas fa-arrow-left"></i> Back
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

  {{-- ── LEFT COLUMN ─────────────────────────────────────────────── --}}
  <div class="col-lg-8">

    {{-- ── Sampling ────────────────────────────────────────────────── --}}
    <section class="card mb-3">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">
          <i class="fas fa-flask me-2 text-warning"></i>Sampling
        </h2>
        @can('sampling.create')
        <a href="{{ route('projects.sampling.create', $project->id) }}"
           class="btn btn-sm btn-warning text-dark">
          <i class="fas fa-plus"></i> New Sample
        </a>
        @endcan
      </header>
      <div class="card-body p-0">
        @if($project->samples->isEmpty())
          <p class="text-muted p-3 mb-0">No samples yet.</p>
        @else
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>Sample #</th>
                <th>Status</th>
                <th>Courier</th>
                <th>Tracking</th>
                <th>Dispatched</th>
                <th>Total Cost</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($project->samples as $sample)
              <tr>
                <td><strong>{{ $sample->sample_no }}</strong></td>
                <td>
                  <span class="badge {{ $sample->getStatusBadge() }}">
                    {{ $sample->getStatusLabel() }}
                  </span>
                </td>
                <td>{{ $sample->courier_name ?? '—' }}</td>
                <td>{{ $sample->tracking_no ?? '—' }}</td>
                <td>{{ $sample->dispatched_at ? $sample->dispatched_at->format('d-m-Y') : '—' }}</td>
                <td>{{ number_format($sample->total_cost, 2) }}</td>
                <td>
                  <a href="{{ route('projects.sampling.show', [$project->id, $sample->id]) }}"
                     class="text-info"><i class="fas fa-eye"></i></a>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </section>

    {{-- ── Production Phases ────────────────────────────────────────── --}}
    <section class="card mb-3">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">
          <i class="fas fa-layer-group me-2 text-primary"></i>Production Phases
        </h2>
        @can('project_phases.create')
        <a href="{{ route('projects.phases.create', $project->id) }}"
           class="btn btn-sm btn-primary">
          <i class="fas fa-plus"></i> Add Phase
        </a>
        @endcan
      </header>
      <div class="card-body p-0">
        @if($project->phases->isEmpty())
          <p class="text-muted p-3 mb-0">No phases added yet.</p>
        @else
          <table class="table table-sm table-bordered mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th>Service</th>
                <th>Vendor</th>
                <th>Rate</th>
                <th>Dispatched</th>
                <th>Received</th>
                <th>Cost</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($project->phases as $phase)
              <tr>
                <td>{{ $phase->phase_order }}</td>
                <td>{{ optional(optional($phase->serviceVendor)->service)->name ?? '—' }}</td>
                <td>{{ optional(optional($phase->serviceVendor)->vendor)->name ?? '—' }}</td>
                <td>{{ number_format($phase->rate, 2) }}</td>
                <td>
                  {{ number_format($phase->quantity_dispatched, 3) }}
                  <small class="text-muted">
                    {{ optional(optional(optional($phase->serviceVendor)->service)->unit)->shortcode }}
                  </small>
                </td>
                <td>{{ number_format($phase->quantity_received, 3) }}</td>
                <td><strong>{{ number_format($phase->total_cost, 2) }}</strong></td>
                <td>
                  <span class="badge {{ $phase->getStatusBadge() }}">
                    {{ $phase->getStatusLabel() }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('projects.phases.show', [$project->id, $phase->id]) }}"
                     class="text-info"><i class="fas fa-eye"></i></a>
                </td>
              </tr>
              @endforeach
            </tbody>
            <tfoot>
              <tr class="table-light fw-bold">
                <td colspan="6" class="text-end">Total Phase Cost:</td>
                <td colspan="2">{{ number_format($project->phases->sum('total_cost'), 2) }}</td>
              </tr>
            </tfoot>
          </table>
        @endif
      </div>
    </section>

    {{-- ── Purchase Orders ──────────────────────────────────────────── --}}
    <section class="card mb-3">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title mb-0">
          <i class="fas fa-shopping-cart me-2 text-success"></i>Purchase Orders
        </h2>
        @can('purchase_orders.create')
        <a href="{{ route('purchase_orders.create', ['project_id' => $project->id]) }}"
           class="btn btn-sm btn-success">
          <i class="fas fa-plus"></i> New PO
        </a>
        @endcan
      </header>
      <div class="card-body">
        <p class="text-muted mb-0 small">
          Purchase Orders module not yet installed.
        </p>
      </div>
    </section>

    {{-- Notes --}}
    @if($project->notes)
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h2>
      </header>
      <div class="card-body">
        <p class="mb-0">{{ $project->notes }}</p>
      </div>
    </section>
    @endif

  </div>{{-- /col-lg-8 --}}

  {{-- ── RIGHT COLUMN ────────────────────────────────────────────── --}}
  <div class="col-lg-4">

    {{-- Costing Summary --}}
    <section class="card mb-3">
      <header class="card-header">
        <h2 class="card-title mb-0">
          <i class="fas fa-calculator me-2 text-warning"></i>Costing Summary
        </h2>
      </header>
      <div class="card-body">
        @php
          $phaseCost    = $project->phases->sum('total_cost');
          $materialCost = $project->phases->flatMap->materials->sum('total_cost');
          $samplingCost = $project->samples
              ->filter(fn($s) => $s->include_in_project_costing)
              ->sum('total_cost');
          $grandTotal   = $phaseCost + $materialCost + $samplingCost;
        @endphp
        <table class="table table-sm mb-0">
          <tr>
            <td>Phase / Service Costs</td>
            <td class="text-end">{{ number_format($phaseCost, 2) }}</td>
          </tr>
          <tr>
            <td>Packaging / Materials</td>
            <td class="text-end">{{ number_format($materialCost, 2) }}</td>
          </tr>
          <tr>
            <td>Sampling (in costing)</td>
            <td class="text-end">{{ number_format($samplingCost, 2) }}</td>
          </tr>
          <tr class="table-warning fw-bold">
            <td>Total Project Cost</td>
            <td class="text-end">{{ number_format($grandTotal, 2) }}</td>
          </tr>
        </table>
      </div>
    </section>

    {{-- Follow-up Comments --}}
    <section class="card">
      <header class="card-header">
        <h2 class="card-title mb-0">
          <i class="fas fa-comments me-2 text-secondary"></i>Follow-up / Comments
        </h2>
      </header>
      <div class="card-body">

        @can('project_comments.create')
        <div class="mb-3">
          <textarea id="new_comment" class="form-control" rows="3"
                    placeholder="Add a follow-up note…"></textarea>
          <div class="mt-2 d-flex gap-2">
            <input type="file" id="comment_attachment" class="form-control form-control-sm"
                   accept=".pdf,.jpg,.jpeg,.png,.zip,.doc,.docx">
            <button type="button" class="btn btn-sm btn-primary" id="btn_add_comment">
              <i class="fas fa-paper-plane"></i> Post
            </button>
          </div>
          <div id="comment_msg" class="mt-1 d-none"></div>
        </div>
        @endcan

        <div id="comments_list">
          @forelse($project->comments as $comment)
          <div class="border rounded p-2 mb-2 bg-light" id="comment_{{ $comment->id }}">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <strong class="text-primary small">{{ optional($comment->user)->name }}</strong>
              <small class="text-muted">
                {{ $comment->created_at->format('d M Y, h:i A') }}
              </small>
            </div>
            <p class="mb-1 small" id="comment_text_{{ $comment->id }}">
              {{ $comment->comment }}
            </p>
            @if($comment->attachment_path)
              <a href="{{ Storage::url($comment->attachment_path) }}" target="_blank"
                 class="text-info small">
                <i class="fas fa-paperclip"></i> Attachment
              </a>
            @endif
            @if(auth()->id() === $comment->user_id)
            <div class="mt-1 d-flex gap-2">
              <button type="button" class="btn btn-link btn-sm p-0 text-primary"
                      onclick="editComment({{ $comment->id }}, '{{ addslashes($comment->comment) }}')">
                <i class="fas fa-edit"></i>
              </button>
              <button type="button" class="btn btn-link btn-sm p-0 text-danger"
                      onclick="deleteComment({{ $comment->id }})">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
            @endif
          </div>
          @empty
          <p class="text-muted small" id="no_comments_msg">No comments yet.</p>
          @endforelse
        </div>

      </div>
    </section>

  </div>{{-- /col-lg-4 --}}

</div>

@push('scripts')
<script>
var projectId = {{ $project->id }};
var csrfToken = $('meta[name="csrf-token"]').attr('content');

function changeStatus(status, label) {
  if (!confirm('Change project status to "' + label + '"?')) return;
  fetch('/projects/' + projectId + '/status', {
    method:  'PATCH',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
    body:    JSON.stringify({ status: status }),
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) { window.location.reload(); }
    else { alert(data.message || 'Could not update status.'); }
  })
  .catch(function() { alert('Network error.'); });
}

$('#btn_add_comment').on('click', function() {
  var text = $('#new_comment').val().trim();
  var msg  = $('#comment_msg');
  if (!text) {
    msg.removeClass('d-none').addClass('alert alert-warning').text('Please enter a comment.');
    return;
  }
  var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  var formData = new FormData();
  formData.append('_token', csrfToken);
  formData.append('comment', text);
  var fileInput = document.getElementById('comment_attachment');
  if (fileInput.files[0]) formData.append('attachment', fileInput.files[0]);

  fetch('/projects/' + projectId + '/comments', {
    method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, body: formData,
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Post');
    if (data.success) {
      var c = data.comment;
      var html = '<div class="border rounded p-2 mb-2 bg-light" id="comment_' + c.id + '">' +
        '<div class="d-flex justify-content-between align-items-start mb-1"><strong class="text-primary small">' + c.user + '</strong><small class="text-muted">' + c.created_at + '</small></div>' +
        '<p class="mb-1 small" id="comment_text_' + c.id + '">' + c.comment + '</p>' +
        (c.attachment_path ? '<a href="/storage/' + c.attachment_path + '" target="_blank" class="text-info small"><i class="fas fa-paperclip"></i> Attachment</a>' : '') +
        '<div class="mt-1 d-flex gap-2">' +
          '<button type="button" class="btn btn-link btn-sm p-0 text-primary" onclick="editComment(' + c.id + ', \'' + c.comment.replace(/'/g, "\\'") + '\')">' +
            '<i class="fas fa-edit"></i></button>' +
          '<button type="button" class="btn btn-link btn-sm p-0 text-danger" onclick="deleteComment(' + c.id + ')">' +
            '<i class="fas fa-trash-alt"></i></button>' +
        '</div></div>';
      $('#no_comments_msg').hide();
      $('#comments_list').prepend(html);
      $('#new_comment').val('');
      fileInput.value = '';
      msg.addClass('d-none');
    } else {
      msg.removeClass('d-none').addClass('alert alert-danger').text(data.message || 'Failed.');
    }
  })
  .catch(function() {
    btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Post');
    msg.removeClass('d-none').addClass('alert alert-danger').text('Network error.');
  });
});

function editComment(id, currentText) {
  var newText = prompt('Edit comment:', currentText);
  if (!newText || newText.trim() === currentText) return;
  fetch('/projects/' + projectId + '/comments/' + id, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
    body: JSON.stringify({ comment: newText.trim() }),
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) { $('#comment_text_' + id).text(newText.trim()); }
    else { alert(data.message || 'Could not update.'); }
  })
  .catch(function() { alert('Network error.'); });
}

function deleteComment(id) {
  if (!confirm('Delete this comment?')) return;
  fetch('/projects/' + projectId + '/comments/' + id, {
    method: 'DELETE',
    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    if (data.success) { $('#comment_' + id).fadeOut(300, function() { $(this).remove(); }); }
    else { alert(data.message || 'Could not delete.'); }
  })
  .catch(function() { alert('Network error.'); });
}
</script>
@endpush

@endsection