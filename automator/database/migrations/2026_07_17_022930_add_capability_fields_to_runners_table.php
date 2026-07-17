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
        Schema::table('runners', function (Blueprint $table) {
            $table->string('version')->nullable()->after('os');
            $table->string('arch')->nullable()->after('version');
            $table->unsignedBigInteger('disk_free_bytes')->nullable()->after('arch');
            $table->unsignedBigInteger('disk_total_bytes')->nullable()->after('disk_free_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('runners', function (Blueprint $table) {
            $table->dropColumn(['version', 'arch', 'disk_free_bytes', 'disk_total_bytes']);
        });
    }
};
