<?php

namespace Database\Seeders;

use App\Models\CannedResponse;
use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Message;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with clean, standardized records:
     * - Admin & Staff / Employees
     * - Separated Departments Table
     * - Separated Designations Table
     * - Separated Clients Table
     * - Separated Client Assignments (Assign Table: Client-Handling Team)
     * - Separated Projects Table (with Team Lead and Member assignments)
     * - Tasks with overtime ratio and completion remarks
     * - Tickets and Direct / Group Chats
     */
    public function run(): void
    {
        // 1. ADMIN USER
        $admin = User::updateOrCreate(
            ['email' => 'admin@fluxdesk.io'],
            [
                'member_id' => 'ADM-001',
                'name' => 'Admin User',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=faces',
                'role' => 'System Administrator',
                'department' => 'Management & Operations',
                'designation' => 'Principal Operations Director',
                'is_admin' => true,
                'is_customer' => false,
                'is_team_lead' => true,
            ]
        );

        // 2. STAFF / EMPLOYEES
        // User 1 (Team Lead - can assign tasks to multiple persons)
        $user1 = User::updateOrCreate(
            ['email' => 'user1@fluxdesk.io'],
            [
                'member_id' => 'EMP-001',
                'name' => 'User 1 (Team Lead)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=faces',
                'role' => 'Senior Team Lead',
                'department' => 'Engineering',
                'designation' => 'Staff Solutions Architect & Lead',
                'is_admin' => false,
                'is_customer' => false,
                'is_team_lead' => true, // Team Lead!
            ]
        );

        // User 2 (Employee - creates own tasks)
        $user2 = User::updateOrCreate(
            ['email' => 'user2@fluxdesk.io'],
            [
                'member_id' => 'EMP-002',
                'name' => 'User 2 (Employee)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop&crop=faces',
                'role' => 'Full-Stack Engineer',
                'department' => 'Engineering',
                'designation' => 'Senior Backend Developer',
                'is_admin' => false,
                'is_customer' => false,
                'is_team_lead' => false, // Regular Employee!
            ]
        );

        // User 3 (Employee - creates own tasks)
        $user3 = User::updateOrCreate(
            ['email' => 'user3@fluxdesk.io'],
            [
                'member_id' => 'EMP-003',
                'name' => 'User 3 (Employee)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&h=150&fit=crop&crop=faces',
                'role' => 'Frontend & UI Developer',
                'department' => 'Product & Design',
                'designation' => 'Lead UI/UX Frontend Engineer',
                'is_admin' => false,
                'is_customer' => false,
                'is_team_lead' => false, // Regular Employee!
            ]
        );

        // 3. CUSTOMERS / CLIENT LOGIN USERS
        $customer1 = User::updateOrCreate(
            ['email' => 'customer1@fluxdesk.io'],
            [
                'member_id' => 'CUST-001',
                'name' => 'Customer 1 (Acme Corp)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop&crop=faces',
                'role' => 'customer',
                'department' => 'Executive Office',
                'designation' => 'VP of Cloud Infrastructure',
                'is_admin' => false,
                'is_customer' => true,
                'is_team_lead' => false,
                'assigned_agent_id' => $user1->id,
            ]
        );

        $customer2 = User::updateOrCreate(
            ['email' => 'customer2@fluxdesk.io'],
            [
                'member_id' => 'CUST-002',
                'name' => 'Customer 2 (Nexus Health)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&h=150&fit=crop&crop=faces',
                'role' => 'customer',
                'department' => 'Clinical Telehealth',
                'designation' => 'Head of Medical Informatics',
                'is_admin' => false,
                'is_customer' => true,
                'is_team_lead' => false,
                'assigned_agent_id' => $user2->id,
            ]
        );

        $customer3 = User::updateOrCreate(
            ['email' => 'customer3@fluxdesk.io'],
            [
                'member_id' => 'CUST-003',
                'name' => 'Customer 3 (Apex Retail)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=faces',
                'role' => 'customer',
                'department' => 'Digital Commerce',
                'designation' => 'Director of Retail Engineering',
                'is_admin' => false,
                'is_customer' => true,
                'is_team_lead' => false,
                'assigned_agent_id' => $user3->id,
            ]
        );

        // 4. SEPARATED DEPARTMENTS TABLE
        $deptEng = Department::updateOrCreate(
            ['code' => 'ENG'],
            [
                'name' => 'Engineering',
                'description' => 'Core software engineering, architecture, frontend & backend engineering operations.',
                'hod_id' => $user1->id,
                'status' => 'active',
            ]
        );
        $deptSupport = Department::updateOrCreate(
            ['code' => 'SUP'],
            [
                'name' => 'Technical Support',
                'description' => 'Tier 1 to Tier 3 customer technical inquiries, bug escalations, and incident triage.',
                'hod_id' => $user2->id,
                'status' => 'active',
            ]
        );
        $deptCS = Department::updateOrCreate(
            ['code' => 'CS'],
            [
                'name' => 'Customer Success',
                'description' => 'Client account management, onboarding, retention, and SLA delivery tracking.',
                'hod_id' => $admin->id,
                'status' => 'active',
            ]
        );
        $deptQA = Department::updateOrCreate(
            ['code' => 'QA'],
            [
                'name' => 'Quality Assurance',
                'description' => 'Automated test suite maintenance, manual validation, performance testing, and release gating.',
                'hod_id' => $user3->id,
                'status' => 'active',
            ]
        );
        $deptOps = Department::updateOrCreate(
            ['code' => 'OPS'],
            [
                'name' => 'DevOps & Infrastructure',
                'description' => 'Cloud infrastructure orchestration, CI/CD pipelines, Kubernetes clusters, and security observability.',
                'hod_id' => $user1->id,
                'status' => 'active',
            ]
        );
        $deptProduct = Department::updateOrCreate(
            ['code' => 'PRD'],
            [
                'name' => 'Product & Design',
                'description' => 'User experience research, UI architecture, product requirement definitions, and prototyping.',
                'hod_id' => $user3->id,
                'status' => 'active',
            ]
        );
        $deptMgmt = Department::updateOrCreate(
            ['code' => 'MGT'],
            [
                'name' => 'Management & Operations',
                'description' => 'Executive governance, administrative oversight, resource allocation, and organizational strategy.',
                'hod_id' => $admin->id,
                'status' => 'active',
            ]
        );

        // 5. SEPARATED DESIGNATIONS TABLE
        $designations = [
            ['title' => 'Principal Operations Director', 'department_id' => $deptMgmt->id, 'department_name' => 'Management & Operations', 'band' => 'Executive', 'responsibilities' => 'Strategic organizational planning, cross-functional resource allocation, and operational excellence.'],
            ['title' => 'Staff Solutions Architect & Lead', 'department_id' => $deptEng->id, 'department_name' => 'Engineering', 'band' => 'Lead', 'responsibilities' => 'System architecture, technical guidance, high-level code reviews, and team leadership.'],
            ['title' => 'Senior Backend Developer', 'department_id' => $deptEng->id, 'department_name' => 'Engineering', 'band' => 'Senior', 'responsibilities' => 'API design, database optimization, backend service reliability, and async queues.'],
            ['title' => 'Lead UI/UX Frontend Engineer', 'department_id' => $deptProduct->id, 'department_name' => 'Product & Design', 'band' => 'Lead', 'responsibilities' => 'Component library creation, design system adherence, frontend performance, and micro-interactions.'],
            ['title' => 'Senior DevOps & Cloud Engineer', 'department_id' => $deptOps->id, 'department_name' => 'DevOps & Infrastructure', 'band' => 'Senior', 'responsibilities' => 'Terraform IaC, Docker clustering, zero-downtime deployments, and telemetry.'],
            ['title' => 'Customer Success Lead', 'department_id' => $deptCS->id, 'department_name' => 'Customer Success', 'band' => 'Senior', 'responsibilities' => 'Client relations, SLA metrics, customer satisfaction reviews, and executive escalations.'],
            ['title' => 'QA Automation Engineer', 'department_id' => $deptQA->id, 'department_name' => 'Quality Assurance', 'band' => 'Mid', 'responsibilities' => 'End-to-end Cypress and Playwright testing, regression test suites, and load test scripts.'],
        ];
        foreach ($designations as $d) {
            Designation::updateOrCreate(
                ['title' => $d['title'], 'department_id' => $d['department_id']],
                $d
            );
        }

        // 6. SEPARATED CLIENTS TABLE
        $cl1 = Client::updateOrCreate(
            ['org_name' => 'Acme Corporation'],
            [
                'user_id' => $customer1->id,
                'reg_number' => 'ACM-US-8921',
                'industry' => 'Enterprise Cloud & Logistics',
                'billing_address' => '100 Silicon Blvd, Suite 400, San Jose, CA 95134',
                'poc_name' => 'Customer 1 (Acme Corp)',
                'poc_email' => 'customer1@fluxdesk.io',
                'poc_phone' => '+1 (555) 234-5678',
                'status' => 'active',
            ]
        );

        $cl2 = Client::updateOrCreate(
            ['org_name' => 'Nexus Health Systems'],
            [
                'user_id' => $customer2->id,
                'reg_number' => 'NXH-US-4432',
                'industry' => 'Healthcare & Telehealth Informatics',
                'billing_address' => '450 Medical Center Way, Boston, MA 02115',
                'poc_name' => 'Customer 2 (Nexus Health)',
                'poc_email' => 'customer2@fluxdesk.io',
                'poc_phone' => '+1 (555) 345-6789',
                'status' => 'active',
            ]
        );

        $cl3 = Client::updateOrCreate(
            ['org_name' => 'Apex Retail Global'],
            [
                'user_id' => $customer3->id,
                'reg_number' => 'APX-UK-1120',
                'industry' => 'High-Growth E-Commerce & Retail',
                'billing_address' => '12 Oxford Street, London, W1D 1BS, UK',
                'poc_name' => 'Customer 3 (Apex Retail)',
                'poc_email' => 'customer3@fluxdesk.io',
                'poc_phone' => '+1 (555) 456-7890',
                'status' => 'active',
            ]
        );

        // 7. SEPARATED CLIENT ASSIGNMENTS (ASSIGN TABLE)
        ClientAssignment::updateOrCreate(
            ['client_id' => $cl1->id],
            [
                'primary_manager_id' => $user1->id,
                'backup_escalation_id' => $admin->id,
                'selected_departments' => ['Engineering', 'DevOps & Infrastructure'],
                'assigned_member_ids' => [$user1->id, $user2->id],
                'sla_level' => 'VIP',
                'assignment_date' => Carbon::now()->subMonths(3)->toDateString(),
                'communication_preference' => 'Dedicated Slack Connect + 24/7 Hotline',
                'notes' => 'Primary enterprise account for North America cloud operations. Escalate critical downtime to User 1 immediately.',
            ]
        );

        ClientAssignment::updateOrCreate(
            ['client_id' => $cl2->id],
            [
                'primary_manager_id' => $user2->id,
                'backup_escalation_id' => $user1->id,
                'selected_departments' => ['Engineering', 'Quality Assurance'],
                'assigned_member_ids' => [$user2->id, $user3->id],
                'sla_level' => 'Gold',
                'assignment_date' => Carbon::now()->subMonths(2)->toDateString(),
                'communication_preference' => 'Bi-Weekly Zoom Review & Dedicated Queue',
                'notes' => 'HIPAA compliant infrastructure. All tickets require QA approval before deployment.',
            ]
        );

        ClientAssignment::updateOrCreate(
            ['client_id' => $cl3->id],
            [
                'primary_manager_id' => $user3->id,
                'backup_escalation_id' => $user1->id,
                'selected_departments' => ['Product & Design', 'Engineering'],
                'assigned_member_ids' => [$user1->id, $user2->id, $user3->id],
                'sla_level' => 'Standard',
                'assignment_date' => Carbon::now()->subMonth()->toDateString(),
                'communication_preference' => 'Email Dispatch & Weekly Standup',
                'notes' => 'High seasonal transaction volume. Peak traffic monitoring during sales events.',
            ]
        );

        // 8. SEPARATED PROJECTS TABLE (Linked to Client, Team Lead, and Members)
        $p1 = Project::updateOrCreate(
            ['name' => 'Project 1A — Acme Cloud ERP'],
            [
                'code' => 'PRJ-ACM-01',
                'client_id' => $customer1->id,
                'team_lead_id' => $user1->id,
                'team_member_ids' => [$user1->id, $user2->id],
                'start_date' => '2026-01-15',
                'target_date' => '2026-12-31',
                'description' => 'Enterprise Cloud ERP suite featuring multi-tenant databases and automated billing.',
                'status' => 'active',
            ]
        );

        $p1b = Project::updateOrCreate(
            ['name' => 'Project 1B — Acme Mobile Warehouse App'],
            [
                'code' => 'PRJ-ACM-02',
                'client_id' => $customer1->id,
                'team_lead_id' => $user1->id,
                'team_member_ids' => [$user2->id],
                'start_date' => '2026-03-01',
                'target_date' => '2026-09-30',
                'description' => 'Barcode scanning and real-time inventory synchronization for Acme Corp.',
                'status' => 'active',
            ]
        );

        $p2 = Project::updateOrCreate(
            ['name' => 'Project 2 — Nexus Mobile App'],
            [
                'code' => 'PRJ-NXH-01',
                'client_id' => $customer2->id,
                'team_lead_id' => $user1->id,
                'team_member_ids' => [$user2->id, $user3->id],
                'start_date' => '2026-02-10',
                'target_date' => '2026-11-15',
                'description' => 'Cross-platform mobile application for real-time patient biometric tracking and secure telehealth.',
                'status' => 'active',
            ]
        );

        $p3 = Project::updateOrCreate(
            ['name' => 'Project 3 — Apex E-Commerce Platform'],
            [
                'code' => 'PRJ-APX-01',
                'client_id' => $customer3->id,
                'team_lead_id' => $user1->id,
                'team_member_ids' => [$user1->id, $user3->id],
                'start_date' => '2026-04-01',
                'target_date' => '2026-10-31',
                'description' => 'High-throughput retail storefront with instant Stripe checkout and inventory synchronization.',
                'status' => 'active',
            ]
        );

        // Link Clients directly to their Projects
        $customer1->update(['project_id' => $p1->id]);
        $customer2->update(['project_id' => $p2->id]);
        $customer3->update(['project_id' => $p3->id]);

        // 5. CANNED RESPONSES
        $canned = [
            ['shortcut' => '/greeting', 'title' => 'Welcome', 'content' => 'Hello! How can I assist you with your project today?'],
            ['shortcut' => '/update', 'title' => 'Status Update', 'content' => 'We are currently reviewing the telemetry data and will report back within the hour.'],
            ['shortcut' => '/resolved', 'title' => 'Resolved', 'content' => 'I am pleased to confirm this issue has been resolved. Please reach out if you need anything else!'],
        ];
        foreach ($canned as $item) {
            CannedResponse::updateOrCreate(['shortcut' => $item['shortcut']], $item);
        }

        // Clean out existing conversations and messages
        Conversation::truncate();
        Message::truncate();
        DB::table('conversation_members')->truncate();

        // 6. TICKETS (Strictly bound to Project and Client)
        // Ticket 1: Customer 1 (Project 1 - Acme Cloud ERP), assigned to User 1
        $t1 = Conversation::create([
            'type' => 'ticket',
            'project_id' => $p1->id,
            'ticket_number' => 'TK-1001',
            'ticket_subject' => 'Reverb WebSocket Disconnection on Port 8080',
            'ticket_description' => 'Our ERP dashboard disconnected from WebSocket cluster 15 mins ago during invoice generation.',
            'customer_name' => $customer1->name,
            'customer_email' => $customer1->email,
            'customer_avatar' => $customer1->avatar,
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $user1->id,
            'last_message_at' => Carbon::now()->subMinutes(5),
        ]);
        $t1->members()->attach([
            $user1->id => ['is_group_admin' => true],
            $customer1->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $t1->id,
            'sender_type' => 'customer',
            'sender_id' => $customer1->id,
            'body' => 'Hello support, our production dashboard disconnected from the live stream. Are connections on port 8080 active?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(15),
        ]);

        Message::create([
            'conversation_id' => $t1->id,
            'sender_type' => 'agent',
            'sender_id' => $user1->id,
            'body' => 'Hi Customer 1! I am reviewing the Reverb cluster logs now. Adjusted worker memory limit to 2GB.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        // Ticket 2: Customer 2 (Project 2 - Nexus Mobile App), assigned to User 2
        $t2 = Conversation::create([
            'type' => 'ticket',
            'project_id' => $p2->id,
            'ticket_number' => 'TK-1002',
            'ticket_subject' => 'Push Notification Latency on Android 14',
            'ticket_description' => 'Urgent alerts are experiencing a 2-3 minute latency on the latest Android release build.',
            'customer_name' => $customer2->name,
            'customer_email' => $customer2->email,
            'customer_avatar' => $customer2->avatar,
            'status' => 'in_progress',
            'priority' => 'medium',
            'assigned_agent_id' => $user2->id,
            'last_message_at' => Carbon::now()->subMinutes(12),
        ]);
        $t2->members()->attach([
            $user2->id => ['is_group_admin' => true],
            $customer2->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $t2->id,
            'sender_type' => 'customer',
            'sender_id' => $customer2->id,
            'body' => 'We noticed Firebase FCM push tokens are taking up to 3 minutes to trigger on Android 14 devices.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(25),
        ]);

        Message::create([
            'conversation_id' => $t2->id,
            'sender_type' => 'agent',
            'sender_id' => $user2->id,
            'body' => 'Hello Customer 2! We are testing the high-priority notification payload configuration in staging.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(12),
        ]);

        // Ticket 3: Customer 3 (Project 3 - Apex E-Commerce), UNASSIGNED POOL TICKET
        $t3 = Conversation::create([
            'type' => 'ticket',
            'project_id' => $p3->id,
            'ticket_number' => 'TK-1003',
            'ticket_subject' => 'Stripe Webhook Signature Verification Failures',
            'ticket_description' => 'Intermittent 400 Bad Request error returned during checkout event processing.',
            'customer_name' => $customer3->name,
            'customer_email' => $customer3->email,
            'customer_avatar' => $customer3->avatar,
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => null, // UNASSIGNED POOL TICKET
            'last_message_at' => Carbon::now()->subMinutes(8),
        ]);
        $t3->members()->attach([
            $customer3->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $t3->id,
            'sender_type' => 'customer',
            'sender_id' => $customer3->id,
            'body' => 'Hi team, checkout webhooks are failing signature verification on production. Could someone look into this?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(8),
        ]);

        // 7. REALTIME CHATS
        // Group Room: Internal Engineering (Staff only)
        $g1 = Conversation::create([
            'type' => 'group',
            'group_name' => '⚡ Core Engineering & DevOps',
            'group_description' => 'Internal engineering discussion room',
            'is_encrypted' => false,
            'customer_name' => 'Core Engineering',
            'customer_email' => 'engineering@fluxdesk.internal',
            'customer_avatar' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=150&h=150&fit=crop',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_agent_id' => $admin->id,
            'last_message_at' => Carbon::now()->subMinutes(2),
        ]);
        $g1->members()->attach([
            $admin->id => ['is_group_admin' => true],
            $user1->id => ['is_group_admin' => false],
            $user2->id => ['is_group_admin' => false],
            $user3->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $g1->id,
            'sender_type' => 'agent',
            'sender_id' => $user1->id,
            'body' => 'Team, please review your work status approximate vs completed hours before standup today.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        Message::create([
            'conversation_id' => $g1->id,
            'sender_type' => 'agent',
            'sender_id' => $user2->id,
            'body' => 'Understood! I finished the query indexing task and logged completion remarks.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(2),
        ]);

        // Direct Chat: Customer 1 & User 1
        $d1 = Conversation::create([
            'type' => 'direct',
            'project_id' => $p1->id,
            'is_encrypted' => false,
            'customer_name' => $customer1->name,
            'customer_email' => $customer1->email,
            'customer_avatar' => $customer1->avatar,
            'status' => 'open',
            'priority' => 'medium',
            'assigned_agent_id' => $user1->id,
            'last_message_at' => Carbon::now()->subMinutes(3),
        ]);
        $d1->members()->attach([
            $user1->id => ['is_group_admin' => false],
            $customer1->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $d1->id,
            'sender_type' => 'customer',
            'sender_id' => $customer1->id,
            'body' => 'Hi User 1, do you have a quick moment regarding the SSL renewal schedule?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(7),
        ]);

        Message::create([
            'conversation_id' => $d1->id,
            'sender_type' => 'agent',
            'sender_id' => $user1->id,
            'body' => 'Yes, absolutely! The certificate was updated on production this morning.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(3),
        ]);

        // 8. WORK STATUS TASKS
        Task::truncate();
        DB::table('task_user')->truncate();

        // Task 1: Overtime Task (Actual 6.5h vs Approx 4h => 1.63x Overtime)
        $task1 = Task::create([
            'title' => 'Update production SSL certificate for API',
            'project_id' => $p1->id,
            'client_id' => $customer1->id,
            'description' => 'Renew wildcard SSL certificate before expiration next week.',
            'status' => 'done',
            'priority' => 'high',
            'approximate_hours' => 4.0,
            'completed_hours' => 6.5, // 6.5 / 4.0 = 1.63x
            'completion_remarks' => 'Renewed successfully via Let\'s Encrypt DNS verification challenge. Reloaded Nginx cluster without downtime.',
            'assigned_to' => $user1->id,
            'created_by' => $user1->id,
            'due_date' => Carbon::now()->addDays(2),
        ]);
        $task1->assignees()->sync([$user1->id, $user2->id]); // Multiple assignees by Team Lead!

        // Task 2: In-Progress Task
        $task2 = Task::create([
            'title' => 'Optimize SQLite query indexing for messages',
            'project_id' => $p2->id,
            'client_id' => $customer2->id,
            'description' => 'Add compound index on conversation_id and created_at to speed up scroll load.',
            'status' => 'in_progress',
            'priority' => 'medium',
            'approximate_hours' => 6.0,
            'completed_hours' => 3.5,
            'assigned_to' => $user2->id,
            'created_by' => $user1->id,
            'due_date' => Carbon::now()->addDays(5),
        ]);
        $task2->assignees()->sync([$user2->id, $user3->id]); // Multiple assignees

        // Task 3: Overtime Task (Actual 8h vs Approx 5h => 1.60x Overtime)
        $task3 = Task::create([
            'title' => 'Implement real-time payment webhook verification',
            'project_id' => $p3->id,
            'client_id' => $customer3->id,
            'description' => 'Implement HMAC-SHA256 signature verification and exponential backoff retry queue.',
            'status' => 'done',
            'priority' => 'high',
            'approximate_hours' => 5.0,
            'completed_hours' => 8.0, // 8.0 / 5.0 = 1.60x
            'completion_remarks' => 'Configured Stripe webhook endpoint with payload signature validation and Redis retry workers.',
            'assigned_to' => $user3->id,
            'created_by' => $user1->id,
            'due_date' => Carbon::now()->addDays(1),
        ]);
        $task3->assignees()->sync([$user1->id, $user3->id]);

        // Task 4: To-Do Task
        $task4 = Task::create([
            'title' => 'Configure Reverb WebSocket connection failover',
            'project_id' => $p2->id,
            'client_id' => $customer2->id,
            'description' => 'Set up automated client reconnect handler on dropped network connections.',
            'status' => 'todo',
            'priority' => 'high',
            'approximate_hours' => 5.0,
            'assigned_to' => $user2->id,
            'created_by' => $user2->id,
            'due_date' => Carbon::now()->addDays(3),
        ]);
        $task4->assignees()->sync([$user2->id]);
    }
}
