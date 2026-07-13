<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_order_receive_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_order_receive_id');

            // The raw material consumed from the vendor's Issued pool
            $table->unsignedBigInteger('raw_product_id');
            $table->decimal('quantity_consumed', 15, 3);
            $table->decimal('quantity_leftover', 15, 3)->default(0); // computed: issued - consumed

            // The finished/output product returned to our own warehouse
            // (nullable — a receive line can be pure raw-consumption tracking
            // with no output, e.g. sampling loss)
            $table->unsignedBigInteger('output_product_id')->nullable();
            $table->decimal('quantity_output', 15, 3)->default(0);

            $table->timestamps();

            $table->foreign('job_order_receive_id')->references('id')->on('job_order_receives')->onDelete('cascade');
            $table->foreign('raw_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('output_product_id')->references('id')->on('products')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_receive_items');
    }
};