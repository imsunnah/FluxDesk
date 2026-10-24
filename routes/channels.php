<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence Channel for tracking active viewers and typing whisper
Broadcast::channel('chat.presence.{conversationId}', function ($user, $conversationId) {
    if ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=faces',
            'role' => $user->role ?? 'Member',
        ];
    }

    return [
        'id' => 999,
        'name' => 'Support Member',
        'email' => 'support@fluxdesk.io',
        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=faces',
        'role' => 'Guest Agent',
    ];
});
