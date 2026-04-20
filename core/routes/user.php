<?php

use Illuminate\Support\Facades\Route;

Route::namespace('User\Auth')->name('user.')->middleware('guest')->group(function () {
    Route::controller('LoginController')->group(function () {
        Route::get('/login', 'showLoginForm')->name('login');
        Route::post('/login', 'login')->middleware('throttle:8,1');
        Route::get('logout', 'logout')->middleware('auth')->withoutMiddleware('guest')->name('logout');
    });

    Route::controller('RegisterController')->group(function () {
        Route::get('register', 'showRegistrationForm')->name('register');
        Route::post('register', 'register')->middleware('throttle:6,1');
        Route::post('check-user', 'checkUser')->name('checkUser')->withoutMiddleware('guest')->middleware('throttle:20,1');
    });

    Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->group(function () {
        Route::get('reset', 'showLinkRequestForm')->name('request');
        Route::post('email', 'sendResetCodeEmail')->name('email')->middleware('throttle:5,1');
        Route::get('code-verify', 'codeVerify')->name('code.verify');
        Route::post('verify-code', 'verifyCode')->name('verify.code')->middleware('throttle:10,1');
    });

    Route::controller('ResetPasswordController')->group(function () {
        Route::post('password/reset', 'reset')->name('password.update')->middleware('throttle:5,1');
        Route::get('password/reset/{token}', 'showResetForm')->name('password.reset');
    });

    Route::controller('SocialiteController')->group(function () {
        Route::get('social-login/{provider}', 'socialLogin')->name('social.login');
        Route::get('social-login/callback/{provider}', 'callback')->name('social.login.callback');
    });
});

Route::middleware('auth')->name('user.')->group(function () {
    Route::get('user-data', 'User\UserController@userData')->name('data');
    Route::post('user-data-submit', 'User\UserController@userDataSubmit')->name('data.submit');

    //authorization
    Route::middleware('registration.complete')->namespace('User')->controller('AuthorizationController')->group(function () {
        Route::get('authorization', 'authorizeForm')->name('authorization');
        Route::get('resend-verify/{type}', 'sendVerifyCode')->name('send.verify.code');
        Route::post('verify-email', 'emailVerification')->name('verify.email');
        Route::post('verify-mobile', 'mobileVerification')->name('verify.mobile');
        Route::post('verify-g2fa', 'g2faVerification')->name('2fa.verify');
    });

    Route::middleware(['check.status', 'registration.complete'])->group(function () {
        Route::namespace('User')->group(function () {
            Route::controller('UserController')->group(function () {
                Route::get('dashboard', 'home')->name('home');
                Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');

                //2FA
                Route::get('twofactor', 'show2faForm')->name('twofactor');
                Route::post('twofactor/enable', 'create2fa')->name('twofactor.enable');
                Route::post('twofactor/disable', 'disable2fa')->name('twofactor.disable');

                //KYC
                Route::get('kyc-form', 'kycForm')->name('kyc.form');
                Route::get('kyc-data', 'kycData')->name('kyc.data');
                Route::post('kyc-submit', 'kycSubmit')->name('kyc.submit');

                //Report
                Route::get('deposit/history', 'depositHistory')->name('deposit.history');
                Route::get('transactions', 'transactions')->name('transactions');
                Route::get('referral', 'referrals')->name('referrals');

                Route::post('add-device-token', 'addDeviceToken')->name('add.device.token');

                Route::get('game/log', 'gameLog')->name('game.log');
                Route::get('game/demo-log', 'demoGameLog')->name('game.log.demo');
                Route::get('commission/log', 'commissionLog')->name('commission.log');
            });

            //Profile setting
            Route::controller('ProfileController')->group(function () {
                Route::get('profile-setting', 'profile')->name('profile.setting');
                Route::post('profile-setting', 'submitProfile');
                Route::get('change-password', 'changePassword')->name('change.password');
                Route::post('change-password', 'submitPassword');
            });

            // Balance Transfer / Deposit / Withdraw routes removed — SaaS uses external WalletBridge.
            // Keep legacy route names registered to prevent RouteNotFoundException in old templates/controllers.
            $walletBridgeDisabledHandler = static function () {
                return to_route('user.home')->withNotify([
                    ['warning', 'WalletBridge mode is enabled. Wallet actions are handled by your provider app.'],
                ]);
            };

            Route::match(['GET', 'POST'], 'walletbridge-disabled/deposit/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('deposit.index');
            Route::post('walletbridge-disabled/deposit/insert/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('deposit.insert');
            Route::post('walletbridge-disabled/deposit/manual/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('deposit.manual.update');

            Route::match(['GET', 'POST'], 'walletbridge-disabled/withdraw/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('withdraw');
            Route::post('walletbridge-disabled/withdraw/money/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('withdraw.money');
            Route::match(['GET', 'POST'], 'walletbridge-disabled/withdraw/preview/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('withdraw.preview');
            Route::post('walletbridge-disabled/withdraw/submit/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('withdraw.submit');
            Route::match(['GET', 'POST'], 'walletbridge-disabled/withdraw/history/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('withdraw.history');

            Route::match(['GET', 'POST'], 'walletbridge-disabled/transfer/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('transfer.index');
            Route::post('walletbridge-disabled/transfer/store/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('transfer.store');
            Route::match(['GET', 'POST'], 'walletbridge-disabled/transfer/validate/{legacy?}', $walletBridgeDisabledHandler)
                ->where('legacy', '.*')
                ->name('transfer.validate.username');

            Route::controller('PlayController')->prefix('play')->name('play.')->group(function () {
                Route::get('tenant/wallet/refresh', 'tenantWalletRefresh')->name('tenant.wallet.refresh');
                Route::get('teen-patti/global/sync/{demo?}', 'teenPattiGlobalSync')->name('teen_patti.global.sync');
                Route::get('teen-patti/history/{demo?}', 'teenPattiHistory')->name('teen_patti.history');
                Route::get('{alias}/{demo?}', 'playGame')->name('game');
                Route::post('invest/{alias}/{demo?}', 'investGame')->name('invest');
                Route::post('end/{alias}/{demo?}', 'gameEnd')->name('end');
            });
        });

        // Payment/Deposit gateway routes removed — SaaS uses external WalletBridge.
    });
});
