<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\CoRuleController;
use App\Http\Controllers\Api\CreditController;
use App\Http\Controllers\Api\DuesController;
use App\Http\Controllers\Api\EventBadgeController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PodiumController;
use App\Http\Controllers\Api\RankController;
use App\Http\Controllers\Api\RedemptionController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

// Publicas a propósito: <img src> no puede enviar la cabecera Authorization.
// Solo exponen la imagen, nunca nicks, saldos ni precios.
Route::get('/members/{member}/avatar', [MemberController::class, 'avatar'])
    ->name('members.avatar');
Route::get('/rewards/{reward}/image', [RewardController::class, 'image'])
    ->name('rewards.image');
Route::get('/ranks/{rank}/icon', [RankController::class, 'icon'])
    ->name('ranks.icon');
Route::get('/events/{event}/badge/{position}', [EventBadgeController::class, 'show'])
    ->name('events.badge');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Solo lectura: admin y jugador.
    Route::get('/settings', [SettingController::class, 'index']);
    Route::get('/bank', [BankController::class, 'index']);
    Route::get('/ranks', [RankController::class, 'index']);
    Route::get('/co-rules', [CoRuleController::class, 'index']);

    Route::get('/members', [MemberController::class, 'index']);
    Route::get('/members/{member}', [MemberController::class, 'show']);
    Route::get('/members/{member}/results', [MemberController::class, 'results']);
    Route::get('/members/{member}/credits', [CreditController::class, 'history']);
    Route::get('/members/{member}/redemptions', [RedemptionController::class, 'forMember']);

    Route::get('/events', [EventController::class, 'index']);
    Route::get('/events/{event}', [EventController::class, 'show']);
    Route::get('/credits/recent', [CreditController::class, 'recent']);

    Route::get('/rewards', [RewardController::class, 'index']);
    Route::get('/rewards/{reward}', [RewardController::class, 'show']);
    Route::get('/redemptions', [RedemptionController::class, 'index']);
    Route::get('/podium', [PodiumController::class, 'index']);
    Route::get('/dues', [DuesController::class, 'index']);

    // Escritura: solo admin.
    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function () {
        Route::put('/settings', [SettingController::class, 'update']);

        Route::post('/bank', [BankController::class, 'store']);
        Route::delete('/bank/{bankMovement}', [BankController::class, 'destroy']);

        Route::post('/members', [MemberController::class, 'store']);
        Route::put('/members/{member}', [MemberController::class, 'update']);
        Route::delete('/members/{member}', [MemberController::class, 'destroy']);
        Route::post('/members/{member}/avatar', [MemberController::class, 'uploadAvatar']);
        Route::delete('/members/{member}/avatar', [MemberController::class, 'deleteAvatar']);

        Route::post('/members/{member}/credits', [CreditController::class, 'adjust']);

        Route::post('/events/suggest-co', [EventController::class, 'suggestCo']);
        Route::post('/events', [EventController::class, 'store']);
        Route::put('/events/{event}', [EventController::class, 'update']);
        Route::delete('/events/{event}', [EventController::class, 'destroy']);

        Route::post('/dues', [DuesController::class, 'store']);
        Route::delete('/dues', [DuesController::class, 'destroy']);
        Route::put('/dues/amount', [DuesController::class, 'updateAmount']);

        Route::post('/events/{event}/badge', [EventBadgeController::class, 'upload']);
        Route::delete('/events/{event}/badge/{position}', [EventBadgeController::class, 'destroy']);

        Route::post('/co-rules', [CoRuleController::class, 'store']);
        Route::put('/co-rules/{coRule}', [CoRuleController::class, 'update']);
        Route::delete('/co-rules/{coRule}', [CoRuleController::class, 'destroy']);

        Route::post('/rewards', [RewardController::class, 'store']);
        Route::put('/rewards/{reward}', [RewardController::class, 'update']);
        Route::delete('/rewards/{reward}', [RewardController::class, 'destroy']);
        Route::post('/rewards/{reward}/image', [RewardController::class, 'uploadImage']);
        Route::delete('/rewards/{reward}/image', [RewardController::class, 'deleteImage']);

        Route::put('/ranks/{rank}', [RankController::class, 'update']);
        Route::post('/ranks/{rank}/icon', [RankController::class, 'uploadIcon']);
        Route::delete('/ranks/{rank}/icon', [RankController::class, 'deleteIcon']);

        Route::post('/redemptions', [RedemptionController::class, 'store']);
        Route::put('/redemptions/{redemption}', [RedemptionController::class, 'updateStatus']);
        Route::post('/redemptions/{redemption}/cancel', [RedemptionController::class, 'cancel']);
    });
});
