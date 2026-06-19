<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('service_id')->nullable()->after('customer_site_id')->constrained()->nullOnDelete();
            $table->foreignId('service_package_id')->nullable()->after('service_id')->constrained('service_packages')->nullOnDelete();
            $table->string('billing_cycle')->default('monthly')->after('payment_plan');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_package_id');
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('billing_cycle');
        });
    }
};
