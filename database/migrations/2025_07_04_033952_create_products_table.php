<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // FKs — subcategory_id nullable since not all products have one
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('subcategory_id')->nullable();

            $table->string('name');
            $table->string('sku')->unique();
            $table->text('description')->nullable();

            // Inventory & pricing
            $table->decimal('opening_stock', 15, 2)->default(0);
            $table->decimal('selling_price', 15, 2)->default(0);

            // FIX: column named 'measurement_unit' but stores a FK ID.
            // Ambiguous name kept for backward compatibility — document clearly.
            // This references measurement_units.id
            $table->unsignedBigInteger('measurement_unit');

            $table->boolean('is_active')->default(true);
            $table->boolean('track_lots')->default(false);

            $table->softDeletes();
            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreign('measurement_unit')
                  ->references('id')->on('measurement_units')
                  ->onDelete('restrict');  // FIX: was cascade — deleting a unit
                                           // would cascade-delete all products.
                                           // restrict is correct here.

            $table->foreign('category_id')
                  ->references('id')->on('product_categories')
                  ->onDelete('restrict');  // FIX: was cascade — same reason

            // FIX: original had ->references('id')->on('product_categories')
            // for subcategory_id — wrong table! Must reference product_subcategories.
            $table->foreign('subcategory_id')
                  ->references('id')->on('product_subcategories')
                  ->onDelete('set null');  // nullable FK → set null on parent delete

            // ── Indexes ──────────────────────────────────────────────
            // category_id FK auto-creates an index in MySQL — no duplicate needed
            // subcategory_id same
            $table->index('is_active', 'idx_products_is_active');
            $table->index('sku',       'idx_products_sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};