<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->string('completion_review_status')->default('pending_review')->after('supervisor_note');
            $table->timestamp('completion_reviewed_at')->nullable()->after('completion_review_status');
            $table->foreignId('completion_reviewed_by')->nullable()->after('completion_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('completion_review_note')->nullable()->after('completion_reviewed_by');
            $table->index('completion_review_status');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropIndex(['completion_review_status']);
            $table->dropForeign(['completion_reviewed_by']);
            $table->dropColumn([
                'completion_review_status',
                'completion_reviewed_at',
                'completion_reviewed_by',
                'completion_review_note',
            ]);
        });
    }
};
