<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Constants\Status;
use App\Models\Admin;
use App\Models\AdminPasswordReset;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ResetPasswordController extends Controller
{
    protected int $resetCodeExpireMinutes = 15;

    public function showResetForm(Request $request, $token)
    {
        $pageTitle = "Account Recovery";
        $resetToken = AdminPasswordReset::where('token', $token)->where('status', Status::ENABLE)->first();

        if (!$resetToken || $this->isCodeExpired($resetToken->created_at)) {
            $notify[] = ['error', 'Verification code mismatch'];
            return to_route('admin.password.reset')->withNotify($notify);
        }
        $email = $resetToken->email;
        return view('admin.auth.passwords.reset', compact('pageTitle', 'email', 'token'));
    }


    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|confirmed|min:4',
        ]);

        $reset = AdminPasswordReset::where('token', $request->token)
            ->where('email', $request->email)
            ->where('status', Status::ENABLE)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$reset) {
            $notify[] = ['error', 'Invalid code'];
            return to_route('admin.login')->withNotify($notify);
        }

        if ($this->isCodeExpired($reset->created_at)) {
            AdminPasswordReset::where('email', $request->email)->update(['status' => Status::DISABLE]);
            $notify[] = ['error', 'Verification code expired'];
            return to_route('admin.password.reset')->withNotify($notify);
        }

        $admin = Admin::where('email', $reset->email)->first();
        if (!$admin) {
            $notify[] = ['error', 'Admin account not found'];
            return to_route('admin.login')->withNotify($notify);
        }

        $admin->password = Hash::make($request->password);
        $admin->save();
        AdminPasswordReset::where('email', $admin->email)->update(['status' => Status::DISABLE]);

        $browser = osBrowser();
        notify($admin, 'PASS_RESET_DONE', [
            'operating_system' => isset($browser['os_platform']) ? $browser['os_platform'] : '',
            'browser' => isset($browser['browser']) ? $browser['browser'] : '',
            'ip' => getRealIp(),
            'time' => date('Y-m-d h:i:s A')
        ],['email'],false);

        $notify[] = ['success', 'Password changed'];
        return to_route('admin.login')->withNotify($notify);
    }

    private function isCodeExpired($createdAt): bool
    {
        if (!$createdAt) {
            return true;
        }

        return Carbon::parse($createdAt)->addMinutes($this->resetCodeExpireMinutes)->isPast();
    }
}
