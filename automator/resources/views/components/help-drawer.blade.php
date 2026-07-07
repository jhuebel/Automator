@php
    $blurbs = [
        'dashboard' => 'Stat cards, a 14-day execution chart, a language breakdown, and your 5 most recent executions.',
        'scripts.index' => 'Search and filter scripts. Run, edit, or delete them depending on your role.',
        'scripts.create' => 'Fill in General details, write the script in the Code tab, and define any Variables it needs.',
        'scripts.edit' => 'Fill in General details, write the script in the Code tab, and define any Variables it needs.',
        'runner.index' => 'Pick a script, fill in its variables, and click Run to stream live output.',
        'jobs.index' => 'Attach a cron schedule to a script. Use the quick-pick presets or write your own expression.',
        'history.index' => 'Every past execution. Click a row to see its full output.',
        'settings.index' => 'Application limits, user management, AI Assistant configuration, system status, and the audit log.',
        'account.index' => 'Change your own email or password.',
    ];

    $routeName = request()->route()?->getName();
    $blurb = $blurbs[$routeName] ?? 'Browse Automator using the sidebar. Visit the full Help page for more detail.';
@endphp

<div
    x-data
    x-show="$store.help.open"
    x-on:keydown.escape.window="$store.help.open = false"
    x-cloak
    class="fixed inset-0 z-50"
    style="display: none"
>
    <div class="absolute inset-0 bg-gray-500/50" x-on:click="$store.help.open = false"></div>
    <div class="absolute right-0 top-0 h-full w-[380px] bg-white shadow-xl p-6 overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-gray-900">Help</h2>
            <button x-on:click="$store.help.open = false" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <p class="text-sm text-gray-600">{{ $blurb }}</p>
        <a href="{{ route('help.index') }}" wire:navigate class="inline-block mt-4 text-sm text-indigo-600 hover:text-indigo-800">
            View full Help page &rarr;
        </a>
    </div>
</div>
