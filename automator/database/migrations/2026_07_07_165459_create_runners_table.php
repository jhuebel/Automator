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
        Schema::create('runners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique();
            $table->string('hostname')->nullable();
            $table->string('os')->nullable();
            $table->json('tags');
            $table->string('status')->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('current_job_count')->default(0);
            $table->unsignedInteger('max_concurrent_jobs')->default(1);
            $table->foreignId('personal_access_token_id')->nullable()
                ->constrained('personal_access_tokens')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runners');
    }
};
