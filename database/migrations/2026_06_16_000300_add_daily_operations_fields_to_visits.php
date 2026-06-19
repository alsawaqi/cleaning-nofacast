<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->timestamp('supervisor_acknowledged_at')->nullable()->after('checked_out_at');
            $table->foreignId('supervisor_acknowledged_by')->nullable()->after('supervisor_acknowledged_at')->constrained('users')->nullOnDelete();
            $table->text('supervisor_note')->nullable()->after('supervisor_acknowledged_by');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropForeign(['supervisor_acknowledged_by']);
            $table->dropColumn([
                'supervisor_acknowledged_at',
                'supervisor_acknowledged_by',
                'supervisor_note',
            ]);
        });
    }
};
