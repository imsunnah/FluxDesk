<?php

namespace Database\Seeders;

use App\Models\CannedResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with clean staff, customer, ticket, and chat records.
     */
    public function run(): void
    {
        // 1. Staff / Admins
        $alex = User::updateOrCreate(
            ['email' => 'alex@fluxdesk.io'],
            [
                'member_id' => 'ADM-001',
                'name' => 'Alex Rivera (Admin & Customer)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=faces',
                'role' => 'admin',
                'is_admin' => true,
                'is_customer' => true, // Dual access: can work as staff and can create tickets!
            ]
        );

        $elena = User::updateOrCreate(
            ['email' => 'elena@fluxdesk.io'],
            [
                'member_id' => 'EMP-102',
                'name' => 'Elena Rostova (Staff)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&h=150&fit=crop&crop=faces',
                'role' => 'agent',
                'is_admin' => false,
                'is_customer' => false, // Pure staff: CANNOT create tickets!
            ]
        );

        $marcus = User::updateOrCreate(
            ['email' => 'marcus@fluxdesk.io'],
            [
                'member_id' => 'EMP-103',
                'name' => 'Marcus Vance (Staff)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=faces',
                'role' => 'agent',
                'is_admin' => false,
                'is_customer' => false, // Pure staff: CANNOT create tickets!
            ]
        );

        // 2. Customers
        $sarah = User::updateOrCreate(
            ['email' => 'sarah@customer.com'],
            [
                'member_id' => 'CUST-501',
                'name' => 'Sarah Jenkins (Customer)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop&crop=faces',
                'role' => 'customer',
                'is_admin' => false,
                'is_customer' => true, // Customer: CAN create tickets!
                'assigned_agent_id' => $alex->id,
            ]
        );

        $david = User::updateOrCreate(
            ['email' => 'david@customer.com'],
            [
                'member_id' => 'CUST-502',
                'name' => 'David Chen (Customer)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop&crop=faces',
                'role' => 'customer',
                'is_admin' => false,
                'is_customer' => true, // Customer: CAN create tickets!
                'assigned_agent_id' => $elena->id,
            ]
        );

        $sophia = User::updateOrCreate(
            ['email' => 'sophia@customer.com'],
            [
                'member_id' => 'CUST-503',
                'name' => 'Sophia Martinez (Customer)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&h=150&fit=crop&crop=faces',
                'role' => 'customer',
                'is_admin' => false,
                'is_customer' => true, // Customer: CAN create tickets!
                'assigned_agent_id' => $marcus->id,
            ]
        );

        // 3. Canned Responses
        $canned = [
            ['shortcut' => '/greeting', 'title' => 'Welcome', 'content' => 'Hello! How can I assist you today?'],
            ['shortcut' => '/pricing', 'title' => 'Pricing', 'content' => 'Our Enterprise plan is $79/user/month with dedicated WebSocket clusters.'],
            ['shortcut' => '/resolved', 'title' => 'Resolved', 'content' => 'I am pleased to mark this ticket as resolved. Have a great day!'],
        ];

        foreach ($canned as $item) {
            CannedResponse::updateOrCreate(['shortcut' => $item['shortcut']], $item);
        }

        // Clean out existing conversations to prevent duplicate rows
        Conversation::truncate();
        Message::truncate();

        // 4. TICKETS
        // Ticket 1: Assigned to Alex Rivera
        $t1 = Conversation::create([
            'type' => 'ticket',
            'ticket_number' => 'TK-8421',
            'ticket_subject' => 'Reverb WebSocket Disconnection on Port 8080',
            'ticket_description' => 'Our production dashboard disconnected from Reverb WebSockets about 15 minutes ago.',
            'customer_name' => $sarah->name,
            'customer_email' => $sarah->email,
            'customer_avatar' => $sarah->avatar,
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $alex->id,
            'last_message_at' => Carbon::now()->subMinutes(2),
        ]);
        $t1->members()->attach([
            $alex->id => ['is_group_admin' => true],
            $sarah->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $t1->id,
            'sender_type' => 'customer',
            'sender_id' => $sarah->id,
            'body' => 'Hi, our production dashboard disconnected from Reverb. Are you seeing dropped connections on port 8080?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(15),
        ]);

        Message::create([
            'conversation_id' => $t1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => 'Hello Sarah! I am checking our node telemetry now. Adjusted worker memory to 2GB.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        Message::create([
            'conversation_id' => $t1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => '🔒 Internal Note: Elena, please monitor memory on worker-02.',
            'is_internal_note' => true,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(4),
        ]);

        // Ticket 2: Assigned to Elena Rostova
        $t2 = Conversation::create([
            'type' => 'ticket',
            'ticket_number' => 'TK-9032',
            'ticket_subject' => 'Custom Domain SSL Reverse Proxy Setup',
            'ticket_description' => 'Need help configuring WSS reverse proxy for our white-labeled enterprise portal.',
            'customer_name' => $david->name,
            'customer_email' => $david->email,
            'customer_avatar' => $david->avatar,
            'status' => 'in_progress',
            'priority' => 'medium',
            'assigned_agent_id' => $elena->id,
            'last_message_at' => Carbon::now()->subMinutes(20),
        ]);
        $t2->members()->attach([
            $elena->id => ['is_group_admin' => true],
            $david->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $t2->id,
            'sender_type' => 'customer',
            'sender_id' => $david->id,
            'body' => 'Can you send over the Nginx configuration snippet for Reverb WSS SSL proxying?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(25),
        ]);

        Message::create([
            'conversation_id' => $t2->id,
            'sender_type' => 'agent',
            'sender_id' => $elena->id,
            'body' => 'Hi David! Sure, here is the proxy_pass configuration for port 8080.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(20),
        ]);

        // Ticket 3: UNASSIGNED TICKET (Any agent can "Collect / Claim"!)
        $t3 = Conversation::create([
            'type' => 'ticket',
            'ticket_number' => 'TK-4510',
            'ticket_subject' => 'Database Migration Performance on SQLite',
            'ticket_description' => 'Need advice on WAL mode concurrency for 500+ active sessions.',
            'customer_name' => $sophia->name,
            'customer_email' => $sophia->email,
            'customer_avatar' => $sophia->avatar,
            'status' => 'open',
            'priority' => 'medium',
            'assigned_agent_id' => null, // UNASSIGNED!
            'last_message_at' => Carbon::now()->subMinutes(10),
        ]);
        $t3->members()->attach([
            $sophia->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $t3->id,
            'sender_type' => 'customer',
            'sender_id' => $sophia->id,
            'body' => 'Hello support, we are running migrations and want to enable SQLite WAL mode. Which config controls this?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        // 5. GROUP CHATS (Staff only)
        $g1 = Conversation::create([
            'type' => 'group',
            'group_name' => '⚡ Core DevOps & Infrastructure',
            'group_description' => 'Real-time devops incident response room',
            'is_encrypted' => false,
            'customer_name' => '⚡ Core DevOps',
            'customer_email' => 'devops@fluxdesk.internal',
            'customer_avatar' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=150&h=150&fit=crop',
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $alex->id,
            'last_message_at' => Carbon::now()->subMinutes(1),
        ]);
        $g1->members()->attach([
            $alex->id => ['is_group_admin' => true],
            $elena->id => ['is_group_admin' => false],
            $marcus->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $g1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => 'Reverb WebSocket cluster connection is healthy. Sub-15ms ping across all regions.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(1),
        ]);

        // 6. DIRECT CHAT (Alex & Elena)
        $d1 = Conversation::create([
            'type' => 'direct',
            'is_encrypted' => false,
            'customer_name' => $elena->name,
            'customer_email' => $elena->email,
            'customer_avatar' => $elena->avatar,
            'status' => 'open',
            'priority' => 'low',
            'assigned_agent_id' => $alex->id,
            'last_message_at' => Carbon::now()->subMinutes(30),
        ]);
        $d1->members()->attach([$alex->id, $elena->id]);

        Message::create([
            'conversation_id' => $d1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => 'Hey Elena, let me know when you finish reviewing the Redis queue limits.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);
    }
}
