<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_items', function (Blueprint $table): void {
            $table->foreignId('base_unit_id')->nullable()->after('vendor_id')->constrained('units')->nullOnDelete();
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('product_item_id')->constrained('units')->nullOnDelete();
            $table->decimal('quantity_base', 12, 4)->default(0)->after('quantity');
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->foreignId('unit_id')->nullable()->after('product_item_id')->constrained('units')->nullOnDelete();
            $table->decimal('quantity_base', 12, 4)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('quantity_base');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('quantity_base');
        });

        Schema::table('product_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('base_unit_id');
        });
    }
};
