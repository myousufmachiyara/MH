<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_order_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 15, 3);

            // Which pool it was drawn from — for traceability/reporting
            $table->string('source_status', 20)->nullable(); // 'fresh' | 'leftover' | 'mixed'

            $table->timestamps();

            $table->foreign('job_order_id')->references('id')->on('job_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_items');
    }
};