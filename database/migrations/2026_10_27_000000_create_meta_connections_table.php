<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facebook Pages (and their linked Instagram accounts) connected via Facebook Login for Business.
     * page_id / instagram_account_id are the routing keys for the global Meta webhook.
     */
    public function up(): void
    {
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('facebook_user_id')->nullable();
            $table->string('page_id')->unique();
            $table->string('page_name')->nullable();
            $table->text('page_access_token');
            $table->string('instagram_account_id')->nullable()->unique();
            $table->string('instagram_username')->nullable();
            $table->text('user_access_token')->nullable();
            $table->timestampTz('user_token_expires_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_connections');
    }
};
