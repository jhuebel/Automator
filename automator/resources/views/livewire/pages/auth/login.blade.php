<?php

use App\Livewire\Forms\LoginForm;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public bool $entraEnabled = false;

    public bool $googleEnabled = false;

    public function mount(): void
    {
        $settings = AppSetting::current();
        $this->entraEnabled = $settings->isSsoEnabledFor('entra');
        $this->googleEnabled = $settings->isSsoEnabledFor('google');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($entraEnabled || $googleEnabled)
        <div class="space-y-2 mb-6">
            @if ($entraEnabled)
                <a href="{{ route('sso.redirect', 'entra') }}" class="flex items-center justify-center gap-2 w-full px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Sign in with Microsoft
                </a>
            @endif
            @if ($googleEnabled)
                <a href="{{ route('sso.redirect', 'google') }}" class="flex items-center justify-center gap-2 w-full px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Sign in with Google
                </a>
            @endif
        </div>
        <div class="relative mb-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-xs">
                <span class="bg-white px-2 text-gray-500">or sign in with a password</span>
            </div>
        </div>
    @endif

    <form wire:submit="login">
        <!-- Username -->
        <div>
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input wire:model="form.username" id="username" class="block mt-1 w-full" type="text" name="username" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.username')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="form.password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</div>
