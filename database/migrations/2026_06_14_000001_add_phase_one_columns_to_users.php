<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('operations')->after('password');
            $table->string('phone')->nullable()->after('email');
            $table->string('locale', 8)->default('ar')->after('role');
            $table->boolean('is_active')->default(true)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'phone', 'locale', 'is_active']);
        });
    }
};
