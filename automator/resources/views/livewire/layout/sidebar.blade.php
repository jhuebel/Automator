<?php

use Livewire\Volt\Component;

new class extends Component
{
    //
}; ?>

<aside class="w-[220px] shrink-0 flex flex-col bg-sidebar text-gray-300 h-screen">
    <div class="flex items-center gap-2 px-5 h-16 border-b border-white/10 shrink-0">
        <span class="text-yellow-400 text-xl">&#9889;</span>
        <span class="text-white font-semibold text-lg">Automator</span>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 space-y-1">
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
            Dashboard
        </x-sidebar-link>

        @can('scripts.view')
            <x-sidebar-link :href="route('scripts.index')" :active="request()->routeIs('scripts.*')">
                Script Library
            </x-sidebar-link>
        @endcan

        @can('scripts.run')
            <x-sidebar-link :href="route('runner.index')" :active="request()->routeIs('runner.*')">
                Run Script
            </x-sidebar-link>
        @endcan

        <x-sidebar-link :href="route('history.index')" :active="request()->routeIs('history.*')">
            History
        </x-sidebar-link>

        @can('jobs.view')
            <x-sidebar-link :href="route('jobs.index')" :active="request()->routeIs('jobs.*')">
                Scheduled Jobs
            </x-sidebar-link>
        @endcan

        <x-sidebar-link :href="route('help.index')" :active="request()->routeIs('help.*')">
            Help
        </x-sidebar-link>

        @can('settings.manage')
            <div class="mt-4 pt-4 border-t border-white/10">
                <x-sidebar-link :href="route('settings.index')" :active="request()->routeIs('settings.*')">
                    Settings
                </x-sidebar-link>
            </div>
        @endcan
    </nav>

    <div class="px-5 py-4 border-t border-white/10 text-xs text-gray-500 flex items-center justify-between shrink-0">
        <span>v{{ config('automator.version', '0.1.0') }}</span>
        <a href="https://github.com" target="_blank" rel="noopener" class="hover:text-gray-300">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 .5C5.73.5.5 5.73.5 12c0 5.09 3.29 9.4 7.86 10.93.57.1.79-.25.79-.55v-2.15c-3.2.7-3.87-1.36-3.87-1.36-.53-1.34-1.29-1.7-1.29-1.7-1.05-.72.08-.71.08-.71 1.17.08 1.78 1.2 1.78 1.2 1.03 1.77 2.7 1.26 3.36.96.1-.75.4-1.26.73-1.55-2.56-.29-5.26-1.28-5.26-5.7 0-1.26.45-2.29 1.19-3.09-.12-.29-.52-1.46.11-3.05 0 0 .97-.31 3.18 1.18a11 11 0 015.79 0c2.2-1.49 3.17-1.18 3.17-1.18.64 1.59.24 2.76.12 3.05.74.8 1.19 1.83 1.19 3.09 0 4.43-2.71 5.4-5.29 5.69.42.36.79 1.07.79 2.16v3.2c0 .3.21.66.8.55A11.5 11.5 0 0023.5 12C23.5 5.73 18.27.5 12 .5z"/></svg>
        </a>
    </div>
</aside>
