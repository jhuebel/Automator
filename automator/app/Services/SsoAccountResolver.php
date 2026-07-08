<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class SsoAccountResolver
{
    /**
     * Resolve (or auto-provision) the local User for an SSO identity.
     * Returns null if no account exists and auto-provisioning doesn't apply —
     * the caller is responsible for turning that into a user-facing error.
     */
    public function resolve(string $provider, string $providerUserId, string $email, ?string $name): ?User
    {
        $column = $this->columnFor($provider);

        $user = User::query()->where($column, $providerUserId)->first();
        if ($user) {
            AuditLog::record('Auth.SsoLogin', $user->username, "provider: {$provider}");

            return $user;
        }

        $user = User::query()->where('email', $email)->first();
        if ($user) {
            $user->forceFill([$column => $providerUserId])->save();
            AuditLog::record('Auth.SsoLogin', $user->username, "provider: {$provider} (linked)");

            return $user;
        }

        $settings = AppSetting::current();
        if (! $this->canAutoProvision($settings, $provider, $email)) {
            return null;
        }

        $user = $this->createUser($settings, $provider, $providerUserId, $email, $name);

        AuditLog::record('User.AutoProvisioned', $user->username, "provider: {$provider}, role: {$settings->sso_default_role}");
        AuditLog::record('Auth.SsoLogin', $user->username, "provider: {$provider}");

        return $user;
    }

    private function canAutoProvision(AppSetting $settings, string $provider, string $email): bool
    {
        if (! $settings->sso_auto_provision_enabled) {
            return false;
        }

        $allowedDomains = $settings->ssoAllowedDomainsFor($provider);
        if (empty($allowedDomains)) {
            return true;
        }

        $domain = Str::lower(Str::after($email, '@'));

        return in_array($domain, $allowedDomains, true);
    }

    private function createUser(AppSetting $settings, string $provider, string $providerUserId, string $email, ?string $name): User
    {
        $username = $this->uniqueUsernameFromEmail($email);

        $user = User::create([
            'username' => $username,
            'name' => filled($name) ? $name : ucfirst($username),
            'email' => $email,
            'password' => null,
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            $this->columnFor($provider) => $providerUserId,
        ])->save();

        $user->assignRole($settings->sso_default_role ?: 'Viewer');

        return $user;
    }

    private function uniqueUsernameFromEmail(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '.') ?: 'user';
        $username = $base;
        $suffix = 1;

        while (User::query()->where('username', $username)->exists()) {
            $suffix++;
            $username = "{$base}{$suffix}";
        }

        return $username;
    }

    private function columnFor(string $provider): string
    {
        return match ($provider) {
            'entra' => 'entra_object_id',
            'google' => 'google_id',
            default => throw new \InvalidArgumentException("Unknown SSO provider: {$provider}"),
        };
    }
}
