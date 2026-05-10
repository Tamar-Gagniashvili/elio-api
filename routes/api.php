<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChatDistributionController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactLinkController;
use App\Http\Controllers\ContactLinkGroupController;
use App\Http\Controllers\ContactNoteController;
use App\Http\Controllers\ContactStatusController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationObjectController;
use App\Http\Controllers\EdgeFunctionController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NoteStatusController;
use App\Http\Controllers\ObjectStatusController;
use App\Http\Controllers\ObjectTypeController;
use App\Http\Controllers\ObjectTypeFieldController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RelinkObjectController;
use App\Http\Controllers\RoutingRuleController;
use App\Http\Controllers\RuleActionLogController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantMembershipController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────────────────────────────────────
// Landlord routes — no tenant context required
// ─────────────────────────────────────────────────────────────────────────────
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Tenant CRUD, user profiles, and memberships are landlord-level
Route::apiResource('tenants', TenantController::class);
Route::apiResource('profiles', ProfileController::class);
Route::apiResource('memberships', MembershipController::class);
Route::apiResource('user-roles', UserRoleController::class);
Route::apiResource('push-subscriptions', PushSubscriptionController::class);

// Webhooks — no tenant middleware (resolved internally from payload/query param)
Route::group(['prefix' => 'functions'], function () {
    Route::post('/webchat', [EdgeFunctionController::class, 'webchat']);
    Route::match(['get', 'post'], '/webhook-instagram', [EdgeFunctionController::class, 'webhookInstagram']);
    Route::match(['get', 'post'], '/webhook-messenger', [EdgeFunctionController::class, 'webhookMessenger']);
    Route::post('/webhook-telegram', [EdgeFunctionController::class, 'webhookTelegram']);
    Route::match(['get', 'post'], '/webhook-whatsapp', [EdgeFunctionController::class, 'webhookWhatsapp']);
    Route::post('/upload-chat-attachment', [EdgeFunctionController::class, 'uploadChatAttachment']);
    Route::post('/create-user', [EdgeFunctionController::class, 'createUser']);
});

// ─────────────────────────────────────────────────────────────────────────────
// Tenant-scoped routes — require a valid tenant (resolved from subdomain)
// ─────────────────────────────────────────────────────────────────────────────
// Remaining tenant-scoped routes
Route::middleware(['tenant'])->group(function () {
    Route::apiResource('messages', MessageController::class);
    Route::apiResource('channels', ChannelController::class);
    Route::apiResource('contact-links', ContactLinkController::class);
    Route::apiResource('relink-objects', RelinkObjectController::class);
    Route::apiResource('lead-statuses', LeadStatusController::class);
    Route::apiResource('note-statuses', NoteStatusController::class);
    Route::apiResource('contact-link-groups', ContactLinkGroupController::class);
    Route::apiResource('contacts', ContactController::class);
    Route::post('contact-statuses/bulk', [ContactStatusController::class, 'bulkStore']);
    Route::delete('contact-statuses/bulk', [ContactStatusController::class, 'bulkDelete']);
    Route::apiResource('contact-statuses', ContactStatusController::class);
    Route::apiResource('conversations', ConversationController::class);
    Route::apiResource('object-types', ObjectTypeController::class);
    Route::apiResource('object-statuses', ObjectStatusController::class);
    Route::apiResource('object-type-fields', ObjectTypeFieldController::class);
    Route::apiResource('conversation-objects', ConversationObjectController::class);
    Route::apiResource('contact-notes', ContactNoteController::class);
    Route::apiResource('routing-rules', RoutingRuleController::class);
    Route::apiResource('rule-action-logs', RuleActionLogController::class);
    Route::apiResource('chat-distributions', ChatDistributionController::class);
    Route::get('tenant-memberships', [TenantMembershipController::class, 'index']);

    // Tenant-aware edge function proxies
    Route::prefix('functions')->group(function () {
        Route::post('/encrypt-channel-config', [EdgeFunctionController::class, 'encryptChannelConfig']);
        Route::post('/import-leads', [EdgeFunctionController::class, 'importLeads']);
        Route::post('/import-objects', [EdgeFunctionController::class, 'importObjects']);
        Route::post('/send-messenger', [EdgeFunctionController::class, 'sendMessenger']);
        Route::post('/send-push-notification', [EdgeFunctionController::class, 'sendPushNotification']);
        Route::post('/send-telegram', [EdgeFunctionController::class, 'sendTelegram']);
    });
});
