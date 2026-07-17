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
            $table->timestamps();

            $table->foreign('job_order_receive_id')->references('id')->on('job_order_receives')->onDelete('cascade');
            $table->foreign('raw_product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_receive_items');
    }
};