<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('source_type')->nullable(); // Polymorphic source (e.g. Sale, Purchase, etc.)
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->decimal('debit', 12, 2)->default(0.00);
            $table->decimal('credit', 12, 2)->default(0.00);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });

        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table): void {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropForeign(['account_id']);
            $table->dropColumn('account_id');
        });

        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
