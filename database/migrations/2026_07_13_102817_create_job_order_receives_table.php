<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_order_receives', function (Blueprint $table) {
            $table->id();
            $table->string('receive_no', 30)->unique();
            $table->unsignedBigInteger('job_order_id');
            $table->date('receive_date');
            $table->decimal('processing_charge', 15, 2)->default(0); // vendor's labour fee
            $table->text('remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('job_order_id')->references('id')->on('job_orders')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_receives');
    }
};