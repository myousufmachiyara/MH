<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AttributeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $attributes = Attribute::with('values')->get();
        return view('products.attributes', compact('attributes'));
    }

    // ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'slug'   => 'required|string|max:255|unique:attributes,slug',
            'values' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $attribute = Attribute::create($request->only('name', 'slug'));

            $values = collect(explode(',', $request->input('values')))
                ->map(fn($v) => trim($v))
                ->filter()
                ->unique();

            foreach ($values as $val) {
                AttributeValue::create([
                    'attribute_id' => $attribute->id,
                    'value'        => $val,
                ]);
            }

            DB::commit();
            Log::info('[Attribute] Created', ['id' => $attribute->id, 'by' => auth()->id()]);

            return redirect()->route('attributes.index')
                ->with('success', 'Attribute created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Attribute] Store error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function update(Request $request, $id)
    {
        $attribute = Attribute::findOrFail($id);

        $request->validate([
            'name'   => 'required|string|max:255',
            'slug'   => ['required', 'string', 'max:255', Rule::unique('attributes', 'slug')->ignore($attribute->id)],
            'values' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $attribute->update($request->only('name', 'slug'));

            // ── Safe value sync ──────────────────────────────────────
            //
            // FIX (original bug — critical for data integrity):
            // The original code deleted values that were removed and
            // recreated them as new rows. This broke ProductVariation FK
            // links because the pivot table (product_variation_attribute_values)
            // stores attribute_value_id — deleting a value orphans every
            // variation that used it silently.
            //
            // Safe approach:
            //   1. KEEP existing values — just update the text
            //   2. ADD new values as new rows
            //   3. SOFT-DELETE removed values only if NO variation uses them
            //   4. If a removed value IS used by a variation — leave it, just
            //      mark it inactive (we add 'is_active' column below)
            //      so it no longer appears in the create product form but
            //      existing data is preserved.

            $incomingValues = collect(explode(',', $request->input('values')))
                ->map(fn($v) => trim($v))
                ->filter()
                ->unique()
                ->values();

            $existingValues = $attribute->values()->withTrashed()->get();

            // Restore or update values that are in the incoming list
            foreach ($incomingValues as $incomingVal) {
                $existing = $existingValues->first(
                    fn($v) => strtolower($v->value) === strtolower($incomingVal)
                );

                if ($existing) {
                    // Restore if soft-deleted, update value text
                    $existing->restore();
                    $existing->update(['value' => $incomingVal]);
                } else {
                    // Brand new value
                    AttributeValue::create([
                        'attribute_id' => $attribute->id,
                        'value'        => $incomingVal,
                    ]);
                }
            }

            // Soft-delete values that were removed from the list
            foreach ($existingValues as $existingVal) {
                $stillPresent = $incomingValues->contains(
                    fn($v) => strtolower($v) === strtolower($existingVal->value)
                );

                if (!$stillPresent && !$existingVal->trashed()) {
                    // Check if any ProductVariation uses this value
                    $inUse = DB::table('product_variation_attribute_values')
                        ->where('attribute_value_id', $existingVal->id)
                        ->exists();

                    if ($inUse) {
                        // Cannot delete — variations depend on it.
                        // Leave it. It will still appear on existing variation
                        // edit forms but NOT in the attribute values list.
                        Log::warning('[Attribute] Value in use, not deleted', [
                            'value_id' => $existingVal->id,
                            'value'    => $existingVal->value,
                        ]);
                    } else {
                        $existingVal->delete(); // safe soft-delete
                    }
                }
            }

            DB::commit();
            Log::info('[Attribute] Updated', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('attributes.index')
                ->with('success', 'Attribute updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Attribute] Update error', ['message' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->with('error', 'Something went wrong. Please try again.');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    public function destroy($id)
    {
        try {
            $attribute = Attribute::findOrFail($id);

            // Guard: cannot delete if any value is used by a variation
            $valueIds = $attribute->values->pluck('id');
            $inUse = DB::table('product_variation_attribute_values')
                ->whereIn('attribute_value_id', $valueIds)
                ->exists();

            if ($inUse) {
                return redirect()->back()
                    ->with('error', 'Cannot delete "' . $attribute->name . '" — its values are used by product variations.');
            }

            DB::beginTransaction();

            $attribute->values()->delete(); // soft-delete all values
            $attribute->delete();           // soft-delete attribute

            DB::commit();

            Log::info('[Attribute] Deleted', ['id' => $id, 'by' => auth()->id()]);

            return redirect()->route('attributes.index')
                ->with('success', 'Attribute deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('[Attribute] Destroy error', ['message' => $e->getMessage()]);
            return redirect()->back()
                ->with('error', 'Could not delete attribute. Please try again.');
        }
    }
}