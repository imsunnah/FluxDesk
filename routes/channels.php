<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence Channel for live agent presence and whisper typing events
Broadcast::channel('chat.{conversationId}', function ($user, $conversationId) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=faces',
            'role' => $user->role ?? 'Support Agent',
        ];
    }

    // Default agent presence for unauthenticated dev agent
    $agentId = session('guest_agent_id', 1);
    return [
        'id' => (int) $agentId,
        'name' => 'Agent Alex (You)',
        'email' => 'alex@fluxdesk.io',
        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=faces',
        'role' => 'Senior Support Specialist',
    ];
});
