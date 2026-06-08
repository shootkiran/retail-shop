<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'customers',
        'vendors',
        'product_categories',
        'product_items',
        'payment_methods',
        'sales',
        'sale_items',
        'purchases',
        'purchase_items',
        'held_orders',
    ];

    public function up(): void
    {
        $businessId = DB::table('businesses')->value('id');

        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            DB::table($tableName)->update(['business_id' => $businessId]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_id');
            });
        }
    }
};
