<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_stock_ledger', function (Blueprint $table) {
            $table->id();

            // Human-readable doc number, only set for GatePass entries
            // (JobIssue/JobReceive entries reference their own parent's number)
            $table->string('doc_no', 30)->nullable()->unique();

            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('product_id');

            // fresh | issued | leftover
            $table->string('status', 20);

            // signed: +qty adds to pool, -qty removes from pool
            $table->decimal('quantity', 15, 3);

            // 'GatePass' | 'JobIssue' | 'JobReceive'
            $table->string('reference_type', 30);
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->date('entry_date');
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['vendor_id', 'product_id', 'status'], 'idx_vsl_lookup');
            $table->index(['reference_type', 'reference_id'], 'idx_vsl_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_stock_ledger');
    }
};