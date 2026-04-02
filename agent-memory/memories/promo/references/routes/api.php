<?php

use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\EnergyController;
use App\Http\Controllers\Api\GameplayController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\RewardController;
use App\Http\Controllers\Api\SliderController;
use App\Services\ApiResponse;
use Illuminate\Support\Facades\Route;

// Public routes (throttled)
Route::middleware('throttle:20,1')->group(function () {
    Route::get('campaigns/configuration', [MemberController::class, 'getCampaignConfiguration']);
    // Route::get('members/consent', [MemberController::class, 'checkConsent']);
    Route::post('members/check', [MemberController::class, 'checkMember']);
    Route::post('members/register', [MemberController::class, 'register']);
    Route::prefix('pages')->group(function () {
        Route::get('/', [PageController::class, 'index']);
        Route::get('/{slug}', [PageController::class, 'show']);
    });
});

// Authenticated routes
Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    Route::get('members/profile', [MemberController::class, 'getMember']);
});

// Authenticated + suspended check
Route::middleware(['auth:sanctum', 'suspend.check', 'throttle:60,1'])->group(function () {

    // Member routes
    Route::prefix('members')->group(function () {
        // Inbox
        Route::get('inbox', [MemberController::class, 'inbox']);
        Route::put('inbox/{id}/read', [MemberController::class, 'updateReadInbox']);
        Route::post('inbox/read-all', [MemberController::class, 'readAllInbox']);

        // OTP (stricter rate limit)
        Route::middleware('throttle:5,1')->group(function () {
            Route::post('otp/send', [MemberController::class, 'sendOtp']);
            Route::post('otp/verify', [MemberController::class, 'verifyOtp']);
        });
    });

    // Checkin Routes
    Route::prefix('checkins')->group(function () {
        Route::get('/', [CheckinController::class, 'index']);
        Route::post('/claim', [CheckinController::class, 'claim']);
    });

    // Energy Routes
    Route::prefix('energies')->group(function () {
        Route::get('/', [EnergyController::class, 'index']);
        Route::post('/buy', [EnergyController::class, 'buy']);
    });

    // Promos
    Route::prefix('promos')->group(function () {
        Route::get('/', [PromoController::class, 'getPromo']);
    });

    // Sliders
    // Route::prefix('sliders')->group(function () {
    //     Route::get('/', [SliderController::class, 'getSliders']);
    //     Route::get('{slider}', [SliderController::class, 'show']);
    // });

    // Rewards
    Route::prefix('rewards')->group(function () {
        Route::get('/checkin', [RewardController::class, 'getCheckinRewards']);
        Route::get('/', [RewardController::class, 'myRewards']);
        Route::post('{uid}/claim', [RewardController::class, 'claimRewardMember']);
    });

    // Gameplay Routes
    Route::prefix('customers')->group(function () {
        Route::post('/served', [GameplayController::class, 'serveCustomer']);
        Route::post('/{key}/submit', [GameplayController::class, 'submitGameplay']);
    });

    // Mistery Box Routes
    Route::post('mystery-boxes/claim', [PromoController::class, 'claimMysteryBox']);

});

// Root
Route::get('/', function () {
    return ApiResponse::json([
        'message' => 'Welcome to the API',
        'app_env' => config('app.env'),
    ]);
});
