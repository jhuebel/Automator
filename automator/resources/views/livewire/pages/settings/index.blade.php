<?php

use App\Models\AppSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Settings'])] class extends Component
{
    #[Url]
    public string $tab = 'application';

    public int $executionTimeoutSeconds = 300;

    public int $maxHistoryRecords = 1000;

    public string $anthropicApiKey = '';

    public string $anthropicModel = 'claude-sonnet-5';

    public string $anthropicEffort = 'high';

    public bool $showApiKey = false;

    public ?string $savedMessage = null;

    private const MODELS = [
        'claude-fable-5' => 'Fable 5',
        'claude-haiku-4-5-20251001' => 'Haiku 4.5',
        'claude-sonnet-5' => 'Sonnet 5',
        'claude-opus-4-8' => 'Opus 4.8',
    ];

    private const EFFORTS = ['low', 'medium', 'high'];

    public function mount(): void
    {
        $settings = AppSetting::current();
        $this->executionTimeoutSeconds = $settings->execution_timeout_seconds;
        $this->maxHistoryRecords = $settings->max_history_records;
        $this->anthropicModel = $settings->anthropic_model;
        $this->anthropicEffort = $settings->anthropic_effort;
        // API key is intentionally left blank in the form; it's write-only from the UI's
        // perspective so the encrypted value never round-trips back to the browser.
    }

    public function getModelsProperty(): array
    {
        return self::MODELS;
    }

    public function getEffortsProperty(): array
    {
        return self::EFFORTS;
    }

    public function getSupportsEffortProperty(): bool
    {
        return ! str_contains(strtolower($this->anthropicModel), 'haiku');
    }

    public function saveApplication(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'executionTimeoutSeconds' => 'required|integer|min:1|max:86400',
            'maxHistoryRecords' => 'required|integer|min:1|max:100000',
        ]);

        $settings = AppSetting::current();
        $settings->update([
            'execution_timeout_seconds' => $validated['executionTimeoutSeconds'],
            'max_history_records' => $validated['maxHistoryRecords'],
        ]);

        \App\Models\AuditLog::record('Settings.Updated', 'Application');
        $this->savedMessage = 'Application settings saved.';
    }

    public function saveAi(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'anthropicModel' => 'required|string',
            'anthropicEffort' => 'required|string|in:low,medium,high',
        ]);

        $settings = AppSetting::current();
        $attributes = [
            'anthropic_model' => $validated['anthropicModel'],
            'anthropic_effort' => $validated['anthropicEffort'],
        ];

        if (filled($this->anthropicApiKey)) {
            $attributes['anthropic_api_key'] = $this->anthropicApiKey;
        }

        $settings->update($attributes);
        $this->anthropicApiKey = '';

        \App\Models\AuditLog::record('Settings.Updated', 'AI Assistant');
        $this->savedMessage = 'AI Assistant settings saved.';
    }
}; ?>

<div class="p-6 space-y-4">
    <div class="border-b border-gray-200">
        <nav class="flex gap-6 -mb-px">
            @foreach (['application' => 'Application', 'users' => 'Users', 'runners' => 'Runners', 'sso' => 'Single Sign-On', 'ai' => 'AI Assistant', 'status' => 'System Status', 'audit' => 'Audit Logs'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tab', '{{ $key }}')"
                    class="py-2 border-b-2 text-sm font-medium {{ $tab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    @if ($savedMessage)
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-md px-4 py-2">
            {{ $savedMessage }}
        </div>
    @endif

    @if ($tab === 'application')
        <div class="bg-white rounded-lg border border-gray-200 p-4 max-w-lg space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Execution Timeout (seconds)</label>
                <input type="number" wire:model="executionTimeoutSeconds" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                @error('executionTimeoutSeconds') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max History Records</label>
                <input type="number" wire:model="maxHistoryRecords" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                @error('maxHistoryRecords') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>
            <button wire:click="saveApplication" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">Save</button>
        </div>
    @elseif ($tab === 'users')
        <livewire:settings.user-management />
    @elseif ($tab === 'runners')
        <livewire:settings.runner-management />
    @elseif ($tab === 'sso')
        <livewire:settings.sso-configuration />
    @elseif ($tab === 'ai')
        <div class="bg-white rounded-lg border border-gray-200 p-4 max-w-lg space-y-4">
            <div class="bg-blue-50 border border-blue-200 text-blue-800 text-xs rounded-md px-3 py-2">
                The API key is encrypted at rest and never displayed once saved. Leave blank to keep the current key.
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Anthropic API Key</label>
                <div class="mt-1 flex gap-2">
                    <input type="{{ $showApiKey ? 'text' : 'password' }}" wire:model="anthropicApiKey" placeholder="sk-ant-..." class="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    <button type="button" wire:click="$toggle('showApiKey')" class="px-3 py-1.5 bg-gray-100 text-gray-700 rounded-md text-xs">
                        {{ $showApiKey ? 'Hide' : 'Show' }}
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Model</label>
                <select wire:model="anthropicModel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    @foreach ($this->models as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Effort</label>
                <select wire:model="anthropicEffort" @disabled(!$this->supportsEffort) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                    @foreach ($this->efforts as $effort)
                        <option value="{{ $effort }}">{{ ucfirst($effort) }}</option>
                    @endforeach
                </select>
                @unless ($this->supportsEffort)
                    <p class="text-xs text-gray-500 mt-1">Haiku models don't support the effort parameter.</p>
                @endunless
            </div>
            <button wire:click="saveAi" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">Save</button>
        </div>
    @elseif ($tab === 'status')
        <livewire:settings.system-status />
    @elseif ($tab === 'audit')
        <livewire:settings.audit-log-viewer />
    @endif
</div>
