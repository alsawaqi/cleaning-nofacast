<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('minimum_billable_minutes')->default(0);
            $table->unsignedSmallInteger('default_workers')->default(1);
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->unsignedInteger('default_material_cost_halalas')->default(0);
            $table->string('material_policy')->default('company_supplied_included');
            $table->json('included_materials')->nullable();
            $table->unsignedInteger('extra_hour_rate_halalas')->default(0);
            $table->string('overtime_policy')->default('none');
        });

        Schema::table('service_packages', function (Blueprint $table): void {
            $table->unsignedTinyInteger('visits_per_week')->nullable();
            $table->decimal('hours_per_visit', 6, 2)->nullable();
            $table->unsignedInteger('expected_labor_minutes')->default(0);
            $table->unsignedInteger('material_cost_halalas')->default(0);
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('pricing_model')->default('package');
            $table->unsignedSmallInteger('agreed_workers')->default(1);
            $table->unsignedTinyInteger('visits_per_week')->nullable();
            $table->decimal('hours_per_visit', 6, 2)->nullable();
            $table->unsignedInteger('planned_weekly_minutes')->default(0);
            $table->boolean('included_materials')->default(true);
            $table->string('material_policy')->default('company_supplied_included');
            $table->unsignedInteger('estimated_material_cost_halalas')->default(0);
            $table->unsignedInteger('extra_hour_rate_halalas')->default(0);
            $table->string('overtime_policy')->default('none');
            $table->json('service_scope')->nullable();
            $table->text('terms_and_conditions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn([
                'pricing_model',
                'agreed_workers',
                'visits_per_week',
                'hours_per_visit',
                'planned_weekly_minutes',
                'included_materials',
                'material_policy',
                'estimated_material_cost_halalas',
                'extra_hour_rate_halalas',
                'overtime_policy',
                'service_scope',
                'terms_and_conditions',
            ]);
        });

        Schema::table('service_packages', function (Blueprint $table): void {
            $table->dropColumn([
                'visits_per_week',
                'hours_per_visit',
                'expected_labor_minutes',
                'material_cost_halalas',
            ]);
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn([
                'minimum_billable_minutes',
                'default_workers',
                'default_duration_minutes',
                'default_material_cost_halalas',
                'material_policy',
                'included_materials',
                'extra_hour_rate_halalas',
                'overtime_policy',
            ]);
        });
    }
};
