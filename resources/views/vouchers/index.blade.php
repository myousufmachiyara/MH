@extends('layouts.app')

@section('title', ucfirst($type) . ' Vouchers')

@section('content')
<div class="row">
  <div class="col">
    <section class="card">
      <header class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title">
          <i class="fas fa-money-check-alt me-2"></i>
          {{ ucfirst($type) }} Vouchers
        </h2>
        @if(in_array($type, ['payment', 'receipt', 'journal', 'contra']))
        <button type="button" class="modal-with-form btn btn-primary" href="#addModal">
          <i class="fas fa-plus"></i> Add New
        </button>
        @endif
      </header>

      {{-- ── Tab bar ──────────────────────────────────────────────────────── --}}
      <div class="tabs pb-0 pt-2">
        <ul class="nav nav-tabs">
          @can('purchase.index')
            <li class="nav-item">
              <a class="nav-link {{ $type === 'purchase' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'purchase') }}">Purchase</a>
            </li>
          @endcan
          @can('sale.index')
            <li class="nav-item">
              <a class="nav-link {{ $type === 'sale' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'sale') }}">Sale</a>
            </li>
          @endcan
          @can('vouchers.index')
            <li class="nav-item">
              <a class="nav-link {{ $type === 'journal' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'journal') }}">Journal</a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ $type === 'payment' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'payment') }}">Payment</a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ $type === 'receipt' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'receipt') }}">Receipt</a>
            </li>
            <li class="nav-item">
              <a class="nav-link {{ $type === 'contra' ? 'active' : '' }}"
                 href="{{ route('vouchers.index', 'contra') }}">Contra</a>
            </li>
          @endcan
        </ul>
      </div>

      <div class="card-body">

        @if(session('success'))
          <div class="alert alert-success alert-dismissible mb-3">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('success') }}
          </div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible mb-3">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            {{ session('error') }}
          </div>
        @endif

        {{-- ── Payment channel guide ─────────────────────────────────────── --}}
        @if(in_array($type, ['payment', 'receipt']))
        <div class="alert alert-info mb-3 py-2">
          <strong><i class="fas fa-info-circle me-1"></i>Payment Channels via Chart of Accounts:</strong>
          <span class="ms-2">
            Pick the <strong>Cash</strong> or <strong>Bank</strong> account, and the account being
            {{ $type === 'payment' ? 'paid (Vendor / Expense)' : 'collected from (Customer / Income)' }}.
          </span>
        </div>
        @endif

        @if(in_array($type, ['purchase', 'sale']))
        <div class="alert alert-secondary mb-3 py-2">
          <i class="fas fa-lock me-1"></i>
          These vouchers are <strong>auto-generated</strong> from {{ $type }} invoices.
          To change an entry, edit or cancel the source document.
        </div>
        @endif

        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0" id="voucher-datatable">
            <thead>
              <tr>
                <th>Voucher #</th>
                <th>Date</th>
                <th>Debit Account(s)</th>
                <th>Credit Account(s)</th>
                <th>Source Document</th>
                <th>Remarks</th>
                <th class="text-end">Total</th>
                <th class="text-center" style="width:90px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($vouchers as $row)
                <tr>
                  <td class="fw-bold">
                    {{ $row->voucher_no }}
                    @if($row->is_auto)
                      <span class="badge bg-info text-dark ms-1" style="font-size:.65rem;">auto</span>
                    @endif
                  </td>

                  <td class="text-nowrap">{{ $row->voucher_date?->format('d-m-Y') }}</td>

                  <td style="min-width:200px">
                    @foreach($row->display_debits as $d)
                      <div class="d-flex justify-content-between gap-2 py-0" style="line-height:1.4">
                        <span style="font-size:.85rem">{{ $d['account'] }}</span>
                        <span class="text-muted text-nowrap" style="font-size:.8rem">{{ number_format($d['amount'], 2) }}</span>
                      </div>
                    @endforeach
                  </td>

                  <td style="min-width:200px">
                    @foreach($row->display_credits as $c)
                      <div class="d-flex justify-content-between gap-2 py-0" style="line-height:1.4">
                        <span style="font-size:.85rem">{{ $c['account'] }}</span>
                        <span class="text-muted text-nowrap" style="font-size:.8rem">{{ number_format($c['amount'], 2) }}</span>
                      </div>
                    @endforeach
                  </td>

                  <td>
                    @if($row->reference_label)
                      @if($row->reference_link)
                        <a href="{{ $row->reference_link }}"
                           class="badge bg-warning text-dark text-decoration-none"
                           style="font-size:.72rem;white-space:normal;" title="Open source document">
                          <i class="fas fa-external-link-alt me-1"></i>{{ $row->reference_label }}
                        </a>
                      @else
                        <span class="badge bg-secondary" style="font-size:.72rem;white-space:normal;">
                          {{ $row->reference_label }}
                        </span>
                      @endif
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>

                  <td>
                    <span class="text-muted" style="font-size:.82rem;">
                      {{ Str::limit($row->narration ?? '', 50) }}
                    </span>
                  </td>

                  <td class="text-end fw-bold text-nowrap">{{ number_format($row->display_total, 2) }}</td>

                  <td class="text-center text-nowrap">
                    {{-- Print — TCPDF endpoint to be wired up separately --}}
                    <a class="text-success me-1"
                       href="{{ route('vouchers.print', ['type' => $type, 'id' => $row->id]) }}"
                       title="Print PDF" target="_blank">
                      <i class="fas fa-print"></i>
                    </a>

                    {{-- View entries (read-only for all vouchers) --}}
                    <a class="text-primary me-1 modal-with-form"
                       href="#updateModal"
                       onclick="getVoucherDetails({{ $row->id }})"
                       title="View Entries">
                      <i class="fas fa-eye"></i>
                    </a>

                    @if(!$row->is_auto)
                      @can('vouchers.delete')
                      <a class="text-danger modal-with-form"
                         href="#deleteModal"
                         onclick="setDeleteId({{ $row->id }})"
                         title="Delete">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                      @endcan
                    @else
                      <span class="text-secondary"
                            title="Auto-generated — delete/cancel the source document to reverse"
                            style="cursor:help;">
                        <i class="fas fa-lock"></i>
                      </span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="8" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                    No {{ ucfirst($type) }} vouchers found.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </section>


    {{-- ================================================================== --}}
    {{-- ADD VOUCHER MODAL (Payment / Receipt / Journal / Contra only)       --}}
    {{-- ================================================================== --}}
    @if(in_array($type, ['payment', 'receipt']))
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST"
              action="{{ route('vouchers.store', $type) }}"
              enctype="multipart/form-data"
              onkeydown="return event.key !== 'Enter';">
          @csrf

          <header class="card-header">
            <h2 class="card-title">Add {{ ucfirst($type) }} Voucher</h2>
          </header>

          <div class="card-body">

            <div class="alert alert-light border mb-3 py-2">
              <label class="fw-bold small mb-1 d-block">
                <i class="fas fa-bolt text-warning"></i>
                Quick Select Cash / Bank
                <small class="text-muted fw-normal">(auto-fills the {{ $type === 'payment' ? 'Credit' : 'Debit' }} account)</small>
              </label>
              <div class="d-flex flex-wrap gap-2">
                @foreach($accounts->whereIn('account_type', ['cash', 'bank']) as $acc)
                  <button type="button" class="btn btn-sm btn-outline-{{ $acc->account_type === 'cash' ? 'success' : 'primary' }} channel-btn"
                          data-account-id="{{ $acc->id }}"
                          title="{{ $acc->account_code }} — {{ $acc->name }}">
                    <i class="fas fa-{{ $acc->account_type === 'cash' ? 'coins' : 'university' }} me-1"></i>{{ $acc->name }}
                  </button>
                @endforeach
              </div>
            </div>

            <div class="row">
              <div class="col-lg-6 mb-2">
                <label>Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="voucher_date"
                       value="{{ date('Y-m-d') }}" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Amount <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount"
                       step="0.01" min="0.01" value="0" required>
              </div>

              <div class="col-lg-6 mb-2">
                <label>
                  {{ $type === 'payment' ? 'Debit Account' : 'Credit Account' }}
                  <span class="text-danger">*</span>
                  <small class="text-muted d-block">
                    {{ $type === 'payment' ? 'What we owe (Vendor AP, Expense, etc.)' : 'What is collected (Customer AR, Other Income)' }}
                  </small>
                </label>
                <select class="form-control select2-js"
                        name="{{ $type === 'payment' ? 'ac_dr_sid' : 'ac_cr_sid' }}"
                        id="other_account" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts->groupBy('account_type') as $groupType => $groupAccounts)
                    <optgroup label="{{ ucfirst($groupType) }}">
                      @foreach($groupAccounts as $acc)
                        <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                      @endforeach
                    </optgroup>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>{{ $type === 'payment' ? 'Credit Account (Cash/Bank)' : 'Debit Account (Cash/Bank)' }} <span class="text-danger">*</span></label>
                <select class="form-control select2-js"
                        name="{{ $type === 'payment' ? 'ac_cr_sid' : 'ac_dr_sid' }}"
                        id="channel_account" required>
                  <option value="" disabled selected>Select Account</option>
                  @foreach($accounts->whereIn('account_type', ['cash', 'bank']) as $acc)
                    <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Party (optional)</label>
                <select class="form-control select2-js" name="party_type" id="party_type">
                  <option value="">— None —</option>
                  <option value="customer">Customer</option>
                  <option value="vendor">Vendor</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>&nbsp;</label>
                <select class="form-control select2-js" name="party_id" id="party_id">
                  <option value="">— Select party type first —</option>
                </select>
              </div>

              <div class="col-lg-6 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="attachments[]" multiple
                       accept=".zip,application/pdf,image/png,image/jpeg">
                <small class="text-muted">Max 5MB per file. PDF / JPG / PNG / ZIP.</small>
              </div>

              <div class="col-lg-12 mb-2">
                <label>Remarks</label>
                <textarea rows="2" class="form-control" name="remarks"
                          placeholder="e.g. Payment for Invoice PUR-00001 via Bank transfer"></textarea>
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Save Voucher</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>
    @endif

    @if(in_array($type, ['journal', 'contra']))
    <div id="addModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <form method="POST"
              action="{{ route('vouchers.store', $type) }}"
              enctype="multipart/form-data"
              onkeydown="return event.key !== 'Enter';">
          @csrf

          <header class="card-header">
            <h2 class="card-title">Add {{ ucfirst($type) }} Voucher</h2>
          </header>

          <div class="card-body">
            <div class="row mb-2">
              <div class="col-lg-6 mb-2">
                <label>Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="voucher_date"
                       value="{{ date('Y-m-d') }}" required>
              </div>
            </div>

            <table class="table" id="lines-table">
              <thead>
                <tr>
                  <th style="width:30%">Account</th>
                  <th style="width:20%">Party</th>
                  <th style="width:15%">Debit</th>
                  <th style="width:15%">Credit</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="lines-body">
                @for($i = 0; $i < 2; $i++)
                <tr class="line-row">
                  <td>
                    <select name="lines[{{ $i }}][account_id]" class="form-control select2-js" required>
                      <option value="">Select Account</option>
                      @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">[{{ $acc->account_code }}] {{ $acc->name }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <select name="lines[{{ $i }}][party_type]" class="form-control line-party-type">
                      <option value="">—</option>
                      <option value="customer">Customer</option>
                      <option value="vendor">Vendor</option>
                    </select>
                    <select name="lines[{{ $i }}][party_id]" class="form-control line-party-id mt-1" style="display:none"></select>
                  </td>
                  <td><input type="number" step="0.01" name="lines[{{ $i }}][debit]" class="form-control line-debit"></td>
                  <td><input type="number" step="0.01" name="lines[{{ $i }}][credit]" class="form-control line-credit"></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-line">&times;</button></td>
                </tr>
                @endfor
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="2" class="text-end fw-bold">Totals:</td>
                  <td id="total-debit" class="fw-bold">0.00</td>
                  <td id="total-credit" class="fw-bold">0.00</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
            <button type="button" id="add-line" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-plus"></i> Add Line
            </button>
            <span id="balance-warning" class="text-danger ms-3" style="display:none">
              ⚠ Debit and Credit must be equal
            </span>

            <div class="row mt-3">
              <div class="col-lg-8 mb-2">
                <label>Remarks</label>
                <textarea rows="2" class="form-control" name="remarks"></textarea>
              </div>
              <div class="col-lg-4 mb-2">
                <label>Attachments</label>
                <input type="file" class="form-control" name="attachments[]" multiple
                       accept=".zip,application/pdf,image/png,image/jpeg">
              </div>
            </div>
          </div>

          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Save Voucher</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>
    @endif


    {{-- ================================================================== --}}
    {{-- VIEW ENTRIES MODAL (read-only for ALL vouchers)                     --}}
    {{-- ================================================================== --}}
    <div id="updateModal" class="modal-block modal-block-primary mfp-hide">
      <section class="card">
        <header class="card-header">
          <h2 class="card-title">
            Voucher Entries
            <span id="edit_voucher_no" class="text-primary fs-6 ms-1"></span>
          </h2>
        </header>

        <div class="card-body">
          <div id="auto_voucher_alert" class="alert alert-info d-none"></div>

          <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
              <thead class="table-light">
                <tr>
                  <th>Account</th>
                  <th class="text-end">Debit</th>
                  <th class="text-end">Credit</th>
                  <th>Narration</th>
                </tr>
              </thead>
              <tbody id="entries_tbody"></tbody>
            </table>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="button" class="btn btn-default modal-dismiss">Close</button>
        </footer>
      </section>
    </div>


    {{-- ================================================================== --}}
    {{-- DELETE MODAL                                                         --}}
    {{-- ================================================================== --}}
    <div id="deleteModal" class="modal-block modal-block-warning mfp-hide">
      <section class="card">
        <form method="POST" id="deleteForm">
          @csrf
          @method('DELETE')
          <header class="card-header">
            <h2 class="card-title">Delete Voucher</h2>
          </header>
          <div class="card-body">
            <p class="mb-0">Are you sure you want to delete this voucher? This cannot be undone.</p>
          </div>
          <footer class="card-footer text-end">
            <button type="submit" class="btn btn-danger">Yes, Delete</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
          </footer>
        </form>
      </section>
    </div>

  </div>
</div>

<script>
const customers = @json($accounts->isNotEmpty() ? [] : []); // placeholder, replaced below
</script>
<script>
// party dropdowns need real customer/vendor lists — passed from controller
const customerList = @json(\App\Models\Customer::active()->orderBy('name')->get(['id','name']));
const vendorList    = @json(\App\Models\Vendor::active()->orderBy('name')->get(['id','name']));

function populatePartySelect(select, type) {
    select.innerHTML = '<option value="">— Select —</option>';
    const list = type === 'customer' ? customerList : (type === 'vendor' ? vendorList : []);
    list.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.name;
        select.appendChild(opt);
    });
}

// ── Payment/Receipt: quick-select channel buttons ─────────────────────────
document.querySelectorAll('.channel-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        $('#channel_account').val(this.dataset.accountId).trigger('change');
        document.querySelectorAll('.channel-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
    });
});

const partyTypeSelect = document.getElementById('party_type');
if (partyTypeSelect) {
    partyTypeSelect.addEventListener('change', function () {
        populatePartySelect(document.getElementById('party_id'), this.value);
    });
}

// ── Journal/Contra: dynamic lines ─────────────────────────────────────────
const linesBody = document.getElementById('lines-body');
if (linesBody) {
    let lineIndex = linesBody.querySelectorAll('.line-row').length;

    document.getElementById('add-line').addEventListener('click', function () {
        const row = linesBody.querySelector('.line-row').cloneNode(true);
        row.querySelectorAll('select, input').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${lineIndex}]`);
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            else el.value = '';
        });
        row.querySelector('.line-party-id').style.display = 'none';
        linesBody.appendChild(row);
        lineIndex++;
        recalcTotals();
    });

    linesBody.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-line')) {
            if (linesBody.querySelectorAll('.line-row').length > 2) {
                e.target.closest('.line-row').remove();
                recalcTotals();
            } else {
                alert('A voucher needs at least 2 lines.');
            }
        }
    });

    linesBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('line-party-type')) {
            const row = e.target.closest('.line-row');
            const idSelect = row.querySelector('.line-party-id');
            if (e.target.value) {
                idSelect.style.display = '';
                populatePartySelect(idSelect, e.target.value);
            } else {
                idSelect.style.display = 'none';
                idSelect.innerHTML = '';
            }
        }
    });

    linesBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('line-debit') || e.target.classList.contains('line-credit')) {
            recalcTotals();
        }
    });

    function recalcTotals() {
        let totalDebit = 0, totalCredit = 0;
        linesBody.querySelectorAll('.line-row').forEach(row => {
            totalDebit  += parseFloat(row.querySelector('.line-debit').value)  || 0;
            totalCredit += parseFloat(row.querySelector('.line-credit').value) || 0;
        });
        document.getElementById('total-debit').textContent  = totalDebit.toFixed(2);
        document.getElementById('total-credit').textContent = totalCredit.toFixed(2);

        const warning = document.getElementById('balance-warning');
        warning.style.display = (totalDebit.toFixed(2) !== totalCredit.toFixed(2)) ? '' : 'none';
    }
}

// ── View entries modal ─────────────────────────────────────────────────────
function getVoucherDetails(id) {
    const type = '{{ $type }}';

    document.getElementById('entries_tbody').innerHTML = '';
    document.getElementById('auto_voucher_alert').classList.add('d-none');

    fetch(`/vouchers/${type}/${id}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('edit_voucher_no').textContent = '#' + data.voucher_no;

            if (data.remarks) {
                const alertBox = document.getElementById('auto_voucher_alert');
                alertBox.textContent = 'Remarks: ' + data.remarks;
                alertBox.classList.remove('d-none');
            }

            const tbody = document.getElementById('entries_tbody');
            (data.entries || []).forEach(e => {
                tbody.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td style="font-size:.85rem">${e.account_name}${e.party_name ? ' — ' + e.party_name : ''}</td>
                        <td class="text-end" style="font-size:.85rem">${e.debit  > 0 ? Number(e.debit).toFixed(2)  : ''}</td>
                        <td class="text-end" style="font-size:.85rem">${e.credit > 0 ? Number(e.credit).toFixed(2) : ''}</td>
                        <td class="text-muted" style="font-size:.8rem">${e.narration ?? ''}</td>
                    </tr>`);
            });
        })
        .catch(() => alert('Failed to load voucher details. Please try again.'));
}

// ── Delete modal ─────────────────────────────────────────────────────────────
function setDeleteId(id) {
    const type = '{{ $type }}';
    document.getElementById('deleteForm').action = `/vouchers/${type}/${id}`;
}
</script>
@endsection