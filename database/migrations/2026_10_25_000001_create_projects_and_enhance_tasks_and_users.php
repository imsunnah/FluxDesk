<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Projects Table
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        // 2. Add is_team_lead & project_id to users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_team_lead')) {
                $table->boolean('is_team_lead')->default(false)->after('is_customer');
            }
            if (!Schema::hasColumn('users', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('is_team_lead')->constrained('projects')->nullOnDelete();
            }
        });

        // 3. Enhance tasks table
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('title')->constrained('projects')->nullOnDelete();
            }
            if (!Schema::hasColumn('tasks', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('project_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('tasks', 'approximate_hours')) {
                $table->float('approximate_hours')->default(0)->after('priority');
            }
            if (!Schema::hasColumn('tasks', 'completed_hours')) {
                $table->float('completed_hours')->nullable()->after('approximate_hours');
            }
            if (!Schema::hasColumn('tasks', 'completion_remarks')) {
                $table->text('completion_remarks')->nullable()->after('completed_hours');
            }
        });

        // 4. Multiple assignees pivot table for tasks
        if (!Schema::hasTable('task_user')) {
            Schema::create('task_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        // 5. Add project_id to conversations (tickets)
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('ticket_description')->constrained('projects')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['project_id', 'client_id', 'approximate_hours', 'completed_hours', 'completion_remarks']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_team_lead', 'project_id']);
        });

        Schema::dropIfExists('projects');
    }
};
