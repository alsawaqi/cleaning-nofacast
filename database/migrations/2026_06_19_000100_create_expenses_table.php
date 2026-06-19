<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('expense_date');
            $table->string('expense_type')->default('operating_expense');
            $table->string('category');
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('amount_halalas')->default(0);
            $table->unsignedInteger('vat_halalas')->default(0);
            $table->string('payment_method')->default('bank_transfer');
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('approved');
            $table->string('receipt_path')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['expense_date', 'expense_type', 'status']);
            $table->index(['category', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
