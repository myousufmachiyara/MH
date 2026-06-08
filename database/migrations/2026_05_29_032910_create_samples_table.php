<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Samples ──────────────────────────────────────────────────
        Schema::create('samples', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');

            // Auto-generated: SMP-{project_no}-001
            $table->string('sample_no')->unique();

            // Status flow:
            // pending → approved
            //         → rejected → resample (new Sample record created)
            //                    → dropped  (project status → dropped)
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'resampled',  // this sample was rejected and a new one created
                'dropped',    // rejected and no resample — project dropped
            ])->default('pending');

            // Whether sampling cost is charged to the project invoice
            $table->boolean('include_in_project_costing')->default(false);

            // Courier / dispatch details
            $table->string('courier_name')->nullable();
            $table->string('tracking_no')->nullable();
            $table->date('dispatched_at')->nullable();
            $table->date('received_at')->nullable();   // when customer received

            // Rejection details
            $table->text('rejection_reason')->nullable();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreign('project_id')
                  ->references('id')->on('projects')
                  ->onDelete('cascade');

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->index('project_id', 'idx_samples_project');
            $table->index('status',     'idx_samples_status');
        });

        // ── Sample Costs ─────────────────────────────────────────────
        // Each sample can have multiple cost entries
        // (e.g. fabric cost, stitching cost, courier charge)
        Schema::create('sample_costs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sample_id');

            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);

            // If true → this cost is included in project costing / invoice
            $table->boolean('include_in_project_costing')->default(false);

            // Who bears the cost
            $table->enum('borne_by', ['company', 'customer'])->default('company');

            $table->timestamps();

            $table->foreign('sample_id')
                  ->references('id')->on('samples')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_costs');
        Schema::dropIfExists('samples');
    }
};