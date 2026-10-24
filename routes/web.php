<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/quick-login/{user}', [AuthController::class, 'quickLogin'])->name('quick-login');

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
Route::post('/conversations/{conversation}/members', [ChatController::class, 'joinConversationMember'])->name('chat.conversation.members.join');
Route::post('/conversations/{conversation}/members/{user}/admin', [ChatController::class, 'makeGroupAdmin'])->name('chat.conversation.members.make-admin');
Route::delete('/conversations/{conversation}/members/{user}', [ChatController::class, 'removeGroupMember'])->name('chat.conversation.members.remove');

// Canned Responses
Route::post('/canned-responses', [ChatController::class, 'storeCannedResponse'])->name('chat.canned-responses.store');
