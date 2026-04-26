<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('tenant_panel_id')) {
            return redirect()->route('tenant.dashboard');
        }
        return view('tenant.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $tenant = Tenant::where('email', $request->email)->where('status', 1)->first();

        if (!$tenant || !$tenant->password || !Hash::check($request->password, $tenant->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        $request->session()->regenerate();
        session(['tenant_panel_id' => $tenant->id]);

        return redirect()->route('tenant.dashboard');
    }

    public function logout(Request $request)
    {
        session()->forget('tenant_panel_id');
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('tenant.login');
    }
}
