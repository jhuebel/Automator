<?php

namespace App\Livewire\Settings;

use App\Models\AppSetting;
use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class SsoConfiguration extends Component
{
    public bool $entraEnabled = false;

    public string $entraClientId = '';

    public string $entraClientSecret = '';

    public string $entraTenantId = '';

    public string $entraAllowedDomains = '';

    public bool $showEntraSecret = false;

    public bool $googleEnabled = false;

    public string $googleClientId = '';

    public string $googleClientSecret = '';

    public string $googleAllowedDomains = '';

    public bool $showGoogleSecret = false;

    public bool $ssoAutoProvisionEnabled = false;

    public string $ssoDefaultRole = 'Viewer';

    public ?string $savedMessage = null;

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->pluck('name');
    }

    public function mount(): void
    {
        $settings = AppSetting::current();

        $this->entraEnabled = $settings->entra_enabled;
        $this->entraClientId = $settings->entra_client_id ?? '';
        $this->entraTenantId = $settings->entra_tenant_id ?? '';
        $this->entraAllowedDomains = $settings->entra_allowed_domains ?? '';

        $this->googleEnabled = $settings->google_enabled;
        $this->googleClientId = $settings->google_client_id ?? '';
        $this->googleAllowedDomains = $settings->google_allowed_domains ?? '';

        $this->ssoAutoProvisionEnabled = $settings->sso_auto_provision_enabled;
        $this->ssoDefaultRole = $settings->sso_default_role ?? 'Viewer';

        // Client secrets are intentionally left blank in the form — write-only,
        // same as the Anthropic API key on the AI Assistant tab.
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'entraEnabled' => 'boolean',
            'entraClientId' => 'nullable|string|max:255',
            'entraTenantId' => 'nullable|string|max:255',
            'entraAllowedDomains' => 'nullable|string|max:1000',
            'googleEnabled' => 'boolean',
            'googleClientId' => 'nullable|string|max:255',
            'googleAllowedDomains' => 'nullable|string|max:1000',
            'ssoAutoProvisionEnabled' => 'boolean',
            'ssoDefaultRole' => ['required', \Illuminate\Validation\Rule::in($this->roles)],
        ]);

        $attributes = [
            'entra_enabled' => $validated['entraEnabled'],
            'entra_client_id' => $validated['entraClientId'],
            'entra_tenant_id' => $validated['entraTenantId'],
            'entra_allowed_domains' => $validated['entraAllowedDomains'],
            'google_enabled' => $validated['googleEnabled'],
            'google_client_id' => $validated['googleClientId'],
            'google_allowed_domains' => $validated['googleAllowedDomains'],
            'sso_auto_provision_enabled' => $validated['ssoAutoProvisionEnabled'],
            'sso_default_role' => $validated['ssoDefaultRole'],
        ];

        if (filled($this->entraClientSecret)) {
            $attributes['entra_client_secret'] = $this->entraClientSecret;
        }

        if (filled($this->googleClientSecret)) {
            $attributes['google_client_secret'] = $this->googleClientSecret;
        }

        AppSetting::current()->update($attributes);

        $this->entraClientSecret = '';
        $this->googleClientSecret = '';

        AuditLog::record('Settings.Updated', 'Single Sign-On');
        $this->savedMessage = 'Single Sign-On settings saved.';
    }

    public function render()
    {
        return view('livewire.settings.sso-configuration');
    }
}
