<?php

namespace App\Http\Middleware;

use App\Support\Roles;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locale = app()->getLocale();
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $messages = array_replace_recursive(
            config('cleanops.translations.en', []),
            config('cleanops_extra_translations.en', []),
            config("cleanops.translations.{$locale}", []),
            config("cleanops_extra_translations.{$locale}", []),
        );

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'role', 'locale']),
                    'permissions' => $user->permissions(),
                ] : null,
                'roles' => Roles::labels(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
            'app' => [
                'name' => config('app.name'),
                'locale' => $locale,
                'dir' => $dir,
                'supportedLocales' => config('cleanops.supported_locales', ['ar', 'en']),
            ],
            'i18n' => [
                'locale' => $locale,
                'dir' => $dir,
                'messages' => $messages,
            ],
        ];
    }
}
