<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Help'])] class extends Component
{
    public ?string $open = 'dashboard';

    public function toggle(string $section): void
    {
        $this->open = $this->open === $section ? null : $section;
    }
}; ?>

<div class="p-6 max-w-3xl space-y-3">
    @php
        $sections = [
            'dashboard' => [
                'title' => 'Dashboard',
                'body' => 'Shows script counts, successful/failed run totals, currently running executions, active schedules, a 14-day execution chart, a language breakdown, and your 5 most recent executions. Updates each time you load the page.',
            ],
            'scripts' => [
                'title' => 'Script Library',
                'body' => 'Browse, search, and filter scripts by name, description, tag, or language. Viewers can view and run scripts; Developers and Admins can also create, edit, and delete them.',
            ],
            'runner' => [
                'title' => 'Run Script',
                'body' => 'Pick a script, fill in any variables it defines, and click Run. Output streams live as the script executes. Click Cancel to terminate a running script.',
            ],
            'jobs' => [
                'title' => 'Scheduled Jobs',
                'body' => 'Attach a cron expression to a script so it runs automatically. Use the quick-pick buttons for common schedules, or type your own 5-field cron expression — the next run time previews live. Toggle a job on/off with the switch.',
            ],
            'history' => [
                'title' => 'History',
                'body' => 'A record of every script run, manual or scheduled. Click a row to expand and view its full captured output.',
            ],
        ];

        if (auth()->user()->can('settings.manage')) {
            $sections['settings'] = [
                'title' => 'Settings',
                'body' => 'Admin-only. Configure execution timeout and concurrency limits, manage users and roles, configure the AI Assistant, check installed script runtimes, and review the audit log.',
            ];
        }

        $sections['account'] = [
            'title' => 'My Account',
            'body' => 'Update your own email address and password.',
        ];
    @endphp

    @foreach ($sections as $key => $section)
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <button type="button" wire:click="toggle('{{ $key }}')" class="w-full flex justify-between items-center px-4 py-3 text-left">
                <span class="font-medium text-gray-900 text-sm">{{ $section['title'] }}</span>
                <span class="text-gray-400">{{ $open === $key ? '−' : '+' }}</span>
            </button>
            @if ($open === $key)
                <div class="px-4 pb-4 text-sm text-gray-600">{{ $section['body'] }}</div>
            @endif
        </div>
    @endforeach
</div>
