<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $categories = ProductCategory::withCount('products')->get();
        return view('products.categories', compact('categories'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:product_categories,name',
            'code' => 'required|string|max:50|unique:product_categories,code',
        ]);

        DB::beginTransaction();
        try {
            $category = ProductCategory::create($request->only('name', 'code'));

            DB::commit();
            Log::info('[Category] Created', ['id' => $category->id, 'by' => auth()->id()]);

            return redirect()->route('product_categories.index')
                ->with('success', 'Category "' . $category->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Category] Store error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('product_categories', 'name')->ignore($id)],
            'code' => ['required', 'string', 'max:50',  Rule::unique('product_categories', 'code')->ignore($id)],
        ]);

        DB::beginTransaction();
        try {
            $category = ProductCategory::findOrFail($id);
            $category->update($request->only('name', 'code'));

            DB::commit();
            Log::info('[Category] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('product_categories.index')
                ->with('success', 'Category updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Category] Update error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        try {
            $category = ProductCategory::findOrFail($id);

            // Guard: cannot delete if products are linked
            if ($category->products()->count() > 0) {
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $category->name . '" — it has ' . $category->products()->count() . ' product(s) linked to it.');
            }

            // Guard: cannot delete if subcategories are linked
            if ($category->subcategories()->count() > 0) {
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $category->name . '" — it has subcategories. Delete those first.');
            }

            $category->delete();

            Log::info('[Category] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('product_categories.index')
                ->with('success', 'Category deleted successfully.');

        } catch (\Exception $e) {
            Log::error('[Category] Destroy error', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete category. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: returns subcategories for a given category_id.
    // Route: helpers.category.subcategories  (GET /helpers/categories/{category}/subcategories)
    public function getSubcategories($categoryId)
    {
        try {
            $subcategories = ProductSubcategory::where('category_id', $categoryId)
                ->select('id', 'name', 'code')
                ->get();

            return response()->json($subcategories);

        } catch (\Exception $e) {
            Log::error('[Category] getSubcategories error', ['message' => $e->getMessage()]);
            return response()->json([], 500);
        }
    }
}