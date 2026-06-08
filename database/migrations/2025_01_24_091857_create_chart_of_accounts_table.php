<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {

            $table->id();

            $table->string('account_code', 20)->unique();

            // FK to sub_head_of_accounts
            // NOTE: do NOT add a separate ->index() on shoa_id below —
            // MySQL automatically creates an index for every foreign key column.
            // Declaring a second index on the same column throws:
            // "Duplicate key name 'idx_coa_shoa_id'" on some MySQL versions.
            $table->unsignedBigInteger('shoa_id');

            $table->string('name');
            $table->string('trn')->nullable();

            // Validated against COAController::ACCOUNT_TYPES on every write.
            // Valid values: customer | vendor | cash | bank | inventory |
            //   receivable | liability | payable | equity | revenue | cogs |
            //   expenses | service_cost | freight | sampling | packaging
            $table->string('account_type')->nullable();

            $table->decimal('receivables',     15, 2)->default(0);
            $table->decimal('payables',        15, 2)->default(0);
            $table->decimal('credit_limit',    15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);

            $table->date('opening_date');

            // Using text for remarks/address so long entries don't get truncated
            $table->string('remarks')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_no')->nullable();

            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');

            $table->timestamps();
            $table->softDeletes();

            // ── Foreign keys ─────────────────────────────────────────────
            $table->foreign('shoa_id')->references('id')->on('sub_head_of_accounts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');

            // ── Explicit indexes ─────────────────────────────────────────
            // account_type: filtered on every dropdown across all modules
            // (e.g. "all customer accounts", "all cash+bank accounts")
            $table->index('account_type', 'idx_coa_account_type');

            // shoa_id: the FK above already creates an implicit index in MySQL.
            // Do NOT add idx_coa_shoa_id here — duplicate index error.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};