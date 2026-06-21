<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerSite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        if ($request->filled('email')) {
            $request->merge([
                'email' => Str::lower($request->string('email')->trim()->toString()),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'customer_type' => ['required', Rule::in(['individual', 'company'])],
            'city' => ['required', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'preferred_locale' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $existingCustomer = Customer::query()
                ->whereRaw('LOWER(email) = ?', [$validated['email']])
                ->lockForUpdate()
                ->first();

            if ($existingCustomer?->user_id) {
                throw ValidationException::withMessages([
                    'email' => __('A customer account already exists for this email. Please login instead.'),
                ]);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
                'role' => 'customer',
                'locale' => $validated['preferred_locale'],
                'is_active' => true,
            ]);

            $customer = $existingCustomer ?? new Customer;
            $customer->fill([
                'user_id' => $user->id,
                'customer_type' => $customer->customer_type ?: $validated['customer_type'],
                'name' => $customer->name ?: $validated['name'],
                'phone' => $customer->phone ?: $validated['phone'],
                'email' => $validated['email'],
                'preferred_channel' => $customer->preferred_channel ?: 'whatsapp',
                'preferred_locale' => $customer->preferred_locale ?: $validated['preferred_locale'],
                'status' => 'active',
            ])->save();

            if (! $customer->sites()->exists()) {
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
            } elseif (! $customer->sites()->where('is_default', true)->exists()) {
                $customer->sites()->oldest('id')->first()?->update(['is_default' => true]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/app/customer');
    }
}
