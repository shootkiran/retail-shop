<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->foreignId('counted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('counted_at');
            $table->dateTime('posted_at')->nullable();
            $table->string('posting_mode')->default('inventory_only');
            $table->string('status')->default('posted');
            $table->decimal('variance_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('variance_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_adjustment_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_name_snapshot');
            $table->string('unit_symbol_snapshot')->nullable();
            $table->decimal('unit_multiplier_snapshot', 12, 4)->default(1);
            $table->decimal('system_quantity_base', 12, 4)->default(0);
            $table->decimal('system_quantity_display', 12, 4)->default(0);
            $table->decimal('counted_quantity', 12, 4)->nullable();
            $table->decimal('counted_quantity_base', 12, 4)->nullable();
            $table->decimal('variance_base', 12, 4)->default(0);
            $table->decimal('variance_value', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_adjustment_batch_id', 'product_item_id'], 'stock_adjustment_lines_batch_product_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustment_batches');
    }
};
