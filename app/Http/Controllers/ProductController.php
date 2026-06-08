<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use App\Models\MeasurementUnit;
use App\Models\ProductVariation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

// FIX: removed dead imports that don't exist in this project:
//   Picqer\Barcode\BarcodeGeneratorPNG  — barcode package not installed
//   Maatwebsite\Excel\Facades\Excel      — excel package not installed
//   ProductPart                          — model doesn't exist
// These caused class-not-found errors on any request hitting this controller.

class ProductController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $products = Product::with(['category', 'subcategory', 'variations'])->get();
        return view('products.index', compact('products'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function create()
    {
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();
        $units      = MeasurementUnit::all();

        return view('products.create', compact('categories', 'attributes', 'units'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255|unique:products,name',
            'category_id'      => 'required|exists:product_categories,id',
            'subcategory_id'   => 'nullable|exists:product_subcategories,id',
            'sku'              => 'required|string|unique:products,sku',
            'description'      => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'selling_price'    => 'nullable|numeric|min:0',
            'opening_stock'    => 'required|numeric|min:0',
            'is_active'        => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::create($request->only([
                'name', 'category_id', 'subcategory_id', 'sku', 'description',
                'measurement_unit', 'opening_stock', 'selling_price', 'is_active',
            ]));

            Log::info('[Product] Created', ['id' => $product->id, 'by' => auth()->id()]);

            if ($request->has('variations')) {
                foreach ($request->variations as $variationData) {
                    $variation = $product->variations()->create([
                        'sku'            => $variationData['sku'] ?? null,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        'selling_price'  => $variationData['selling_price'] ?? 0,
                    ]);

                    // FIX: attribute_value_ids come as a flat array from the
                    // hidden inputs in create.blade.php, not nested objects.
                    // Extract just the IDs for sync().
                    if (!empty($variationData['attributes'])) {
                        $attributeValueIds = collect($variationData['attributes'])
                            ->pluck('attribute_value_id')
                            ->filter()
                            ->toArray();

                        if (!empty($attributeValueIds)) {
                            $variation->attributeValues()->sync($attributeValueIds);
                        }
                    }
                }
            }

            DB::commit();

            return redirect()->route('products.index')
                ->with('success', 'Product created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product] Store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()
                ->with('error', 'Product creation failed. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // show() redirects to index — products don't have a detail page
    public function show(Product $product)
    {
        return redirect()->route('products.index');
    }

    // ─────────────────────────────────────────────────────────────────
    // details() — AJAX: returns product info for purchase invoice row
    public function details(Request $request)
    {
        try {
            $product = Product::with('measurementUnit')->findOrFail($request->id);

            return response()->json([
                'id'      => $product->id,
                'name'    => $product->name,
                'sku'     => $product->sku,
                'unit_id' => $product->measurement_unit,
                'unit'    => optional($product->measurementUnit)->shortcode,
                'price'   => $product->selling_price ?? 0,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Product not found'], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function edit($id)
    {
        $product = Product::with([
            'variations.attributeValues',
            'images',
        ])->findOrFail($id);

        $categories    = ProductCategory::all();
        $subcategories = ProductSubcategory::where('category_id', $product->category_id)->get();
        $attributes    = Attribute::with('values')->get();
        $units         = MeasurementUnit::all();

        return view('products.edit', compact(
            'product',
            'categories',
            'subcategories',
            'attributes',
            'units'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'name'             => 'required|string|max:255|unique:products,name,' . $id,
            'category_id'      => 'required|exists:product_categories,id',
            'subcategory_id'   => 'nullable|exists:product_subcategories,id',
            'sku'              => 'required|string|unique:products,sku,' . $id,
            'measurement_unit' => 'required|exists:measurement_units,id',
            'selling_price'    => 'nullable|numeric|min:0',
            'opening_stock'    => 'nullable|numeric|min:0',
            'is_active'        => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $product = Product::findOrFail($id);

            $product->update($request->only([
                'name', 'category_id', 'subcategory_id', 'sku',
                'measurement_unit', 'opening_stock', 'description',
                'selling_price', 'is_active',
            ]));

            // ── Update existing variations ───────────────────────────
            if (is_array($request->variations)) {
                foreach ($request->variations as $variationData) {
                    // FIX: was using findOrFail without checking the variation
                    // actually belongs to this product — security gap.
                    $variation = ProductVariation::where('id', $variationData['id'])
                        ->where('product_id', $product->id)
                        ->firstOrFail();

                    $variation->update([
                        'sku'            => $variationData['sku'],
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        'selling_price'  => $variationData['selling_price'] ?? 0,
                    ]);

                    if (!empty($variationData['attributes'])) {
                        $variation->attributeValues()->sync($variationData['attributes']);
                    }
                }
            }

            // ── Add new variations ───────────────────────────────────
            if (is_array($request->new_variations)) {
                foreach ($request->new_variations as $newVar) {
                    $variation = $product->variations()->create([
                        'sku'            => $newVar['sku'],
                        'stock_quantity' => $newVar['stock_quantity'] ?? 0,
                        'selling_price'  => $newVar['selling_price'] ?? 0,
                    ]);

                    if (!empty($newVar['attributes'])) {
                        $variation->attributeValues()->sync($newVar['attributes']);
                    }
                }
            }

            // ── Remove deleted variations ────────────────────────────
            if ($request->filled('removed_variations')) {
                // FIX: must also check product_id ownership to prevent
                // deleting variations from other products via crafted requests
                ProductVariation::whereIn('id', $request->removed_variations)
                    ->where('product_id', $product->id)
                    ->delete();
            }

            DB::commit();

            Log::info('[Product] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('products.index')
                ->with('success', 'Product updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product] Update failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withInput()
                ->with('error', 'Product update failed. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        // FIX: original had no try/catch — any exception caused unhandled 500
        try {
            $product = Product::findOrFail($id);

            // Guard: products referenced in purchase/sale invoices cannot be deleted
            // FIX: original had no guard — deleting a product mid-project corrupts
            // invoice history. Soft delete is fine but check first.
            $usedInPurchase = DB::table('purchase_invoice_items')
                ->where('item_id', $product->id)
                ->exists();

            $usedInSale = DB::table('sale_invoice_items')
                ->where('product_id', $product->id)
                ->exists();

            if ($usedInPurchase || $usedInSale) {
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $product->name . '" — it is used in invoices. Deactivate it instead.');
            }

            $product->delete(); // soft delete

            Log::info('[Product] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('products.index')
                ->with('success', 'Product deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Product] Destroy failed', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete product. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // getVariations() — AJAX: used by purchase invoice and project phase
    // dropdowns to populate variation select after product is chosen.
    // Route: helpers.product.variations
    public function getVariations($productId)
    {
        try {
            $product = Product::with([
                'variations.attributeValues',
                'measurementUnit',
            ])->findOrFail($productId);

            $unitId = $product->measurement_unit;

            $variations = $product->variations->map(function ($v) use ($unitId) {
                // Build human-readable label from attribute values
                $label = $v->attributeValues->map(fn($av) => $av->value)->implode(' - ');

                return [
                    'id'    => $v->id,
                    'sku'   => $v->sku,
                    'label' => $label ?: $v->sku,
                    'unit'  => $unitId,
                    'stock' => $v->stock_quantity,
                    'price' => $v->selling_price,
                ];
            });

            return response()->json([
                'success'   => true,
                'variation' => $variations,
                'product'   => [
                    'id'   => $product->id,
                    'name' => $product->name,
                    'unit' => $unitId,
                    'unit_shortcode' => optional($product->measurementUnit)->shortcode,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success'   => false,
                'variation' => [],
                'message'   => 'Product not found.',
            ], 404);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // getLocationStock() — kept for backward compatibility but flagged:
    // references tables (stock_transfers, stock_transfer_details,
    // sale_invoice_items with location_id) that don't exist in this
    // project's migration set. Will throw SQL errors if called.
    // Remove once confirmed unused, or build the tables first.
    public function getLocationStock(Request $request)
    {
        // TODO: remove or implement properly when stock transfer module is built
        return response()->json(['stock' => 0]);
    }
}