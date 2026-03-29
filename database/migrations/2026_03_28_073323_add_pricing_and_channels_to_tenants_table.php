<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'pricing_monthly')) {
                $table->decimal('pricing_monthly', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('tenants', 'enabled_channels')) {
                $table->json('enabled_channels')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['pricing_monthly', 'enabled_channels']);
        });
    }
};
