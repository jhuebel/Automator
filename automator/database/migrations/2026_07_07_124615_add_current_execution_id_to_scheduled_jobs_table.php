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
        Schema::table('scheduled_jobs', function (Blueprint $table) {
            $table->foreignUlid('current_execution_id')->nullable()
                ->after('last_exit_code')
                ->constrained('script_execution_results')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_jobs', function (Blueprint $table) {
            $table->dropForeign(['current_execution_id']);
            $table->dropColumn('current_execution_id');
        });
    }
};
