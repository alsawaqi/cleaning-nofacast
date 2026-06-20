<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_sites', function (Blueprint $table): void {
            $table->boolean('is_default')->default(false)->after('formatted_address');
            $table->index(['customer_id', 'is_default']);
        });

        DB::table('customer_sites')
            ->whereIn('id', function ($query): void {
                $query
                    ->selectRaw('MIN(id)')
                    ->from('customer_sites')
                    ->groupBy('customer_id');
            })
            ->update(['is_default' => true]);
    }

    public function down(): void
    {
        Schema::table('customer_sites', function (Blueprint $table): void {
            $table->dropIndex(['customer_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
