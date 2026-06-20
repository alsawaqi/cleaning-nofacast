<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredCustomerController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'customer_type' => ['required', Rule::in(['individual', 'company'])],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'preferred_locale' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'customer',
                'locale' => $validated['preferred_locale'],
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_type' => $validated['customer_type'],
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'preferred_channel' => 'whatsapp',
                'preferred_locale' => $validated['preferred_locale'],
                'status' => 'active',
            ]);

            CustomerSite::create([
                'customer_id' => $customer->id,
                'country_code' => 'SA',
                'name' => 'Primary site',
                'city' => $validated['city'],
                'district' => $validated['district'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_default' => true,
                'contact_name' => $validated['name'],
                'contact_phone' => $validated['phone'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/app/customer');
    }
}
