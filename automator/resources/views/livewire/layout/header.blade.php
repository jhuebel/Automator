<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public ?string $title = null;

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/login', navigate: true);
    }
}; ?>

<header class="h-14 min-h-14 bg-sidebar text-white flex items-center justify-between px-6 shrink-0 shadow">
    <div class="font-medium text-gray-200">
        {{ $title }}
    </div>

    <div class="flex items-center gap-4">
        @foreach (auth()->user()->getRoleNames() as $role)
            <span class="text-xs font-medium px-2 py-1 rounded bg-white/10 text-gray-200">{{ $role }}</span>
        @endforeach

        <button x-data x-on:click="$store.help.open = true" class="text-gray-300 hover:text-white" title="Help">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="inline-flex items-center text-sm font-medium text-gray-200 hover:text-white focus:outline-none">
                    {{ auth()->user()->username }}
                    <svg class="ms-1 fill-current h-4 w-4" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link href="{{ route('account.index') }}" wire:navigate>
                    My Account
                </x-dropdown-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-dropdown-link>
                        Log Out
                    </x-dropdown-link>
                </button>
            </x-slot>
        </x-dropdown>
    </div>
</header>
