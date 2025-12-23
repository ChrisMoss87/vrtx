<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\WhatsApp\Http\Controllers\WhatsAppConnectionController;
use Plugins\WhatsApp\Http\Controllers\WhatsAppConversationController;
use Plugins\WhatsApp\Http\Controllers\WhatsAppMessageController;
use Plugins\WhatsApp\Http\Controllers\WhatsAppTemplateController;
use Plugins\WhatsApp\Http\Controllers\WhatsAppWebhookController;

/*
|--------------------------------------------------------------------------
| WhatsApp Plugin API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the WhatsApp plugin service provider.
| They are prefixed with 'api/v1/whatsapp' and protected by the plugin license middleware.
|
*/

// =========================================================================
// CONNECTIONS
// =========================================================================

Route::prefix('connections')->group(function () {
    Route::get('/', [WhatsAppConnectionController::class, 'index']);
    Route::post('/', [WhatsAppConnectionController::class, 'store']);
    Route::get('/{connection}', [WhatsAppConnectionController::class, 'show']);
    Route::put('/{connection}', [WhatsAppConnectionController::class, 'update']);
    Route::delete('/{connection}', [WhatsAppConnectionController::class, 'destroy']);
    Route::post('/{connection}/test', [WhatsAppConnectionController::class, 'test']);
});

// =========================================================================
// CONVERSATIONS
// =========================================================================

Route::prefix('conversations')->group(function () {
    Route::get('/', [WhatsAppConversationController::class, 'index']);
    Route::post('/', [WhatsAppConversationController::class, 'store']);
    Route::get('/{conversation}', [WhatsAppConversationController::class, 'show']);
    Route::put('/{conversation}', [WhatsAppConversationController::class, 'update']);

    // Conversation actions
    Route::post('/{conversation}/assign', [WhatsAppConversationController::class, 'assign']);
    Route::post('/{conversation}/close', [WhatsAppConversationController::class, 'close']);
    Route::post('/{conversation}/reopen', [WhatsAppConversationController::class, 'reopen']);
    Route::post('/{conversation}/link', [WhatsAppConversationController::class, 'linkToRecord']);

    // Messages within conversation
    Route::get('/{conversation}/messages', [WhatsAppMessageController::class, 'index']);
    Route::post('/{conversation}/messages', [WhatsAppMessageController::class, 'send']);
});

// =========================================================================
// MESSAGES
// =========================================================================

Route::prefix('messages')->group(function () {
    Route::get('/{message}', [WhatsAppMessageController::class, 'show']);
});

// =========================================================================
// TEMPLATES
// =========================================================================

Route::prefix('templates')->group(function () {
    Route::get('/', [WhatsAppTemplateController::class, 'index']);
    Route::post('/', [WhatsAppTemplateController::class, 'store']);
    Route::get('/{template}', [WhatsAppTemplateController::class, 'show']);
    Route::put('/{template}', [WhatsAppTemplateController::class, 'update']);
    Route::delete('/{template}', [WhatsAppTemplateController::class, 'destroy']);
    Route::post('/{template}/sync', [WhatsAppTemplateController::class, 'sync']);
});

// =========================================================================
// ANALYTICS
// =========================================================================

Route::get('/stats', [WhatsAppConversationController::class, 'stats']);

// =========================================================================
// WEBHOOK (Public - No Auth)
// =========================================================================

Route::withoutMiddleware(['auth:sanctum'])->group(function () {
    Route::get('/webhook', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/webhook', [WhatsAppWebhookController::class, 'handle']);
});
