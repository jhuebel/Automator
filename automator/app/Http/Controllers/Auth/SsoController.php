<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\SsoAccountResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SsoController extends Controller
{
    /**
     * Maps our user-facing provider slugs (used in routes and Settings) to
     * the underlying Socialite driver name. Google is Socialite's own
     * built-in driver; Entra ID uses the community "microsoft" driver
     * against the Azure AD v2.0 / Entra endpoint (see AppServiceProvider).
     */
    private const DRIVERS = [
        'entra' => 'microsoft',
        'google' => 'google',
    ];

    public function redirect(string $provider): RedirectResponse
    {
        $settings = $this->configureProvider($provider);

        return Socialite::driver(self::DRIVERS[$provider])->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->configureProvider($provider);

        $ssoUser = Socialite::driver(self::DRIVERS[$provider])->user();

        $email = $ssoUser->getEmail();

        if (blank($email)) {
            return redirect()->route('login')->withErrors([
                'form.username' => 'Your Microsoft/Google account did not return an email address.',
            ]);
        }

        $user = app(SsoAccountResolver::class)->resolve(
            $provider,
            (string) $ssoUser->getId(),
            $email,
            $ssoUser->getName(),
        );

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'form.username' => "No Automator account found for {$email}. Contact your administrator.",
            ]);
        }

        Auth::login($user);
        Session::regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Hydrate config('services.{driver}') from the DB-stored settings —
     * these credentials are admin-configured in Settings > Single Sign-On,
     * not .env values, so Socialite's config is built per-request.
     */
    private function configureProvider(string $provider): AppSetting
    {
        if (! array_key_exists($provider, self::DRIVERS)) {
            throw new NotFoundHttpException;
        }

        $settings = AppSetting::current();

        if (! $settings->isSsoEnabledFor($provider)) {
            throw new NotFoundHttpException;
        }

        $redirect = route('sso.callback', $provider);

        if ($provider === 'entra') {
            config(['services.microsoft' => [
                'client_id' => $settings->entra_client_id,
                'client_secret' => $settings->entra_client_secret,
                'redirect' => $redirect,
                'tenant' => filled($settings->entra_tenant_id) ? $settings->entra_tenant_id : 'common',
            ]]);
        } else {
            config(['services.google' => [
                'client_id' => $settings->google_client_id,
                'client_secret' => $settings->google_client_secret,
                'redirect' => $redirect,
            ]]);
        }

        return $settings;
    }
}
