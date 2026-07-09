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
            $table->unsignedBigInteger('product_id'); // finished/processed product received
            $table->decimal('quantity_received', 15, 3);
            $table->decimal('quantity_wastage', 15, 3)->default(0);
            $table->timestamps();

            $table->foreign('job_order_receive_id')->references('id')->on('job_order_receives')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_receive_items');
    }
};