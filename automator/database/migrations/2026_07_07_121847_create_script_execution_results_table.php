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
        Schema::create('script_execution_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('script_id')->nullable()->constrained('script_definitions')->nullOnDelete();
            $table->string('script_name');
            $table->string('language');
            $table->string('username')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('exit_code')->nullable();
            $table->json('output');
            $table->integer('pid')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('script_execution_results');
    }
};
