<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_business_id')->nullable()->after('id')->constrained('businesses')->nullOnDelete();
            $table->string('office_type')->default('back_office')->after('email_verified_at');
            $table->boolean('is_platform_admin')->default(false)->after('office_type');
            $table->boolean('is_active')->default(true)->after('is_platform_admin');
        });

        $businessId = DB::table('businesses')->value('id');

        DB::table('users')->update([
            'current_business_id' => $businessId,
            'office_type' => 'back_office',
            'is_active' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_business_id');
            $table->dropColumn(['office_type', 'is_platform_admin', 'is_active']);
        });
    }
};
