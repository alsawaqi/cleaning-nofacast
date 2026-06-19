<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->unsignedSmallInteger('planned_minutes')->default(0)->after('photos');
            $table->unsignedSmallInteger('actual_minutes')->default(0)->after('planned_minutes');
            $table->integer('variance_minutes')->default(0)->after('actual_minutes');
            $table->unsignedSmallInteger('overtime_minutes')->default(0)->after('variance_minutes');
            $table->unsignedSmallInteger('billable_overtime_minutes')->default(0)->after('overtime_minutes');
            $table->string('overtime_status')->default('not_required')->after('billable_overtime_minutes');
            $table->json('materials_used')->nullable()->after('overtime_status');
            $table->unsignedInteger('planned_revenue_halalas')->default(0)->after('materials_used');
            $table->unsignedInteger('labor_cost_halalas')->default(0)->after('planned_revenue_halalas');
            $table->unsignedInteger('material_cost_halalas')->default(0)->after('labor_cost_halalas');
            $table->unsignedInteger('billable_overtime_halalas')->default(0)->after('material_cost_halalas');
            $table->integer('gross_profit_halalas')->default(0)->after('billable_overtime_halalas');
            $table->text('execution_notes')->nullable()->after('gross_profit_halalas');
            $table->index('overtime_status');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropIndex(['overtime_status']);
            $table->dropColumn([
                'planned_minutes',
                'actual_minutes',
                'variance_minutes',
                'overtime_minutes',
                'billable_overtime_minutes',
                'overtime_status',
                'materials_used',
                'planned_revenue_halalas',
                'labor_cost_halalas',
                'material_cost_halalas',
                'billable_overtime_halalas',
                'gross_profit_halalas',
                'execution_notes',
            ]);
        });
    }
};
