<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // FK to chart_of_accounts — auto-created by CustomerObserver on created
            $table->unsignedBigInteger('coa_id')->nullable();

            // ── Core fields ────────────────────────────────────────
            $table->string('name');                              // required

            // ── Contact ────────────────────────────────────────────
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            // ── Financial ──────────────────────────────────────────
            $table->string('ntn')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('debit');
            $table->date('opening_balance_date')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);

            // ── Meta ───────────────────────────────────────────────
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ── Foreign keys ───────────────────────────────────────
            $table->foreign('coa_id')
                  ->references('id')->on('chart_of_accounts')
                  ->onDelete('set null');

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            // ── Indexes ────────────────────────────────────────────
            $table->index('is_active', 'idx_customers_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};