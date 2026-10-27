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
        // 1. Admin Account from database
        $adminAccount = User::where('is_admin', true)
            ->orWhere('role', 'admin')
            ->with('project:id,name')
            ->select('id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
            ->first();

        // 2. Team Lead Account from database
        $teamLeadAccount = User::where('is_admin', false)
            ->where('is_customer', false)
            ->where('is_team_lead', true)
            ->with('project:id,name')
            ->select('id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
            ->first();

        // 3. Employee Account from database (regular employee or staff)
        $employeeAccount = User::where('is_admin', false)
            ->where('is_customer', false)
            ->where('is_team_lead', false)
            ->where(function ($q) {
                $q->where('role', '!=', 'customer')
                  ->orWhereNull('role');
            })
            ->with('project:id,name')
            ->select('id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
            ->first()
            ?? User::where('is_admin', false)
                ->where('is_customer', false)
                ->with('project:id,name')
                ->select('id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
                ->first();

        // 4. Client Account from database
        $clientAccount = User::where(function ($q) {
                $q->where('is_customer', true)
                  ->orWhere('role', 'customer');
            })
            ->with('project:id,name')
            ->select('id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
            ->first();

        // All users for reference
        $demoUsers = User::with('project:id,name')
            ->select('id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_team_lead', 'project_id', 'member_id')
            ->get();

        return Inertia::render('Auth/Login', [
            'adminAccount' => $adminAccount,
            'teamLeadAccount' => $teamLeadAccount,
            'employeeAccount' => $employeeAccount,
            'clientAccount' => $clientAccount,
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
