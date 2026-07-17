<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_checks', function (Blueprint $table) {
            $table->id();
            $table->string('qc_no', 30)->unique();
            $table->unsignedBigInteger('job_order_receive_id');
            $table->unsignedBigInteger('product_id');

            $table->decimal('quantity_inspected', 15, 3);
            $table->decimal('quantity_passed', 15, 3);
            $table->decimal('quantity_rejected', 15, 3)->default(0);

            $table->string('rejection_reason', 255)->nullable();
            $table->date('qc_date');
            $table->text('remarks')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('job_order_receive_id')->references('id')->on('job_order_receives')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_checks');
    }
};