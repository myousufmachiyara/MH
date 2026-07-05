@extends('layouts.app')

@section('title', 'Account Mappings')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Account Mappings</h4>
            <small class="text-muted">Bind system roles to your chart of accounts</small>
        </div>
        <a href="{{ route('coa.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Chart of Accounts
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-1"></i>
        Assign each system role to one of your accounts. The system uses these when it
        auto-creates vouchers for sales, purchases, payments, and expenses.
    </div>

    <form action="{{ route('account-mappings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:30%">System Role</th>
                                <th style="width:40%">Description</th>
                                <th style="width:30%">Mapped Account</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($mappings as $m)
                            <tr>
                                <td>
                                    <strong>{{ $m['label'] }}</strong>
                                    <span class="badge bg-light text-dark border ms-1">{{ $m['type'] }}</span>
                                </td>
                                <td class="text-muted small">{{ $m['hint'] }}</td>
                                <td>
                                    <select name="mappings[{{ $m['role_key'] }}]"
                                            class="form-select form-select-sm select2">
                                        <option value="">— Not set —</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}"
                                                @selected($m['account_id'] == $acc->id)>
                                                {{ $acc->account_code }} — {{ $acc->name }}@if($acc->account_type) ({{ $acc->account_type }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> Save Mappings
            </button>
        </div>
    </form>

</div>
@endsection

@push('scripts')
<script>
    // if your coa.blade uses select2, initialize here too for searchable pickers
    $(function () {
        if ($.fn.select2) {
            $('.select2').select2({ width: '100%' });
        }
    });
</script>
@endpush