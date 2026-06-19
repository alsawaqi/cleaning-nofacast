<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('billing_cycle')->default('monthly');
            $table->string('visit_frequency')->default('monthly');
            $table->unsignedSmallInteger('worker_count')->default(1);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->unsignedInteger('price_halalas')->default(0);
            $table->unsignedTinyInteger('vat_rate')->default(15);
            $table->boolean('prices_include_vat')->default(false);
            $table->json('checklist_template')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_id', 'is_active']);
        });

        Schema::create('service_pricing_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('pricing_type')->default('fixed');
            $table->string('unit_label')->default('service');
            $table->unsignedInteger('unit_price_halalas')->default(0);
            $table->unsignedInteger('minimum_quantity')->default(1);
            $table->unsignedInteger('maximum_quantity')->nullable();
            $table->unsignedTinyInteger('vat_rate')->default(15);
            $table->boolean('prices_include_vat')->default(false);
            $table->json('applies_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_id', 'pricing_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pricing_rules');
        Schema::dropIfExists('service_packages');
    }
};
