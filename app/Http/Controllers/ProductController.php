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

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'subcategory', 'variations'])->get();
        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = ProductCategory::all();
        $attributes = Attribute::with('values')->get();
        $units      = MeasurementUnit::all();

        return view('products.create', compact('categories', 'attributes', 'units'));
    }

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

    public function show(Product $product)
    {
        return redirect()->route('products.index');
    }

    // AJAX: returns product info for purchase/sale invoice row
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
            'product', 'categories', 'subcategories', 'attributes', 'units'
        ));
    }

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

            if (is_array($request->variations)) {
                foreach ($request->variations as $variationData) {
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

            if ($request->filled('removed_variations')) {
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

    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // FIX: was querying dead tables purchase_invoice_items/sale_invoice_items.
            // Corrected to the actual current tables.
            $usedInPurchase = DB::table('purchase_items')
                ->where('product_id', $product->id)
                ->exists();

            $usedInSale = DB::table('sale_items')
                ->where('product_id', $product->id)
                ->exists();

            $usedInStockMovements = DB::table('stock_movements')
                ->where('product_id', $product->id)
                ->exists();

            if ($usedInPurchase || $usedInSale || $usedInStockMovements) {
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $product->name . '" — it has transaction history. Deactivate it instead.');
            }

            $product->delete();

            Log::info('[Product] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('products.index')
                ->with('success', 'Product deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Product] Destroy failed', ['id' => $id, 'error' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete product. Please try again.');
        }
    }

    // AJAX: variation dropdown for purchase/sale invoice item rows
    public function getVariations($productId)
    {
        try {
            $product = Product::with(['variations.attributeValues', 'measurementUnit'])
                ->findOrFail($productId);

            $unitId = $product->measurement_unit;

            $variations = $product->variations->map(function ($v) use ($unitId) {
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
}