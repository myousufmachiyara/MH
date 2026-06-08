@extends('layouts.app')

@section('title', 'Products | Create')

@section('content')
<div class="row">
  <div class="col">
    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data"
          onkeydown="return event.key != 'Enter';">
      @csrf

      @if($errors->any())
        <div class="alert alert-danger alert-dismissible">
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          <ul class="mb-0">
            @foreach($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <section class="card">
        <header class="card-header d-flex justify-content-between align-items-center">
          <h2 class="card-title">New Product</h2>
          <a href="{{ route('products.index') }}" class="btn btn-default">
            <i class="fas fa-arrow-left"></i> Back
          </a>
        </header>

        <div class="card-body">

          {{-- ── Basic info ──────────────────────────────────────── --}}
          <div class="row pb-3">

            <div class="col-md-2 mb-3">
              <label class="form-label">Product Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
              @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category_id" id="category_id" class="form-control" required>
                <option value="" disabled selected>Select Category</option>
                @foreach($categories as $cat)
                  <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                  </option>
                @endforeach
              </select>
              @error('category_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Sub Category</label>
              <select name="subcategory_id" id="subcategory_id" class="form-control">
                <option value="">Select Sub Category</option>
              </select>
              @error('subcategory_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">SKU <span class="text-danger">*</span></label>
              <input type="text" name="sku" id="sku" class="form-control"
                     value="{{ old('sku') }}" required>
              @error('sku')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Measurement Unit <span class="text-danger">*</span></label>
              <select name="measurement_unit" id="unit_id" class="form-control" required>
                <option value="" disabled selected>-- Select Unit --</option>
                @foreach($units as $unit)
                  <option value="{{ $unit->id }}" {{ old('measurement_unit') == $unit->id ? 'selected' : '' }}>
                    {{ $unit->name }} ({{ $unit->shortcode }})
                  </option>
                @endforeach
              </select>
              @error('measurement_unit')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Selling Price / Unit</label>
              <input type="number" step="any" name="selling_price" class="form-control"
                     value="{{ old('selling_price', '0.00') }}">
              @error('selling_price')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Opening Stock</label>
              <input type="number" step="any" name="opening_stock" class="form-control"
                     value="{{ old('opening_stock', '0') }}">
              @error('opening_stock')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-2 mb-3">
              <label class="form-label">Status</label>
              <select name="is_active" class="form-control">
                <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', 1) == 0 ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <div class="col-md-4 mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
              @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

          </div>

          {{-- ── Attribute selectors (for generating combinations) ── --}}
          <div class="row mt-2">
            <div class="col-md-12">
              <h2 class="card-title">Product Variations</h2>
              <p class="text-muted small mb-3">
                Select values for each attribute, then click "Generate Variations".
              </p>
              <div class="row">
                @foreach($attributes as $attribute)
                  <div class="col-md-4 mb-3">
                    <label class="form-label">{{ $attribute->name }}</label>
                    <select name="attributes[{{ $attribute->id }}][]"
                            multiple
                            class="form-control select2-js variation-select"
                            data-attribute="{{ $attribute->id }}">
                      @foreach($attribute->values as $value)
                        <option value="{{ $value->id }}">{{ $value->value }}</option>
                      @endforeach
                    </select>
                  </div>
                @endforeach
              </div>
            </div>
          </div>

          {{-- ── Generated variations table ──────────────────────── --}}
          <div class="col-md-12 mt-3">
            <button type="button" class="btn btn-success mb-3" id="generateVariationsBtn">
              <i class="fa fa-sync"></i> Generate Variations
            </button>

            <div class="table-responsive">
              <table class="table table-bordered" id="variationsTable">
                <thead>
                  <tr>
                    <th>Variation</th>
                    <th>SKU</th>
                    <th>Opening Stock</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  {{-- Populated by JS --}}
                </tbody>
              </table>
            </div>
          </div>

        </div>{{-- /card-body --}}

        <footer class="card-footer text-end">
          <a href="{{ route('products.index') }}" class="btn btn-danger">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> Create Product
          </button>
        </footer>
      </section>
    </form>
  </div>
</div>

@push('scripts')
<script>
$(document).ready(function () {

  // ── Init Select2 ────────────────────────────────────────────────
  $('.select2-js').select2({ width: '100%' });

  // ── Subcategory AJAX (FIX: updated route name) ──────────────────
  $('#category_id').on('change', function () {
    var categoryId       = $(this).val();
    var subCategorySelect = $('#subcategory_id');

    subCategorySelect.empty().append('<option value="">Loading…</option>');

    if (categoryId) {
      $.ajax({
        // FIX: was route('products.getSubcategories', ':id')
        // Now uses the new helpers route
        url: "{{ route('helpers.category.subcategories', ':id') }}".replace(':id', categoryId),
        type: 'GET',
        success: function (data) {
          subCategorySelect.empty().append('<option value="">Select Sub Category</option>');
          if (data.length > 0) {
            $.each(data, function (i, subcat) {
              subCategorySelect.append(
                '<option value="' + subcat.id + '">' + subcat.name + '</option>'
              );
            });
          }
        },
        error: function () {
          subCategorySelect.empty().append('<option value="">Failed to load</option>');
        }
      });
    } else {
      subCategorySelect.empty().append('<option value="">Select Sub Category</option>');
    }
  });

  // ── Auto-generate SKU ────────────────────────────────────────────
  function generateAutoSku() {
    var catName  = $('#category_id option:selected').text().trim();
    var prodName = $('input[name="name"]').val().trim();

    if (!catName || catName === 'Select Category') catName  = 'CAT';
    if (!prodName)                                 prodName = 'ITEM';

    var cleanCat  = catName.replace(/\s+/g, '-').toUpperCase();
    var cleanProd = prodName.replace(/\s+/g, '-').toUpperCase();

    $('#sku').val(cleanProd + '-' + cleanCat);
  }

  $('input[name="name"]').on('input', generateAutoSku);
  $('#category_id').on('change', generateAutoSku);

  // ── Generate variation combinations ─────────────────────────────
  $('#generateVariationsBtn').on('click', function () {
    var attributes  = {!! $attributes->toJson() !!};
    var selectedMap = {};

    attributes.forEach(function (attr) {
      var selected = $('select[name="attributes[' + attr.id + '][]"]').val();
      if (selected && selected.length > 0) {
        selectedMap[attr.name] = selected.map(function (valId) {
          var text = $('select[name="attributes[' + attr.id + '][]"] option[value="' + valId + '"]').text();
          return { id: valId, text: text };
        });
      }
    });

    var combos  = buildCombinations(Object.entries(selectedMap));
    var tbody   = $('#variationsTable tbody');
    var mainSku = $('#sku').val();

    tbody.empty();

    combos.forEach(function (combo, index) {
      var label  = combo.map(function (c) { return c.text; }).join(' - ');
      var inputs = combo.map(function (c, i) {
        return '<input type="hidden" name="variations[' + index + '][attributes][' + i + '][attribute_value_id]" value="' + c.id + '">';
      }).join('');

      tbody.append(
        '<tr>' +
          '<td>' + label + inputs + '</td>' +
          '<td><input type="text" name="variations[' + index + '][sku]" class="form-control" value="' + mainSku + '-' + label + '"></td>' +
          '<td><input type="number" name="variations[' + index + '][stock_quantity]" step="any" class="form-control" value="0" required></td>' +
          '<td><button type="button" class="btn btn-sm btn-danger remove-variation"><i class="fas fa-times"></i></button></td>' +
        '</tr>'
      );
    });
  });

  $(document).on('click', '.remove-variation', function () {
    $(this).closest('tr').remove();
  });

  // ── Build cartesian product of attribute selections ──────────────
  function buildCombinations(arr, index) {
    index = index || 0;
    if (index === arr.length) return [[]];
    var rest   = buildCombinations(arr, index + 1);
    var values = arr[index][1];
    return values.reduce(function (acc, v) {
      return acc.concat(rest.map(function (r) { return [v].concat(r); }));
    }, []);
  }

});
</script>
@endpush

@endsection