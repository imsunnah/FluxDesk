<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// Authentication (Guest routes)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Fast user login / switch
Route::get('/quick-login/{user}', [AuthController::class, 'quickLogin'])->name('quick-login');

// Protected Workspace Routes (Must be logged in)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Chat & Ticket Workspace
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/tickets', [ChatController::class, 'createTicket'])->name('chat.tickets.store');
    Route::post('/conversations/{conversation}/claim', [ChatController::class, 'claimTicket'])->name('chat.tickets.claim');
    Route::post('/conversations/{conversation}/reassign', [ChatController::class, 'reassignTicket'])->name('chat.tickets.reassign');

    Route::post('/conversations/direct', [ChatController::class, 'createDirectChat'])->name('chat.direct.store');
    Route::post('/conversations/group', [ChatController::class, 'createGroupChat'])->name('chat.group.store');
    Route::delete('/conversations/{conversation}', [ChatController::class, 'deleteConversation'])->name('chat.conversations.delete');

    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chat.messages.store');
    Route::delete('/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.delete');

    Route::patch('/conversations/{conversation}', [ChatController::class, 'updateConversation'])->name('chat.conversations.update');
    Route::post('/conversations/{conversation}/simulate-customer', [ChatController::class, 'simulateCustomer'])->name('chat.simulate');

    // Member & Group Management
    Route::post('/members', [ChatController::class, 'addMember'])->name('chat.members.store');
    Route::delete('/members/{user}', [ChatController::class, 'deleteMember'])->name('chat.members.delete');
    Route::post('/users/{user}/assign-agent', [ChatController::class, 'assignCustomerAgent'])->name('chat.users.assign-agent');
    Route::post('/users/{user}/toggle-customer-access', [ChatController::class, 'toggleCustomerAccess'])->name('chat.users.toggle-customer-access');
    Route::post('/conversations/{conversation}/members', [ChatController::class, 'joinConversationMember'])->name('chat.conversation.members.join');
    Route::post('/conversations/{conversation}/members/{user}/admin', [ChatController::class, 'makeGroupAdmin'])->name('chat.conversation.members.make-admin');
    Route::delete('/conversations/{conversation}/members/{user}', [ChatController::class, 'removeGroupMember'])->name('chat.conversation.members.remove');

    // Canned Responses
    Route::post('/canned-responses', [ChatController::class, 'storeCannedResponse'])->name('chat.canned-responses.store');

    // Task Management (Work Status)
    Route::post('/tasks', [\App\Http\Controllers\TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{task}/delay-reason', [\App\Http\Controllers\TaskController::class, 'updateDelayReason'])->name('tasks.delay-reason');
    Route::delete('/tasks/{task}', [\App\Http\Controllers\TaskController::class, 'destroy'])->name('tasks.delete');

    // Admin Setup & Management (Projects, Team Leads, Clients)
    Route::post('/projects', [ChatController::class, 'storeProject'])->name('projects.store');
    Route::patch('/projects/{project}', [ChatController::class, 'updateProject'])->name('projects.update');
    Route::delete('/projects/{project}', [ChatController::class, 'deleteProject'])->name('projects.delete');
    Route::post('/users/{user}/toggle-team-lead', [ChatController::class, 'toggleTeamLead'])->name('users.toggle-team-lead');
    Route::post('/users/{user}/update-project', [ChatController::class, 'updateUserProject'])->name('users.update-project');
});
