<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Weaving, Dyeing, Processing, Finishing, Packaging, Other
            $table->unsignedBigInteger('service_cost_account_id')->nullable();
            $table->timestamps();

            $table->foreign('service_cost_account_id')->references('id')->on('chart_of_accounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_types');
    }
};