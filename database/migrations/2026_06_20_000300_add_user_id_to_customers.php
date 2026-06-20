<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id');
            $table->unique('user_id');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['user_id']);
            }

            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
