<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', Rule::in(config('cleanops.supported_locales', ['ar', 'en']))],
        ]);

        $request->session()->put('locale', $validated['locale']);
        $request->user()?->forceFill(['locale' => $validated['locale']])->save();

        $message = config("cleanops.translations.{$validated['locale']}.layout.languageUpdated", __('Language preference updated.'));

        return back()->with('success', $message);
    }
}
