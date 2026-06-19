<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_sites', function (Blueprint $table): void {
            $table->string('country_code', 2)->default('SA')->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_sites', function (Blueprint $table): void {
            $table->dropColumn('country_code');
        });
    }
};
