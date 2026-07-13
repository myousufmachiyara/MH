@extends('layouts.app')

@section('title', 'Purchase Order | New')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('purchase_orders.store') }}" method="POST" onkeydown="return event.key != 'Enter';">
      @csrf
      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Purchase Order</h2>
        </header>

        <div class="card-body">
          <div class="row">
            <input type="hidden" id="itemCount" name="items" value="1">

            <div class="col-md-3 mb-3">
              <label>Order Date</label>
              <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="col-md-3 mb-3">
              <label>Vendor</label>
              <select name="vendor_id" class="form-control select2-js" required>
                <option value="">Select Vendor</option>
                @foreach ($vendors as $vendor)
                  <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-3 mb-3">
              <label>Expected Date</label>
              <input type="date" name="expected_date" class="form-control">
            </div>

            <div class="col-md-3 mb-3">
              <label>Remarks</label>
              <textarea name="remarks" class="form-control" rows="1"></textarea>
            </div>
          </div>

          <div class="table-responsive mb-3">
            <table class="table table-bordered" id="purchaseTable">
              <thead>
                <tr>
                  <th>S.No</th>
                  <th>Item</th>
                  <th>Quantity</th>
                  <th>Unit</th>
                  <th>Est. Price</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="Purchase1Table">
                <tr>
                  <td class="serial-no">1</td>
                  <td>
                    <select name="items[0][item_id]" id="item_name1" class="form-control select2-js product-select" onchange="onItemNameChange(this)">
                      <option value="">Select Item</option>
                      @foreach ($products as $product)
                        <option value="{{ $product->id }}" data-unit-id="{{ $product->measurement_unit }}">
                          {{ $product->name }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[0][quantity]" id="pur_qty1" class="form-control quantity" value="0" step="any" onchange="rowTotal(1)"></td>
                  <td>
                    <select name="items[0][unit]" id="unit1" class="form-control" required>
                      <option value="">-- Select --</option>
                      @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
                      @endforeach
                    </select>
                  </td>
                  <td><input type="number" name="items[0][price]" id="pur_price1" class="form-control" value="0" step="any" onchange="rowTotal(1)"></td>
                  <td><input type="number" id="amount1" class="form-control" value="0" step="any" disabled></td>
                  <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary" onclick="addNewRow_btn()"><i class="fas fa-plus"></i> Add Item</button>
          </div>

          <div class="row">
            <div class="col text-end">
              <h4>Est. Total: <strong class="text-danger">PKR <span id="netTotal">0.00</span></strong></h4>
            </div>
          </div>
        </div>

        <footer class="card-footer text-end">
          <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Order</button>
        </footer>
      </section>
    </form>
  </div>
</div>

<script>
  var products = @json($products);
  var index = 2;

  $(document).ready(function () {
    $('.select2-js').select2({ width: '100%', dropdownAutoWidth: true });
    updateSerialNumbers();
  });

  function updateSerialNumbers() {
    $('#Purchase1Table tr').each(function (index) {
      $(this).find('.serial-no').text(index + 1);
    });
  }

  function removeRow(button) {
    let rows = $('#Purchase1Table tr').length;
    if (rows > 1) {
      $(button).closest('tr').remove();
      $('#itemCount').val(--rows);
      tableTotal();
      updateSerialNumbers();
    }
  }

  function addNewRow_btn() {
    addNewRow();
  }

  function addNewRow() {
      let table = $('#Purchase1Table');
      let rowIndex = index - 1;

      let newRow = `
        <tr>
          <td class="serial-no"></td>
          <td>
            <select name="items[${rowIndex}][item_id]" id="item_name${index}" class="form-control select2-js product-select">
              <option value="">Select Item</option>
              ${products.map(product => `<option value="${product.id}">${product.name}</option>`).join('')}
            </select>
          </td>
          <td><input type="number" name="items[${rowIndex}][quantity]" id="pur_qty${index}" class="form-control quantity" value="0" step="any" onchange="rowTotal(${index})"></td>
          <td>
            <select name="items[${rowIndex}][unit]" id="unit${index}" class="form-control" required>
              <option value="">-- Select --</option>
              @foreach ($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->shortcode }})</option>
              @endforeach
            </select>
          </td>
          <td><input type="number" name="items[${rowIndex}][price]" id="pur_price${index}" class="form-control" value="0" step="any" onchange="rowTotal(${index})"></td>
          <td><input type="number" id="amount${index}" class="form-control" value="0" step="any" disabled></td>
          <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
        </tr>
      `;
      table.append(newRow);
      $('#itemCount').val(index);
      $(`#item_name${index}`).select2();
      $(`#unit${index}`).select2();
      index++;
      updateSerialNumbers();
  }

  function rowTotal(row) {
    let quantity = parseFloat($('#pur_qty' + row).val()) || 0;
    let price = parseFloat($('#pur_price' + row).val()) || 0;
    $('#amount' + row).val((quantity * price).toFixed(2));
    tableTotal();
  }

  function tableTotal() {
    let total = 0;
    $('#Purchase1Table tr').each(function () {
      total += parseFloat($(this).find('input[id^="amount"]').val()) || 0;
    });
    $('#netTotal').text(total.toFixed(2));
  }
</script>
@endsection