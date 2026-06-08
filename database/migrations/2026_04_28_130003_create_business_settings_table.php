<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('country')->default('Nepal');
            $table->string('timezone')->default('Asia/Kathmandu');
            $table->string('currency_code', 3)->default('NPR');
            $table->string('currency_symbol', 10)->default('रू');
            $table->unsignedTinyInteger('currency_decimal_places')->default(2);
            $table->string('date_format')->default('d M Y');
            $table->string('time_format')->default('H:i');
            $table->string('invoice_prefix')->default('SL');
            $table->json('invoice_footer')->nullable();
            $table->timestamps();
        });

        DB::table('businesses')->orderBy('id')->get()->each(function (object $business): void {
            DB::table('business_settings')->insert([
                'business_id' => $business->id,
                'country' => $business->country,
                'timezone' => $business->timezone,
                'currency_code' => $business->currency_code,
                'currency_symbol' => $business->currency_symbol,
                'currency_decimal_places' => 2,
                'date_format' => 'd M Y',
                'time_format' => 'H:i',
                'invoice_prefix' => 'SL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
