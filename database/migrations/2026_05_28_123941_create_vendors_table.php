<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();

            // FK to chart_of_accounts — auto-created by VendorObserver on created
            $table->unsignedBigInteger('coa_id')->nullable();

            // ── Core fields ────────────────────────────────────────
            $table->string('name');                              // required
            $table->string('vendor_type')->default('other');     // spinning_mill | weaving_mill | processing_mill | packager | courier | other

            // ── Contact ────────────────────────────────────────────
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();

            // ── Financial ──────────────────────────────────────────
            $table->string('ntn')->nullable();                   // National Tax Number
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['debit', 'credit'])->default('credit');
            $table->date('opening_balance_date')->nullable();

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
            $table->index('vendor_type', 'idx_vendors_type');
            $table->index('is_active',   'idx_vendors_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};