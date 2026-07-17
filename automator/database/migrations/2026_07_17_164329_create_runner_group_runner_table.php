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
        Schema::create('runner_group_runner', function (Blueprint $table) {
            $table->foreignUlid('runner_group_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('runner_id')->constrained()->cascadeOnDelete();
            $table->primary(['runner_group_id', 'runner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runner_group_runner');
    }
};
