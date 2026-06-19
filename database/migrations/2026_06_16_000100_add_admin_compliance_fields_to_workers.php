<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table): void {
            $table->string('job_role')->default('cleaner')->after('role_language');
            $table->date('hired_on')->nullable()->after('phone');
            $table->string('nationality')->nullable()->after('hired_on');
            $table->text('availability_notes')->nullable()->after('certifications');
        });
    }

    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table): void {
            $table->dropColumn([
                'job_role',
                'hired_on',
                'nationality',
                'availability_notes',
            ]);
        });
    }
};
