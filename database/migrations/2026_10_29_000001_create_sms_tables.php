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
        // Each clinic sends with its own InexPhone account and sender name
        Schema::create('sms_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sms_provider');
            $table->text('inex_phone_settings')->nullable(); // encrypted
            $table->timestampsTz();
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            // The message id at InexPhone, used to match its callbacks
            $table->string('uuid')->nullable()->index();
            $table->string('phone_number');
            $table->text('message');
            $table->string('sms_message_status');
            $table->unsignedSmallInteger('parts_submitted')->nullable();
            $table->unsignedSmallInteger('parts_delivered')->nullable();
            $table->unsignedSmallInteger('total_parts')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestampsTz();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_settings');
    }
};
