<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_sites', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('formatted_address', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_sites', function (Blueprint $table): void {
            $table->dropColumn([
                'latitude',
                'longitude',
                'google_place_id',
                'formatted_address',
            ]);
        });
    }
};
