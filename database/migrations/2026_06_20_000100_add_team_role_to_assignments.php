<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('team_role', 20)->default('main')->after('status');
            $table->index(['contract_id', 'weekday', 'starts_at', 'ends_at', 'team_role'], 'assignments_team_slot_role_index');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropIndex('assignments_team_slot_role_index');
            $table->dropColumn('team_role');
        });
    }
};
