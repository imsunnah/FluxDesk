<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for separated Admin Setup tables:
     * - departments
     * - designations
     * - clients
     * - client_assignments (the assign table)
     * - projects enhancement
     */
    public function up(): void
    {
        // 1. DEPARTMENTS TABLE
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->foreignId('hod_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        // 2. DESIGNATIONS TABLE
        if (!Schema::hasTable('designations')) {
            Schema::create('designations', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('department_name')->nullable();
                $table->string('band')->default('Mid'); // Entry, Mid, Senior, Lead, Executive
                $table->text('responsibilities')->nullable();
                $table->timestamps();
            });
        }

        // 3. CLIENTS TABLE
        if (!Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('org_name');
                $table->string('reg_number')->nullable();
                $table->string('industry')->nullable();
                $table->text('billing_address')->nullable();
                $table->string('poc_name')->nullable();
                $table->string('poc_email')->nullable();
                $table->string('poc_phone')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        // 4. CLIENT ASSIGNMENTS TABLE (Assign Table: Client-Handling Team)
        if (!Schema::hasTable('client_assignments')) {
            Schema::create('client_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('primary_manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('backup_escalation_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('selected_departments')->nullable();
                $table->json('assigned_member_ids')->nullable();
                $table->string('sla_level')->default('Gold'); // Standard, Gold, VIP
                $table->date('assignment_date')->nullable();
                $table->string('communication_preference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. ENHANCE PROJECTS TABLE
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (!Schema::hasColumn('projects', 'code')) {
                    $table->string('code')->nullable()->after('id');
                }
                if (!Schema::hasColumn('projects', 'team_lead_id')) {
                    $table->foreignId('team_lead_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('projects', 'team_member_ids')) {
                    $table->json('team_member_ids')->nullable()->after('team_lead_id');
                }
                if (!Schema::hasColumn('projects', 'start_date')) {
                    $table->date('start_date')->nullable()->after('team_member_ids');
                }
                if (!Schema::hasColumn('projects', 'target_date')) {
                    $table->date('target_date')->nullable()->after('start_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_assignments');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');

        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                if (Schema::hasColumn('projects', 'target_date')) {
                    $table->dropColumn('target_date');
                }
                if (Schema::hasColumn('projects', 'start_date')) {
                    $table->dropColumn('start_date');
                }
                if (Schema::hasColumn('projects', 'team_member_ids')) {
                    $table->dropColumn('team_member_ids');
                }
                if (Schema::hasColumn('projects', 'team_lead_id')) {
                    $table->dropForeign(['team_lead_id']);
                    $table->dropColumn('team_lead_id');
                }
                if (Schema::hasColumn('projects', 'code')) {
                    $table->dropColumn('code');
                }
            });
        }
    }
};
