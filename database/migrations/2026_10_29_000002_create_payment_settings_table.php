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
        // Each clinic takes payments into its own BOG or Flitt merchant account
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider')->nullable(); // bog, flitt
            // Both are kept, so payments made before switching provider can still be checked and refunded
            $table->text('bog_settings')->nullable(); // encrypted
            $table->text('flitt_settings')->nullable(); // encrypted
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
