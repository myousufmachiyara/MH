<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->date('order_date');
            $table->date('expected_date')->nullable();

            // Pending | Converted | Cancelled — Converted means a Purchase Invoice was created from it
            $table->string('status', 30)->default('Pending');
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_po_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};