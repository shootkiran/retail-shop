<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique('customers_email_unique');
            $table->unique(['business_id', 'email']);
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropUnique('vendors_email_unique');
            $table->unique(['business_id', 'email']);
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropUnique('product_categories_name_unique');
            $table->dropUnique('product_categories_slug_unique');
            $table->unique(['business_id', 'name']);
            $table->unique(['business_id', 'slug']);
        });

        Schema::table('product_items', function (Blueprint $table): void {
            $table->dropUnique('product_items_sku_unique');
            $table->dropUnique('product_items_barcode_unique');
            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
        });

        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropUnique('payment_methods_name_unique');
            $table->unique(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'name']);
            $table->unique('name');
        });

        Schema::table('product_items', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'sku']);
            $table->dropUnique(['business_id', 'barcode']);
            $table->unique('sku');
            $table->unique('barcode');
        });

        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'name']);
            $table->dropUnique(['business_id', 'slug']);
            $table->unique('name');
            $table->unique('slug');
        });

        Schema::table('vendors', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'email']);
            $table->unique('email');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'email']);
            $table->unique('email');
        });
    }
};
