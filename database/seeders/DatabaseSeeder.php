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
     * Seed the application's database with realistic production-grade chat data.
     */
    public function run(): void
    {
        // 1. Seed Agents / Admins
        $alex = User::updateOrCreate(
            ['email' => 'alex@fluxdesk.io'],
            [
                'member_id' => 'ADM-001',
                'name' => 'Alex Rivera (Admin)',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop&crop=faces',
                'role' => 'System Administrator',
                'is_admin' => true,
            ]
        );

        $elena = User::updateOrCreate(
            ['email' => 'elena@fluxdesk.io'],
            [
                'member_id' => 'EMP-102',
                'name' => 'Elena Rostova',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&h=150&fit=crop&crop=faces',
                'role' => 'Senior Technical Agent',
                'is_admin' => false,
            ]
        );

        $marcus = User::updateOrCreate(
            ['email' => 'marcus@fluxdesk.io'],
            [
                'member_id' => 'EMP-103',
                'name' => 'Marcus Vance',
                'password' => bcrypt('password123'),
                'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop&crop=faces',
                'role' => 'Customer Success Lead',
                'is_admin' => false,
            ]
        );

        // 2. Seed Canned Responses
        $canned = [
            [
                'shortcut' => '/greeting',
                'title' => 'Warm Welcome & Greeting',
                'content' => 'Hello! Thanks for reaching out to FluxDesk support. How can I assist you with your workspace today?',
            ],
            [
                'shortcut' => '/pricing',
                'title' => 'Enterprise Pricing & Tiers',
                'content' => 'Our Enterprise workspace tier is $79/seat/mo, which includes native Reverb WebSocket clustering, Redis queuing, and 99.99% SLA uptime.',
            ],
            [
                'shortcut' => '/refund',
                'title' => 'Payment Refund Confirmation',
                'content' => 'I have processed the reimbursement request for invoice #FD-8942. The funds will reflect on your card within 3-5 business days.',
            ],
            [
                'shortcut' => '/docs',
                'title' => 'Documentation & API Links',
                'content' => 'You can review our real-time WebSocket protocol and Inertia integration guides directly in our developer docs at https://docs.fluxdesk.io/api',
            ],
            [
                'shortcut' => '/escalate',
                'title' => 'Escalate to Tier 3 Engineering',
                'content' => 'I am escalating this ticket directly to our core infrastructure team to investigate the latency spikes. We will provide a diagnostic update shortly.',
            ],
            [
                'shortcut' => '/resolved',
                'title' => 'Ticket Resolved Closing Note',
                'content' => 'I am marking this inquiry as resolved. Feel free to reply at any time if you have further questions. Have a great day!',
            ],
        ];

        foreach ($canned as $item) {
            CannedResponse::updateOrCreate(['shortcut' => $item['shortcut']], $item);
        }

        // 3. Seed Conversations & Messages
        // Conversation 1: High Priority WebSocket issue
        $c1 = Conversation::create([
            'customer_name' => 'Sarah Jenkins',
            'customer_email' => 'sarah.jenkins@fintechflow.io',
            'customer_phone' => '+1 (415) 890-2134',
            'customer_location' => 'San Francisco, CA',
            'customer_company' => 'FintechFlow Inc.',
            'customer_avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop&crop=faces',
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $alex->id,
            'last_message_at' => Carbon::now()->subMinutes(3),
        ]);

        Message::create([
            'conversation_id' => $c1->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => 'Hi Alex, our production dashboard disconnected from Reverb WebSockets about 15 minutes ago. Are you seeing any dropped connections on port 8080?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(20),
        ]);

        Message::create([
            'conversation_id' => $c1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => 'Hey Sarah! Checking our Reverb presence cluster telemetry right now. One moment while I pull the node heartbeats.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(16),
        ]);

        Message::create([
            'conversation_id' => $c1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => '🔒 Internal Note: Elena, please check Redis pub/sub queue worker memory limits on worker-02. Reverb heartbeat dropped 2 nodes during peak SSL renegotiation.',
            'is_internal_note' => true,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(12),
        ]);

        Message::create([
            'conversation_id' => $c1->id,
            'sender_type' => 'agent',
            'sender_id' => $elena->id,
            'body' => '🔒 Internal Note: Found it! Worker-02 was throttled at 512MB. Scaled Redis supervisor up to 2GB and restarted queue workers. All clear.',
            'is_internal_note' => true,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(8),
        ]);

        Message::create([
            'conversation_id' => $c1->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => 'Sarah, we adjusted the worker allocation and Reverb has fully restabilized. Can you verify if your live feed has resumed without latency?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(6),
        ]);

        Message::create([
            'conversation_id' => $c1->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => 'Everything just reconnected instantly! Sub-50ms ping now. Thanks for the lightning fast turnaround!',
            'is_internal_note' => false,
            'is_read' => false,
            'created_at' => Carbon::now()->subMinutes(3),
        ]);

        // Conversation 2: David Chen - Pending
        $c2 = Conversation::create([
            'customer_name' => 'David Chen',
            'customer_email' => 'david@cloudscale.net',
            'customer_phone' => '+1 (416) 555-0199',
            'customer_location' => 'Toronto, ON',
            'customer_company' => 'CloudScale Technologies',
            'customer_avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop&crop=faces',
            'status' => 'pending',
            'priority' => 'medium',
            'assigned_agent_id' => $elena->id,
            'last_message_at' => Carbon::now()->subMinutes(25),
        ]);

        Message::create([
            'conversation_id' => $c2->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => 'Can we configure custom Webhook endpoints for offline notifications when our agents are away from keyboard?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(45),
        ]);

        Message::create([
            'conversation_id' => $c2->id,
            'sender_type' => 'agent',
            'sender_id' => $elena->id,
            'body' => 'Hello David! Yes, FluxDesk dispatches `ProcessOfflineNotification` queue jobs via Redis. You can register webhook endpoints under Settings > Integrations.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(25),
        ]);

        // Conversation 3: Sophia Martinez - Resolved
        $c3 = Conversation::create([
            'customer_name' => 'Sophia Martinez',
            'customer_email' => 'sophia@pixelstudio.design',
            'customer_phone' => '+34 93 123 4567',
            'customer_location' => 'Barcelona, Spain',
            'customer_company' => 'PixelStudio Creative',
            'customer_avatar' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=150&h=150&fit=crop&crop=faces',
            'status' => 'resolved',
            'priority' => 'low',
            'assigned_agent_id' => $marcus->id,
            'last_message_at' => Carbon::now()->subHours(2),
        ]);

        Message::create([
            'conversation_id' => $c3->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => 'How do I add our team brand colors and logo inside the agent workspace?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subHours(4),
        ]);

        Message::create([
            'conversation_id' => $c3->id,
            'sender_type' => 'agent',
            'sender_id' => $marcus->id,
            'body' => 'Hi Sophia! You can customize Tailwind accents and brand assets under Workspace > Appearance. All changes sync in real-time!',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // Conversation 4: Liam O'Connor - High Priority
        $c4 = Conversation::create([
            'customer_name' => 'Liam O\'Connor',
            'customer_email' => 'liam@novalabs.eu',
            'customer_phone' => '+353 1 496 0000',
            'customer_location' => 'Dublin, Ireland',
            'customer_company' => 'NovaLabs Quantum',
            'customer_avatar' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=150&h=150&fit=crop&crop=faces',
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $alex->id,
            'last_message_at' => Carbon::now()->subMinutes(14),
        ]);

        Message::create([
            'conversation_id' => $c4->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => 'Need help upgrading our SQLite setup to multi-node PostgreSQL before our product launch on Monday.',
            'is_internal_note' => false,
            'is_read' => false,
            'created_at' => Carbon::now()->subMinutes(14),
        ]);

        // Conversation 5: Amara Okafor - Open
        $c5 = Conversation::create([
            'customer_name' => 'Amara Okafor',
            'customer_email' => 'amara@apexsec.co.uk',
            'customer_phone' => '+44 20 7946 0912',
            'customer_location' => 'London, UK',
            'customer_company' => 'Apex Cybersecurity',
            'customer_avatar' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150&h=150&fit=crop&crop=faces',
            'status' => 'open',
            'priority' => 'medium',
            'assigned_agent_id' => $elena->id,
            'last_message_at' => Carbon::now()->subMinutes(40),
        ]);

        Message::create([
            'conversation_id' => $c5->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => 'Do you support single sign-on (SSO) with Okta and SAML 2.0 for enterprise agents?',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(50),
        ]);

        Message::create([
            'conversation_id' => $c5->id,
            'sender_type' => 'agent',
            'sender_id' => $elena->id,
            'body' => 'Yes, Amara! SAML 2.0 and Okta OIDC are supported out of the box on our Enterprise tier.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(40),
        ]);

        // Conversation 6: Group Chat - DevOps Incident Room
        $c6 = Conversation::create([
            'type' => 'group',
            'group_name' => '⚡ DevOps Incident & Core Infra',
            'group_description' => 'Real-time incident response, Reverb monitoring, and queue worker health.',
            'is_encrypted' => false,
            'customer_name' => '⚡ DevOps Incident & Core Infra',
            'customer_email' => 'devops@fluxdesk.internal',
            'customer_avatar' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=150&h=150&fit=crop',
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $alex->id,
            'last_message_at' => Carbon::now()->subMinutes(1),
        ]);

        $c6->members()->attach([
            $alex->id => ['is_group_admin' => true],
            $elena->id => ['is_group_admin' => false],
            $marcus->id => ['is_group_admin' => false],
        ]);

        Message::create([
            'conversation_id' => $c6->id,
            'sender_type' => 'agent',
            'sender_id' => $alex->id,
            'body' => 'Welcome to the core DevOps group! Reverb connection pooling has been enabled across all worker nodes.',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        Message::create([
            'conversation_id' => $c6->id,
            'sender_type' => 'agent',
            'sender_id' => $elena->id,
            'body' => 'Redis queue latency is holding steady at under 15ms. Good work everyone!',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(1),
        ]);

        // Conversation 7: Encrypted Chat - Security Audit & Keys (E2EE)
        $c7 = Conversation::create([
            'type' => 'direct',
            'group_name' => '🔒 Security & Key Rotation (E2EE)',
            'group_description' => 'End-to-End Encrypted channel for rotating production credentials and SSL certificates.',
            'is_encrypted' => true,
            'customer_name' => 'Elena Rostova (Encrypted)',
            'customer_email' => 'elena@fluxdesk.io',
            'customer_avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&h=150&fit=crop&crop=faces',
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $elena->id,
            'last_message_at' => Carbon::now()->subMinutes(5),
        ]);

        $c7->members()->attach([$alex->id, $elena->id]);

        Message::create([
            'conversation_id' => $c7->id,
            'sender_type' => 'agent',
            'sender_id' => $elena->id,
            'body' => '🔐 [End-to-End Encrypted] Elena shared: Production Reverb SSL cert rotation completed. Verification Hash: sha256:7f4a9b2c8e1d...',
            'is_internal_note' => false,
            'is_read' => true,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);
    }
}
