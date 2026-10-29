<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
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
use App\Http\Controllers\MetaController;
use App\Http\Controllers\NoteStatusController;
use App\Http\Controllers\ObjectStatusController;
use App\Http\Controllers\ObjectTypeController;
use App\Http\Controllers\ObjectTypeFieldController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\RelinkObjectController;
use App\Http\Controllers\RoutingRuleController;
use App\Http\Controllers\RuleActionLogController;
use App\Http\Controllers\SmsCallbackController;
use App\Http\Controllers\SmsSettingsController;
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

// Payment gateway callbacks — no tenant middleware (payment found by gateway order id)
Route::prefix('payments/callbacks')->name('payments.callbacks.')->group(function () {
    Route::post('bog', [PaymentCallbackController::class, 'bog'])->name('bog');
    Route::post('flitt', [PaymentCallbackController::class, 'flitt'])->name('flitt');
});

// InexPhone SMS callbacks — no tenant middleware (message found by InexPhone's id)
Route::prefix('sms/callbacks')->name('sms.callbacks.')->group(function () {
    Route::post('submit', [SmsCallbackController::class, 'submit'])->name('submit');
    Route::post('delivery', [SmsCallbackController::class, 'delivery'])->name('delivery');
});

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

// Facebook Login for Business — landlord-level; tenant comes from the signed `state` / webhook page id
Route::get('/auth/facebook/callback', [MetaController::class, 'callback']);
Route::match(['get', 'post'], '/webhooks/facebook', [MetaController::class, 'webhook']);

// ─────────────────────────────────────────────────────────────────────────────
// Tenant-scoped routes — require a valid tenant (resolved from subdomain)
// ─────────────────────────────────────────────────────────────────────────────
// Remaining tenant-scoped routes
Route::middleware(['tenant'])->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('sms-settings', [SmsSettingsController::class, 'show']);
        Route::put('sms-settings', [SmsSettingsController::class, 'update']);
        Route::get('payment-settings', [PaymentSettingsController::class, 'show']);
        Route::put('payment-settings', [PaymentSettingsController::class, 'update']);
        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('payments', [PaymentController::class, 'store']);
    });
    // Several controllers name their model argument without underscores (e.g. $leadstatus),
    // so those resources use matching route parameter names for model binding to work.
    Route::apiResource('messages', MessageController::class);
    Route::apiResource('channels', ChannelController::class);
    Route::apiResource('contact-links', ContactLinkController::class);
    Route::apiResource('relink-objects', RelinkObjectController::class)->parameters(['relink-objects' => 'relinkobject']);
    Route::apiResource('lead-statuses', LeadStatusController::class)->parameters(['lead-statuses' => 'leadstatus']);
    Route::apiResource('note-statuses', NoteStatusController::class)->parameters(['note-statuses' => 'notestatus']);
    Route::apiResource('contact-link-groups', ContactLinkGroupController::class)->parameters(['contact-link-groups' => 'contactlinkgroup']);
    Route::apiResource('contacts', ContactController::class);
    Route::post('contact-statuses/bulk', [ContactStatusController::class, 'bulkStore']);
    Route::delete('contact-statuses/bulk', [ContactStatusController::class, 'bulkDelete']);
    Route::apiResource('contact-statuses', ContactStatusController::class)->parameters(['contact-statuses' => 'contactstatus']);
    Route::apiResource('conversations', ConversationController::class);
    Route::apiResource('object-types', ObjectTypeController::class)->parameters(['object-types' => 'objecttype']);
    Route::apiResource('object-statuses', ObjectStatusController::class)->parameters(['object-statuses' => 'objectstatus']);
    Route::apiResource('object-type-fields', ObjectTypeFieldController::class)->parameters(['object-type-fields' => 'objecttypefield']);
    Route::apiResource('conversation-objects', ConversationObjectController::class)->parameters(['conversation-objects' => 'conversationobject']);
    Route::apiResource('contact-notes', ContactNoteController::class);
    Route::apiResource('routing-rules', RoutingRuleController::class)->parameters(['routing-rules' => 'routingrule']);
    Route::apiResource('rule-action-logs', RuleActionLogController::class)->parameters(['rule-action-logs' => 'ruleactionlog']);
    Route::apiResource('chat-distributions', ChatDistributionController::class)->parameters(['chat-distributions' => 'chatdistribution']);
    Route::apiResource('bookings', BookingController::class);
    Route::get('tenant-memberships', [TenantMembershipController::class, 'index']);

    Route::middleware('auth:sanctum')->prefix('meta')->group(function () {
        Route::get('/connect-url', [MetaController::class, 'connectUrl']);
        Route::get('/connections', [MetaController::class, 'index']);
        Route::delete('/connections/{metaConnection}', [MetaController::class, 'destroy']);
    });

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
