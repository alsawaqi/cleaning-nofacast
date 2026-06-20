<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if (! $request->user()?->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('This account is inactive.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->redirectPathFor($request->user()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function redirectPathFor(?User $user): string
    {
        if (! $user) {
            return '/';
        }

        if ($user->hasPermission('access_back_office')) {
            return '/app/dashboard';
        }

        if ($user->hasPermission('access_worker_portal')) {
            return '/app/worker/today';
        }

        if ($user->hasPermission('access_customer_portal')) {
            return '/app/customer';
        }

        return '/';
    }
}
