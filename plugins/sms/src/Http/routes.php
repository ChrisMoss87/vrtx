<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\SMS\Http\Controllers\SMSConnectionController;
use Plugins\SMS\Http\Controllers\SMSMessageController;
use Plugins\SMS\Http\Controllers\SMSTemplateController;
use Plugins\SMS\Http\Controllers\SMSCampaignController;
use Plugins\SMS\Http\Controllers\SMSWebhookController;

/*
|--------------------------------------------------------------------------
| SMS Plugin API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the SMS plugin service provider.
| They are prefixed with 'api/v1/sms' and protected by the plugin license middleware.
|
*/

// =========================================================================
// CONNECTIONS
// =========================================================================

Route::prefix('connections')->group(function () {
    Route::get('/', [SMSConnectionController::class, 'index']);
    Route::post('/', [SMSConnectionController::class, 'store']);
    Route::get('/{connection}', [SMSConnectionController::class, 'show']);
    Route::put('/{connection}', [SMSConnectionController::class, 'update']);
    Route::delete('/{connection}', [SMSConnectionController::class, 'destroy']);
    Route::post('/{connection}/test', [SMSConnectionController::class, 'test']);
    Route::get('/{connection}/usage', [SMSConnectionController::class, 'usage']);
});

// =========================================================================
// MESSAGES
// =========================================================================

Route::prefix('messages')->group(function () {
    Route::get('/', [SMSMessageController::class, 'index']);
    Route::post('/', [SMSMessageController::class, 'send']);
    Route::get('/conversation/{phoneNumber}', [SMSMessageController::class, 'conversation']);
    Route::get('/{message}', [SMSMessageController::class, 'show']);
});

// =========================================================================
// TEMPLATES
// =========================================================================

Route::prefix('templates')->group(function () {
    Route::get('/', [SMSTemplateController::class, 'index']);
    Route::post('/', [SMSTemplateController::class, 'store']);
    Route::get('/{template}', [SMSTemplateController::class, 'show']);
    Route::put('/{template}', [SMSTemplateController::class, 'update']);
    Route::delete('/{template}', [SMSTemplateController::class, 'destroy']);
    Route::post('/{template}/preview', [SMSTemplateController::class, 'preview']);
});

// =========================================================================
// CAMPAIGNS
// =========================================================================

Route::prefix('campaigns')->group(function () {
    Route::get('/', [SMSCampaignController::class, 'index']);
    Route::post('/', [SMSCampaignController::class, 'store']);
    Route::get('/{campaign}', [SMSCampaignController::class, 'show']);
    Route::put('/{campaign}', [SMSCampaignController::class, 'update']);
    Route::post('/{campaign}/start', [SMSCampaignController::class, 'start']);
    Route::get('/{campaign}/stats', [SMSCampaignController::class, 'stats']);
});

// =========================================================================
// OPT-OUT
// =========================================================================

Route::prefix('opt-outs')->group(function () {
    Route::get('/', [SMSMessageController::class, 'listOptOuts']);
    Route::post('/', [SMSMessageController::class, 'recordOptOut']);
    Route::delete('/{phoneNumber}', [SMSMessageController::class, 'removeOptOut']);
    Route::get('/check/{phoneNumber}', [SMSMessageController::class, 'checkOptOut']);
});

// =========================================================================
// ANALYTICS
// =========================================================================

Route::get('/stats', [SMSMessageController::class, 'stats']);

// =========================================================================
// WEBHOOK (Public - No Auth)
// =========================================================================

Route::withoutMiddleware(['auth:sanctum'])->group(function () {
    Route::post('/webhook/twilio', [SMSWebhookController::class, 'twilio']);
    Route::post('/webhook/status', [SMSWebhookController::class, 'status']);
});
