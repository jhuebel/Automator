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
        Schema::table('script_execution_results', function (Blueprint $table) {
            $table->foreignUlid('runner_id')->nullable()->after('script_id')
                ->constrained('runners')->nullOnDelete();
            $table->timestamp('cancel_requested_at')->nullable()->after('pid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('script_execution_results', function (Blueprint $table) {
            $table->dropForeign(['runner_id']);
            $table->dropColumn(['runner_id', 'cancel_requested_at']);
        });
    }
};
