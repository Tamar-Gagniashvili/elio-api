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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // bog, flitt
            // The order id at the gateway: BOG's own order id, or ours for Flitt
            $table->string('external_id')->nullable()->index();
            // What the payment is for, e.g. a booking
            $table->nullableUuidMorphs('payable');
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('status')->default('created');
            $table->string('description')->nullable();
            $table->string('ccy', 3)->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
