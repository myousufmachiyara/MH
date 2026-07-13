<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();
            $table->string('type', 20); // payment | receipt | journal | contra | system
            $table->date('voucher_date');
            $table->string('narration')->nullable();
            $table->json('attachments')->nullable();

            // Links back to the source document (Purchase/Sale/JobOrderReceive/Expense),
            // null for manually-created vouchers (Payment/Receipt/Journal/Contra)
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('type', 'idx_vouchers_type');
            $table->index(['reference_type', 'reference_id'], 'idx_vouchers_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};