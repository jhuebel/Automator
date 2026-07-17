<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AppSetting extends Model
{
    protected $fillable = [
        'execution_timeout_seconds', 'max_history_records',
        'anthropic_api_key', 'anthropic_model', 'anthropic_effort',
        'sso_auto_provision_enabled', 'sso_default_role',
        'entra_enabled', 'entra_client_id', 'entra_client_secret', 'entra_tenant_id', 'entra_allowed_domains',
        'google_enabled', 'google_client_id', 'google_client_secret', 'google_allowed_domains',
        'runner_auto_update_enabled',
    ];

    protected function casts(): array
    {
        return [
            'anthropic_api_key' => 'encrypted',
            'sso_auto_provision_enabled' => 'boolean',
            'entra_enabled' => 'boolean',
            'entra_client_secret' => 'encrypted',
            'google_enabled' => 'boolean',
            'google_client_secret' => 'encrypted',
            'runner_auto_update_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $settings = static::query()->firstOrCreate([]);

        // firstOrCreate()'s insert only sets the attributes it was given (none
        // here), so a freshly-created row's DB-level column defaults (e.g.
        // execution_timeout_seconds) aren't reflected on the in-memory model
        // until it's reloaded.
        if ($settings->wasRecentlyCreated) {
            $settings->refresh();
        }

        return $settings;
    }

    /**
     * True if this SSO provider is toggled on and has the credentials it
     * needs to actually attempt an OAuth flow.
     */
    public function isSsoEnabledFor(string $provider): bool
    {
        return match ($provider) {
            'entra' => $this->entra_enabled && filled($this->entra_client_id) && filled($this->entra_client_secret),
            'google' => $this->google_enabled && filled($this->google_client_id) && filled($this->google_client_secret),
            default => false,
        };
    }

    /**
     * Lowercased, trimmed email domains allowed to auto-provision via this
     * provider. Empty means unrestricted.
     *
     * @return list<string>
     */
    public function ssoAllowedDomainsFor(string $provider): array
    {
        $raw = match ($provider) {
            'entra' => $this->entra_allowed_domains,
            'google' => $this->google_allowed_domains,
            default => null,
        };

        return collect(explode(',', (string) $raw))
            ->map(fn ($domain) => Str::lower(trim($domain)))
            ->filter()
            ->values()
            ->all();
    }
}
