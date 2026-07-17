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
            $table->foreignUlid('preferred_runner_group_id')->nullable()->after('preferred_runner_id')
                ->constrained('runner_groups')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scheduled_jobs', function (Blueprint $table) {
            $table->dropForeign(['preferred_runner_group_id']);
            $table->dropColumn('preferred_runner_group_id');
        });
    }
};
