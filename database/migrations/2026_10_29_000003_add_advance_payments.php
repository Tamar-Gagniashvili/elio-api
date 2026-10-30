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
        // Pay in advance: a payment link is texted to the client some hours before the session
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->boolean('advance_payment_enabled')->default(false);
            $table->unsignedSmallInteger('advance_payment_hours_before')->default(24);
            $table->decimal('advance_payment_amount', 12, 2)->nullable();
            $table->text('advance_payment_message')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Set once the payment link has been sent, so it goes out only once
            $table->timestampTz('advance_payment_requested_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('advance_payment_requested_at');
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn(['advance_payment_enabled', 'advance_payment_hours_before', 'advance_payment_amount', 'advance_payment_message']);
        });
    }
};
