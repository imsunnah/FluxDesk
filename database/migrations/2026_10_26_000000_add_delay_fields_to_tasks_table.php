<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'delay_category')) {
                $table->string('delay_category')->nullable()->after('completion_remarks');
            }
            if (!Schema::hasColumn('tasks', 'delay_reason')) {
                $table->text('delay_reason')->nullable()->after('delay_category');
            }
            if (!Schema::hasColumn('tasks', 'delay_status')) {
                $table->string('delay_status')->default('none')->after('delay_reason'); // 'none', 'awaiting_lead_review', 'finalized_by_lead'
            }
            if (!Schema::hasColumn('tasks', 'delay_locked_by')) {
                $table->foreignId('delay_locked_by')->nullable()->after('delay_status')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('tasks', 'delay_locked_at')) {
                $table->timestamp('delay_locked_at')->nullable()->after('delay_locked_by');
            }
            if (!Schema::hasColumn('tasks', 'delay_revisions')) {
                $table->json('delay_revisions')->nullable()->after('delay_locked_at');
            }
            if (!Schema::hasColumn('tasks', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['delay_locked_by']);
            $table->dropColumn([
                'delay_category',
                'delay_reason',
                'delay_status',
                'delay_locked_by',
                'delay_locked_at',
                'delay_revisions',
                'completed_at',
            ]);
        });
    }
};
