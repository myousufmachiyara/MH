<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Projects ─────────────────────────────────────────────────
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            $table->string('project_no')->unique(); // MH-2026-0001
            $table->unsignedBigInteger('customer_id');
            $table->string('title');                // brief description / order description
            $table->string('customer_po_no')->nullable(); // PO number received FROM customer

            // Status flow:
            // sampling → po_received → in_production → completed → dropped
            $table->enum('status', [
                'sampling',
                'po_received',
                'in_production',
                'completed',
                'dropped',
            ])->default('sampling');

            $table->date('order_date')->nullable();
            $table->date('delivery_date')->nullable(); // expected delivery to customer
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────────
            $table->foreign('customer_id')
                  ->references('id')->on('customers')
                  ->onDelete('restrict');

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->foreign('updated_by')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            // ── Indexes ───────────────────────────────────────────────
            $table->index('status',      'idx_projects_status');
            $table->index('customer_id', 'idx_projects_customer');
        });

        // ── Project Comments ──────────────────────────────────────────
        // Used for follow-up notes on a project (not per-phase).
        Schema::create('project_comments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->text('comment');
            $table->string('attachment_path')->nullable(); // optional file upload

            $table->timestamps();

            $table->foreign('project_id')
                  ->references('id')->on('projects')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_comments');
        Schema::dropIfExists('projects');
    }
};