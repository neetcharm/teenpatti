<?php

use Illuminate\Support\Facades\Route;

Route::get('cron', 'CronController@cron')->name('cron');

// ── Integration Documentation (admin-protected) ───────────────────────────────
Route::middleware('admin')->get('admin/docs/integration', function () {
    return view('admin.docs.integration', ['pageTitle' => 'Integration Documentation']);
})->name('admin.docs.integration');

// ── Tenant Panel ─────────────────────────────────────────────────────────────
Route::prefix('tenant')->name('tenant.')->namespace('Tenant')->group(function () {
    // Guest
    Route::get('login',  'AuthController@showLogin')->name('login');
    Route::post('login', 'AuthController@login')->name('login.submit');

    // Authenticated
    Route::middleware('tenant.auth')->group(function () {
        Route::post('logout', 'AuthController@logout')->name('logout');

        Route::get('dashboard',                         'DashboardController@index')->name('dashboard');
        Route::get('players',                           'PlayerController@index')->name('players.index');
        Route::post('players/{userId}/topup',           'PlayerController@topup')->name('players.topup');
        Route::post('players/{userId}/deduct',          'PlayerController@deduct')->name('players.deduct');
        Route::get('players/{userId}/history',          'PlayerController@history')->name('players.history');
        Route::get('sessions',                          'SessionController@index')->name('sessions.index');
        Route::get('transactions',                      'TransactionController@index')->name('transactions.index');
        Route::get('settings',                          'SettingsController@index')->name('settings');
        Route::post('settings',                         'SettingsController@update')->name('settings.update');
        Route::post('launch/teen-patti',               'SettingsController@launchTeenPatti')->name('launch.teen_patti');
    });
});

// ── Teen Patti WebView (legacy ClientApp flow) ───────────────────────────────
Route::get('/tp/{token}', 'TeenPattiWebViewController@serve')->name('tp.webview');

// ── Tenant Game Provider – WebView Launch ────────────────────────────────────
// Tenant's Android / Web app opens this URL in a WebView.
// {sessionToken} is passed as ?token=.
// No auth middleware – controller validates session and starts web session.
Route::get('/play', [\App\Modules\GameEngine\GameLaunchController::class, 'serve'])->name('game.launch');
// Legacy/doc compatibility launch route
Route::get('/launch/{sessionToken}', function (string $sessionToken) {
    return redirect()->route('game.launch', ['token' => $sessionToken]);
})->name('game.launch.legacy');


// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{id}', 'replyTicket')->name('reply');
    Route::post('close/{id}', 'closeTicket')->name('close');
    Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
});

// Route for 'app/deposit/confirm' removed — internal wallet gateway purged in SaaS refactor.

Route::controller('SiteController')->group(function () {
    Route::get('/pwa/configuration', 'pwaConfiguration')->name('pwa.configuration');
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'contactSubmit');
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');
    Route::post('/subscribe', 'subscribe')->name('subscribe.post');
    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');
    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');
    Route::get('games', 'games')->name('games');
    Route::get('live-games', 'liveGames')->name('live.games');
    Route::get('live-stats/{alias?}', 'liveStats')->name('live.stats');
    Route::get('blog', 'blog')->name('blog');
    Route::get('blog/{slug}', 'blogDetails')->name('blog.details');
    Route::get('policy/{slug}', 'policyPages')->name('policy.pages');
    Route::get('placeholder-image/{size}', 'placeholderImage')->withoutMiddleware('maintenance')->name('placeholder.image');
    Route::get('maintenance-mode', 'maintenance')->withoutMiddleware('maintenance')->name('maintenance');
    Route::get('/{slug}', 'pages')->name('pages');
    Route::get('/', 'index')->name('home');
});
