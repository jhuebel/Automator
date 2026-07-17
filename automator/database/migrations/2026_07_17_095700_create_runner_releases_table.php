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
        Schema::create('runner_releases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('version');
            $table->string('os');
            $table->string('arch');
            $table->string('checksum_sha256');
            $table->string('storage_path');
            $table->unsignedBigInteger('size_bytes');
            $table->boolean('is_released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['version', 'os', 'arch']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runner_releases');
    }
};
