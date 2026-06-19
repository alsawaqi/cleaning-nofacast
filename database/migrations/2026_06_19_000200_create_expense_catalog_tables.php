<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('expense_type')->default('operating_expense');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('expense_category_id')
                ->nullable()
                ->after('id')
                ->constrained('expense_categories')
                ->nullOnDelete();
        });

        Schema::create('cost_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('item_type')->default('material');
            $table->string('unit')->default('unit');
            $table->unsignedInteger('purchase_cost_halalas')->default(0);
            $table->unsignedSmallInteger('estimated_life_months')->default(1);
            $table->unsignedInteger('estimated_monthly_uses')->default(1);
            $table->unsignedInteger('default_cost_per_use_halalas')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_type', 'is_active']);
        });

        Schema::create('service_cost_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedInteger('cost_per_use_halalas')->default(0);
            $table->unsignedInteger('line_total_halalas')->default(0);
            $table->boolean('charge_customer')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'cost_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_cost_items');
        Schema::dropIfExists('cost_items');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('expense_category_id');
        });

        Schema::dropIfExists('expense_categories');
    }
};
