<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('execution_timeout_seconds')->default(300);
            $table->integer('max_concurrent_executions')->default(5);
            $table->integer('max_history_records')->default(1000);
            $table->text('anthropic_api_key')->nullable();
            $table->string('anthropic_model')->default('claude-sonnet-5');
            $table->string('anthropic_effort')->default('high');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
