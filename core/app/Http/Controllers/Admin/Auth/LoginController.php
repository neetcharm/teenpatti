<?php
namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
     */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    public $redirectTo = 'admin';

    /**
     * Show the application's login form.
     *
     * @return \Illuminate\Http\Response
     */
    public function showLoginForm() {
        $pageTitle = "Admin Login";
        return view('admin.auth.login', compact('pageTitle'));
    }

    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard() {
        return auth()->guard('admin');
    }

    public function username() {
        return 'username';
    }

    public function login(Request $request) {
        try {
            $this->validateLogin($request);
            $request->session()->regenerateToken();

            if (!verifyCaptcha()) {
                $notify[] = ['error', 'Invalid captcha provided'];
                return back()->withNotify($notify);
            }

            if (method_exists($this, 'hasTooManyLoginAttempts') &&
                $this->hasTooManyLoginAttempts($request)) {
                $this->fireLockoutEvent($request);
                return $this->sendLockoutResponse($request);
            }

            if ($this->attemptLogin($request)) {
                return $this->sendLoginResponse($request);
            }

            $this->incrementLoginAttempts($request);
            return $this->sendFailedLoginResponse($request);
        } catch (\Throwable $e) {
            \Log::error('LOGIN_ERROR: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            $notify[] = ['error', 'Internal Error: ' . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    public function logout(Request $request) {
        $this->guard('admin')->logout();
        $request->session()->invalidate();
        return $this->loggedOut($request) ?: redirect($this->redirectTo);
    }
}
