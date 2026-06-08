<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Services master table ────────────────────────────────────
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // Unit used for billing this service (e.g. Lbs, meters, pieces)
            // FK to measurement_units
            $table->unsignedBigInteger('unit_id')->nullable();

            // Which COA expense account is debited when this service cost is posted
            // e.g. Weaving Service Cost (id=24), Processing Cost (id=25) etc.
            $table->unsignedBigInteger('expense_account_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreign('unit_id')
                  ->references('id')->on('measurement_units')
                  ->onDelete('set null');

            $table->foreign('expense_account_id')
                  ->references('id')->on('chart_of_accounts')
                  ->onDelete('set null');

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->index('is_active', 'idx_services_active');
        });

        // ── Service ↔ Vendor pivot ────────────────────────────────────
        // One service can be done by multiple vendors.
        // One vendor can provide multiple services.
        // Rate is stored per vendor per service (agreed rate before dispatch).
        Schema::create('service_vendor', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('vendor_id');

            // Agreed rate for this vendor doing this service
            $table->decimal('rate', 15, 2)->default(0);

            // Currency — PKR by default
            $table->string('currency', 10)->default('PKR');

            $table->text('notes')->nullable();

            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreign('service_id')
                  ->references('id')->on('services')
                  ->onDelete('cascade');

            $table->foreign('vendor_id')
                  ->references('id')->on('vendors')
                  ->onDelete('cascade');

            // A vendor can only be linked to a service once
            $table->unique(['service_id', 'vendor_id'], 'uq_service_vendor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_vendor');
        Schema::dropIfExists('services');
    }
};