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
        // Prices differ by specialist and session, so each booking carries its own
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable();
        });

        // Pay in advance now charges the booking's price instead of one clinic-wide amount
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn('advance_payment_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table) {
            $table->decimal('advance_payment_amount', 12, 2)->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
