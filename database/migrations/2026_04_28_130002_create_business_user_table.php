<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('staff');
            $table->string('office_type')->default('front_office');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        $businessId = DB::table('businesses')->value('id');

        DB::table('users')
            ->select(['id', 'office_type'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use ($businessId): void {
                DB::table('business_user')->insert([
                    'business_id' => $businessId,
                    'user_id' => $user->id,
                    'role' => 'admin',
                    'office_type' => $user->office_type ?: 'back_office',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
    }
};
