<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('chat.index');
Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage'])->name('chat.messages.store');
Route::patch('/conversations/{conversation}', [ChatController::class, 'updateConversation'])->name('chat.conversations.update');
Route::post('/conversations/{conversation}/simulate-customer', [ChatController::class, 'simulateCustomer'])->name('chat.simulate');
Route::post('/canned-responses', [ChatController::class, 'storeCannedResponse'])->name('chat.canned-responses.store');
