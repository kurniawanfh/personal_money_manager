<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\LeakDetectorController;
use App\Http\Controllers\Api\PlannedExpenseController;
use App\Http\Controllers\Api\PrivacyController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\UserSettingController;
use App\Http\Controllers\Api\VoiceParserController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\Webhook\PaymentGatewayWebhookController;
use App\Http\Controllers\Api\Webhook\RevenueCatWebhookController;
use Illuminate\Support\Facades\Route;

// Fallback login route for unauthenticated middleware redirects
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Unauthenticated.',
    ], 401);
})->name('login');

// Public Auth Endpoints
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Public Monetization Webhooks
Route::prefix('webhooks')->group(function () {
    Route::post('revenuecat', [RevenueCatWebhookController::class, 'handle']);
    Route::post('payment-gateway', [PaymentGatewayWebhookController::class, 'handle']);
});

// Authenticated Routes
Route::middleware('auth:sanctum')->group(function () {
    // Session & Profile
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    // Wallets & Transfers
    Route::post('wallets/transfer', [WalletController::class, 'transfer']);
    Route::post('transfers', [TransferController::class, 'store']);
    Route::apiResource('wallets', WalletController::class);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Transactions (Financial Ledger)
    Route::get('transactions/summary', [TransactionController::class, 'summary']);
    Route::apiResource('transactions', TransactionController::class);

    // Subscriptions & Recurring
    Route::post('subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);
    Route::post('subscriptions/{id}/resume', [SubscriptionController::class, 'resume']);
    Route::apiResource('subscriptions', SubscriptionController::class);

    // Planned Expenses
    Route::post('planned-expenses/{id}/confirm', [PlannedExpenseController::class, 'confirm']);
    Route::post('planned-expenses/{id}/skip', [PlannedExpenseController::class, 'skip']);
    Route::apiResource('planned-expenses', PlannedExpenseController::class)->only(['index', 'show']);

    // Voice NLP & Quota Engine
    Route::post('voice/parse', [VoiceParserController::class, 'parse']);
    Route::get('voice/quota', [VoiceParserController::class, 'quota']);

    // Server-Authoritative Sync Engine
    Route::post('sync/batch', [SyncController::class, 'batch']);
    Route::get('sync/pull', [SyncController::class, 'pull']);

    // Potential Money Leak Detector
    Route::get('analytics/leaks', [LeakDetectorController::class, 'index']);

    // User Settings & Leak Thresholds
    Route::get('settings', [UserSettingController::class, 'show']);
    Route::match(['put', 'patch'], 'settings', [UserSettingController::class, 'update']);

    // Privacy, Export & GDPR Hard Deletion
    Route::get('privacy/export-csv', [PrivacyController::class, 'exportCsv']);
    Route::get('privacy/export-json', [PrivacyController::class, 'exportJson']);
    Route::delete('privacy/account', [PrivacyController::class, 'deleteAccount']);

    // Device Tokens & Notifications
    Route::post('devices/token', [DeviceTokenController::class, 'store']);
    Route::delete('devices/token', [DeviceTokenController::class, 'destroy']);
});
