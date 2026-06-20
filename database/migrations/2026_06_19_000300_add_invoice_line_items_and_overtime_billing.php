<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->foreignId('overtime_invoice_id')->nullable()->after('overtime_status')->constrained('invoices')->nullOnDelete();
            $table->timestamp('overtime_billed_at')->nullable()->after('overtime_invoice_id');
        });

        Schema::create('invoice_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('line_type')->index();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('unit_label')->default('item');
            $table->unsignedInteger('unit_price_halalas')->default(0);
            $table->unsignedTinyInteger('vat_rate')->default(15);
            $table->unsignedInteger('net_total_halalas')->default(0);
            $table->unsignedInteger('vat_total_halalas')->default(0);
            $table->unsignedInteger('gross_total_halalas')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['visit_id', 'line_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');

        Schema::table('visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('overtime_invoice_id');
            $table->dropColumn('overtime_billed_at');
        });
    }
};
