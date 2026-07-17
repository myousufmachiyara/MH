<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('job_order_receive_outputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_order_receive_id');
            $table->unsignedBigInteger('output_product_id');
            $table->decimal('quantity_output', 15, 3);
            $table->decimal('conversion_rate', 15, 2)->default(0);
            $table->decimal('processing_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('job_order_receive_id')->references('id')->on('job_order_receives')->onDelete('cascade');
            $table->foreign('output_product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_order_receive_outputs');
    }
};
