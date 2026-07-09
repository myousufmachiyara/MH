<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');

            // Purchase|Sale|JobIssue|JobReceive|TransferIn|TransferOut|Adjustment|Consume
            $table->string('movement_type', 30);

            $table->decimal('quantity', 15, 3); // signed: +in, -out
            $table->decimal('amount', 15, 2)->default(0); // value of the movement (for stock valuation)

            // polymorphic-style reference back to the source document
            $table->string('reference_type', 30); // 'Purchase'|'Sale'|'Job'|'JobReceive'
            $table->unsignedBigInteger('reference_id');

            $table->string('location', 50)->nullable(); // e.g. warehouse, or 'at vendor: X'
            $table->date('movement_date');

            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->index('movement_type', 'idx_sm_movement_type');
            $table->index(['reference_type', 'reference_id'], 'idx_sm_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};