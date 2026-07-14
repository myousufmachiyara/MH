<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 30)->unique();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('sale_id')->nullable(); // optional link to a Sale Order
            $table->unsignedBigInteger('job_type_id')->nullable();
            $table->string('job_type', 50)->nullable(); // Weaving, Dyeing, Processing...
            $table->string('status', 30)->default('Issued'); // Issued|Received|PartiallyReceived
            $table->date('issue_date');
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('status', 'idx_job_orders_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};