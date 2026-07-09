<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_entries', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('voucher_id');
            $table->unsignedBigInteger('account_id'); // → chart_of_accounts

            // Party tagging — separated parties model.
            // party_type distinguishes which table party_id points to,
            // since customers and vendors are separate tables (no single
            // parties table, so no FK constraint here — validated in code).
            $table->string('party_type', 20)->nullable(); // 'customer' | 'vendor'
            $table->unsignedBigInteger('party_id')->nullable();

            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);

            $table->string('narration')->nullable();

            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');

            $table->index('account_id', 'idx_ve_account_id');
            $table->index(['party_type', 'party_id'], 'idx_ve_party');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_entries');
    }
};