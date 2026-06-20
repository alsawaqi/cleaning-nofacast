<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->string('quality_status')->nullable()->after('completion_review_note');
            $table->unsignedTinyInteger('quality_score')->nullable()->after('quality_status');
            $table->timestamp('quality_reviewed_at')->nullable()->after('quality_score');
            $table->foreignId('quality_reviewed_by')->nullable()->after('quality_reviewed_at')->constrained('users')->nullOnDelete();
            $table->text('quality_notes')->nullable()->after('quality_reviewed_by');
            $table->boolean('quality_follow_up_required')->default(false)->after('quality_notes');
            $table->index('quality_status');
            $table->index('quality_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropIndex(['quality_status']);
            $table->dropIndex(['quality_reviewed_at']);
            $table->dropForeign(['quality_reviewed_by']);
            $table->dropColumn([
                'quality_status',
                'quality_score',
                'quality_reviewed_at',
                'quality_reviewed_by',
                'quality_notes',
                'quality_follow_up_required',
            ]);
        });
    }
};
