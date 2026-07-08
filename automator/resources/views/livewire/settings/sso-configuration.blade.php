<div class="space-y-4 max-w-lg">
    @if ($savedMessage)
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-md px-4 py-2">
            {{ $savedMessage }}
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Microsoft Entra ID</h3>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="entraEnabled" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <span class="text-sm text-gray-600">Enabled</span>
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Application (client) ID</label>
            <input type="text" wire:model="entraClientId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            @error('entraClientId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Client Secret</label>
            <div class="mt-1 flex gap-2">
                <input type="{{ $showEntraSecret ? 'text' : 'password' }}" wire:model="entraClientSecret" placeholder="Leave blank to keep the current secret" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                <button type="button" wire:click="$toggle('showEntraSecret')" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-xs">
                    {{ $showEntraSecret ? 'Hide' : 'Show' }}
                </button>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Directory (tenant) ID</label>
            <input type="text" wire:model="entraTenantId" placeholder="common" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            <p class="text-xs text-gray-500 mt-1">Blank defaults to "common" (any work/school account). Use your tenant ID to restrict sign-in to your organization.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Allowed email domains</label>
            <input type="text" wire:model="entraAllowedDomains" placeholder="example.com, example.org" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            <p class="text-xs text-gray-500 mt-1">Comma-separated. Only applies to auto-provisioning new accounts — blank allows any domain.</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded-md px-3 py-2">
            Redirect URI to register in Entra: <code class="break-all">{{ route('sso.callback', 'entra') }}</code>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Google</h3>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="googleEnabled" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                <span class="text-sm text-gray-600">Enabled</span>
            </label>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Client ID</label>
            <input type="text" wire:model="googleClientId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            @error('googleClientId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Client Secret</label>
            <div class="mt-1 flex gap-2">
                <input type="{{ $showGoogleSecret ? 'text' : 'password' }}" wire:model="googleClientSecret" placeholder="Leave blank to keep the current secret" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                <button type="button" wire:click="$toggle('showGoogleSecret')" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-xs">
                    {{ $showGoogleSecret ? 'Hide' : 'Show' }}
                </button>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Allowed email domains</label>
            <input type="text" wire:model="googleAllowedDomains" placeholder="example.com, example.org" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
            <p class="text-xs text-gray-500 mt-1">Comma-separated. Only applies to auto-provisioning new accounts — blank allows any Google account.</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded-md px-3 py-2">
            Redirect URI to register in Google Cloud Console: <code class="break-all">{{ route('sso.callback', 'google') }}</code>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
        <h3 class="text-sm font-semibold text-gray-900">New user provisioning</h3>
        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="ssoAutoProvisionEnabled" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
            <span class="text-sm text-gray-600">Automatically create an account on first SSO login</span>
        </label>
        <p class="text-xs text-gray-500">
            When off, SSO login only works for accounts an admin already created in Settings &rarr; Users
            (matched by email) — new sign-ins are rejected with an error.
        </p>
        <div>
            <label class="block text-sm font-medium text-gray-700">Default role for new accounts</label>
            <select wire:model="ssoDefaultRole" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                @foreach ($this->roles as $role)
                    <option value="{{ $role }}">{{ $role }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <button wire:click="save" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">Save</button>
</div>
