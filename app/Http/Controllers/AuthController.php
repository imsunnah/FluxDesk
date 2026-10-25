<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Display simple login screen.
     */
    public function showLogin(): Response
    {
        $demoUsers = User::with('project:id,name')
            ->select('id', 'name', 'email', 'avatar', 'role', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
            ->get();

        return Inertia::render('Auth/Login', [
            'demoUsers' => $demoUsers,
        ]);
    }

    /**
     * Process login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Instant 1-click user switcher for local demo / testing.
     */
    public function quickLogin(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
