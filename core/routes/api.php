<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::namespace('Api')->name('api.')->group(function () {

    // ── Client Integration API (legacy ClientApp style) ────────────────────
    Route::controller('ClientController')->prefix('client')->middleware('throttle:20,1')->group(function () {
        Route::post('player/token', 'playerToken')->name('client.player.token');
    });

    // ── Tenant Game Provider API  v1 ────────────────────────────────────────
    Route::prefix('v1')->name('v1.')->middleware(['throttle:60,1', \App\Modules\Tenant\TenantAuthMiddleware::class])->group(function () {
        Route::post('session/create', [\App\Modules\API\SessionController::class, 'create'])->name('session.create');
        // Legacy/doc compatibility endpoint
        Route::post('game/session', [\App\Modules\API\SessionController::class, 'create'])->name('game.session');
        
        // Modular Game Engine Routes
        Route::get('game/{alias}/start', [\App\Modules\API\EngineController::class, 'start'])->name('game.start');
        Route::post('game/{alias}/play', [\App\Modules\API\EngineController::class, 'play'])->name('game.play');
    });

    Route::controller('AppController')->group(function () {
        Route::get('general-setting', 'generalSetting');
        Route::get('get-countries', 'getCountries');
        Route::get('language/{key?}', 'getLanguage');
        Route::get('policies', 'policies');
        Route::get('policy/{slug}', 'policyContent');
        Route::get('faq', 'faq');
        Route::get('seo', 'seo');
        Route::get('get-extension/{act}', 'getExtension');
        Route::post('contact', 'submitContact')->middleware('throttle:10,1');
        Route::get('cookie', 'cookie');
        Route::post('cookie/accept', 'cookieAccept');
        Route::get('custom-pages', 'customPages');
        Route::get('custom-page/{slug}', 'customPageData');
        Route::get('sections', 'allSections');
        Route::get('ticket/{ticket}', 'viewTicket');
        Route::post('ticket/ticket-reply/{id}', 'replyTicket')->middleware('throttle:15,1');
    });

    Route::namespace('Auth')->middleware('throttle:20,1')->group(function () {
        Route::controller('LoginController')->group(function () {
            Route::post('login', 'login')->middleware('throttle:8,1');
            Route::post('check-token', 'checkToken')->middleware('throttle:10,1');
            Route::post('social-login', 'socialLogin')->middleware('throttle:10,1');
        });
        Route::post('register', 'RegisterController@register')->middleware('throttle:6,1');
        Route::controller('ForgotPasswordController')->group(function () {
            Route::post('password/email', 'sendResetCodeEmail')->middleware('throttle:5,1');
            Route::post('password/verify-code', 'verifyCode')->middleware('throttle:10,1');
            Route::post('password/reset', 'reset')->middleware('throttle:5,1');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('user-data-submit', 'UserController@userDataSubmit');
        //authorization
        Route::middleware('registration.complete')->controller('AuthorizationController')->group(function () {
            Route::get('authorization', 'authorization');
            Route::get('resend-verify/{type}', 'sendVerifyCode');
            Route::post('verify-email', 'emailVerification');
            Route::post('verify-mobile', 'mobileVerification');
            Route::post('verify-g2fa', 'g2faVerification');
        });

        Route::middleware(['check.status'])->group(function () {
            Route::middleware('registration.complete')->group(function () {
                Route::controller('UserController')->group(function () {
                    Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
                    Route::get('user/dashboard', 'dashboard');
                    Route::post('profile-setting', 'submitProfile');
                    Route::post('change-password', 'submitPassword');

                    Route::get('user-info', 'userInfo');
                    //KYC
                    Route::get('kyc-form', 'kycForm');
                    Route::get('kyc-data', 'kycData');
                    Route::post('kyc-submit', 'kycSubmit');

                    //Report
                    Route::get('deposit/history', 'depositHistory');
                    Route::get('transactions', 'transactions');

                    Route::post('add-device-token', 'addDeviceToken');
                    Route::get('push-notifications', 'pushNotifications');
                    Route::post('push-notifications/read/{id}', 'pushNotificationsRead');

                    //2FA
                    Route::get('twofactor', 'show2faForm');
                    Route::post('twofactor/enable', 'create2fa');
                    Route::post('twofactor/disable', 'disable2fa');

                    Route::post('delete-account', 'deleteAccount');

                    Route::get('user/referral', 'referrals');
                    Route::get('user/game/log', 'gameLog');
                    Route::get('user/demo-game/log', 'demoGameLog');
                });

                // Withdraw / Payment / Balance Transfer routes removed — SaaS uses external WalletBridge.

                Route::controller('TicketController')->prefix('ticket')->group(function () {
                    Route::get('/', 'supportTicket');
                    Route::post('create', 'storeSupportTicket');
                    Route::get('view/{ticket}', 'viewTicket');
                    Route::post('reply/{id}', 'replyTicket');
                    Route::post('close/{id}', 'closeTicket');
                    Route::get('download/{attachment_id}', 'ticketDownload');
                });

                // Game security: issue action token before placing a bet
                Route::post('game/action-token', [\App\Http\Controllers\User\GameSecurityController::class, 'issueToken'])
                    ->middleware('throttle:30,1');

                Route::controller('PlayController')->prefix('play')->group(function () {
                    Route::get('{alias}/{demo?}', 'playGame');
                    Route::post('invest/{alias}/{demo?}', 'investGame')
                        ->middleware('game.validate');
                    Route::post('end/{alias}/{demo?}', 'gameEnd');
                });


            });
        });

        Route::get('logout', 'Auth\LoginController@logout');
    });
});
