<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('pos_terminal_id')->nullable()->after('payment_method_id')->constrained('pos_terminals')->nullOnDelete();
        });

        Schema::table('held_orders', function (Blueprint $table): void {
            $table->foreignId('pos_terminal_id')->nullable()->after('payment_method_id')->constrained('pos_terminals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('held_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_terminal_id');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pos_terminal_id');
        });
    }
};
