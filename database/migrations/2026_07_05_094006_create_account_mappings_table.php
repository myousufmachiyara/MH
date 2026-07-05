<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('role_key', 50)->unique();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')->on('chart_of_accounts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};