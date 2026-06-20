<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->json('sla_kpi_template')->nullable()->after('checklist_template');
        });

        Schema::table('service_packages', function (Blueprint $table): void {
            $table->json('sla_kpi_template')->nullable()->after('checklist_template');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->json('sla_kpi_template')->nullable()->after('terms_and_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('sla_kpi_template');
        });

        Schema::table('service_packages', function (Blueprint $table): void {
            $table->dropColumn('sla_kpi_template');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('sla_kpi_template');
        });
    }
};
