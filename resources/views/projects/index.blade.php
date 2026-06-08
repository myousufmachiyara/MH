@extends('layouts.app')

@section('title', 'Projects')

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
          <h2 class="card-title">All Projects</h2>
          @can('projects.create')
          <a href="{{ route('projects.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Project
          </a>
          @endcan
        </div>
      </header>

      <div class="card-body">

        {{-- ── Status filter tabs ──────────────────────────────── --}}
        <ul class="nav nav-pills mb-3" id="statusFilter">
          <li class="nav-item">
            <a class="nav-link active" href="#" data-status="all">
              All ({{ $projects->count() }})
            </a>
          </li>
          @foreach(\App\Models\Project::STATUSES as $key => $label)
          <li class="nav-item">
            <a class="nav-link" href="#" data-status="{{ $key }}">
              {{ $label }}
              <span class="badge bg-secondary ms-1">
                {{ $projects->where('status', $key)->count() }}
              </span>
            </a>
          </li>
          @endforeach
        </ul>

        <div class="modal-wrapper table-scroll">
          <table class="table table-bordered table-striped mb-0" id="datatable-projects">
            <thead>
              <tr>
                <th>Project #</th>
                <th>Customer</th>
                <th>Title</th>
                <th>Customer PO</th>
                <th>Order Date</th>
                <th>Delivery Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($projects as $project)
              <tr data-status="{{ $project->status }}">
                <td>
                  <a href="{{ route('projects.show', $project->id) }}" class="fw-bold">
                    {{ $project->project_no }}
                  </a>
                </td>
                <td>{{ optional($project->customer)->name ?? '—' }}</td>
                <td>{{ Str::limit($project->title, 50) }}</td>
                <td>{{ $project->customer_po_no ?? '—' }}</td>
                <td>
                  {{ $project->order_date ? $project->order_date->format('d-m-Y') : '—' }}
                </td>
                <td>
                  @if($project->delivery_date)
                    @php
                      $overdue = $project->delivery_date->isPast()
                                 && !in_array($project->status, ['completed','dropped']);
                    @endphp
                    <span class="{{ $overdue ? 'text-danger fw-bold' : '' }}">
                      {{ $project->delivery_date->format('d-m-Y') }}
                    </span>
                  @else
                    —
                  @endif
                </td>
                <td>
                  <span class="badge {{ $project->getStatusBadge() }}">
                    {{ $project->getStatusLabel() }}
                  </span>
                </td>
                <td>
                  <a href="{{ route('projects.show', $project->id) }}"
                     class="text-info me-1" title="View">
                    <i class="fa fa-eye"></i>
                  </a>
                  @can('projects.edit')
                  <a href="{{ route('projects.edit', $project->id) }}"
                     class="text-primary me-1" title="Edit">
                    <i class="fa fa-edit"></i>
                  </a>
                  @endcan
                  @can('projects.delete')
                  <form action="{{ route('projects.destroy', $project->id) }}" method="POST"
                        class="d-inline"
                        onsubmit="return confirm('Delete project {{ addslashes($project->project_no) }}?')">
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
  </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {
  var table = $('#datatable-projects').DataTable({
    pageLength: 25,
    order: [[0, 'desc']],
  });

  $('#statusFilter .nav-link').on('click', function (e) {
    e.preventDefault();
    $('#statusFilter .nav-link').removeClass('active');
    $(this).addClass('active');

    var status = $(this).data('status');
    if (status === 'all') {
      table.column(6).search('').draw();
    } else {
      // Search the Status column (index 6) by the label text
      table.column(6).search($(this).text().trim().split('\n')[0].trim()).draw();
    }
  });
});
</script>
@endpush

@endsection