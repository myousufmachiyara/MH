<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no', 30)->unique();
            $table->string('bill_no', 50)->nullable();
            $table->string('ref_no', 50)->nullable();

            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->date('purchase_date');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            $table->string('status', 30)->default('Posted');
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('purchase_orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_purchases_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};