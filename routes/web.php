<?php

use App\Http\Controllers\Ai\OrderChatController;
use App\Mcp\Servers\OrderServer;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Mcp\Facades\Mcp;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Mcp::web('/mcp/orders', OrderServer::class);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('chats', function () {
        return Inertia::render('chats/index');
    })->name('chats');

    Route::post('chats/send', [OrderChatController::class, 'chat'])
        ->name('chats.send');
});

require __DIR__.'/settings.php';
