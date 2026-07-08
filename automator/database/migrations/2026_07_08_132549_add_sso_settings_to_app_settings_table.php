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
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('sso_auto_provision_enabled')->default(false);
            $table->string('sso_default_role')->default('Viewer');

            $table->boolean('entra_enabled')->default(false);
            $table->string('entra_client_id')->nullable();
            $table->text('entra_client_secret')->nullable();
            $table->string('entra_tenant_id')->nullable();
            $table->string('entra_allowed_domains')->nullable();

            $table->boolean('google_enabled')->default(false);
            $table->string('google_client_id')->nullable();
            $table->text('google_client_secret')->nullable();
            $table->string('google_allowed_domains')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'sso_auto_provision_enabled', 'sso_default_role',
                'entra_enabled', 'entra_client_id', 'entra_client_secret', 'entra_tenant_id', 'entra_allowed_domains',
                'google_enabled', 'google_client_id', 'google_client_secret', 'google_allowed_domains',
            ]);
        });
    }
};
