<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('delivery_option')->nullable()->after('payment_type');
            $table->decimal('delivery_charge', 12, 2)->nullable()->default(0)->after('delivery_option');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['delivery_option', 'delivery_charge']);
        });
    }
};
