<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Project Phases ────────────────────────────────────────────
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');

            // Locks service + vendor + agreed rate together.
            // FK to service_vendor pivot table.
            $table->unsignedBigInteger('service_vendor_id');

            // Order within the project (1 = first phase, 2 = second, etc.)
            $table->unsignedInteger('phase_order')->default(1);

            // Rate per unit — pulled from service_vendor.rate, editable per phase
            $table->decimal('rate', 15, 2)->default(0);

            // ── Dispatch ─────────────────────────────────────────────
            $table->decimal('quantity_dispatched', 15, 3)->default(0);
            $table->date('dispatched_at')->nullable();

            // ── Receipt ──────────────────────────────────────────────
            $table->decimal('quantity_received', 15, 3)->default(0);
            $table->decimal('quantity_rejected', 15, 3)->default(0);
            $table->date('received_at')->nullable();

            // ── Status ───────────────────────────────────────────────
            // pending → dispatched → partially_received → fully_received
            //        → approved / rejected
            $table->enum('status', [
                'pending',
                'dispatched',
                'partially_received',
                'fully_received',
                'approved',
                'rejected',
            ])->default('pending');

            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            // total_cost = quantity_received * rate (computed, stored for reporting)
            $table->decimal('total_cost', 15, 2)->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreign('project_id')
                  ->references('id')->on('projects')
                  ->onDelete('cascade');

            $table->foreign('service_vendor_id')
                  ->references('id')->on('service_vendor')
                  ->onDelete('restrict');

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->index('project_id',       'idx_phases_project');
            $table->index('service_vendor_id', 'idx_phases_sv');
            $table->index('status',            'idx_phases_status');
        });

        // ── Phase Materials ───────────────────────────────────────────
        // Products consumed during a phase (packaging, chemicals, etc.)
        Schema::create('phase_materials', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('phase_id');
            $table->unsignedBigInteger('product_id');

            $table->decimal('quantity', 15, 3)->default(0);
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0); // qty * rate

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('phase_id')
                  ->references('id')->on('project_phases')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('restrict');

            $table->index('phase_id',   'idx_phase_materials_phase');
            $table->index('product_id', 'idx_phase_materials_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_materials');
        Schema::dropIfExists('project_phases');
    }
};