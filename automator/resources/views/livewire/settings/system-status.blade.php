<div class="space-y-4">
    <div class="flex justify-end">
        <button wire:click="refresh" class="text-sm px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md">
            Re-check
        </button>
    </div>

    @php
        // flatMap merges each runner's name => description pairs into one map, so a
        // runtime already seen (same name) is simply overwritten, not duplicated.
        $runtimeChecks = collect($runnerRuntimes)
            ->flatMap(fn ($runner) => collect($runner['runtimes'])->pluck('description', 'name'))
            ->all();
    @endphp

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-4 py-2 bg-gray-50">
            <div class="font-medium text-sm text-gray-700">Runner Script Runtimes</div>
            <div class="text-xs text-gray-400">
                Scripts execute only on registered runners, never on this management-plane host — each column reflects
                what that runner last reported in its heartbeat.
            </div>
        </div>

        @if (empty($runnerRuntimes))
            <p class="px-4 py-6 text-center text-gray-500 text-sm">
                No runners registered yet. Add one under
                <a href="{{ route('settings.index', ['tab' => 'runners']) }}" class="underline">Settings &rarr; Runners</a>.
            </p>
        @else
            <table class="min-w-full text-sm divide-y divide-gray-100">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="px-4 py-2">Runtime</th>
                        @foreach ($runnerRuntimes as $runner)
                            <th class="px-4 py-2">
                                {{ $runner['name'] }}
                                <div class="text-xs font-normal {{ $runner['status'] === 'online' ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ ucfirst($runner['status']) }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse ($runtimeChecks as $name => $description)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-900">
                                {{ $name }}
                                <div class="text-xs text-gray-400 font-normal">{{ $description }}</div>
                            </td>
                            @foreach ($runnerRuntimes as $runner)
                                @php $check = collect($runner['runtimes'])->firstWhere('name', $name); @endphp
                                <td class="px-4 py-2 text-xs">
                                    @if (! $check)
                                        <span class="text-gray-400">—</span>
                                    @elseif ($check['available'])
                                        <span class="text-green-600">&check;</span>
                                        <span class="text-gray-500 font-mono">{{ $check['version'] ?? '' }}</span>
                                    @else
                                        <span class="text-red-600">&times;</span>
                                        <span class="text-gray-500">{{ $check['error'] ?? 'Not available' }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($runnerRuntimes) + 1 }}" class="px-4 py-6 text-center text-gray-500">
                                No runtime data reported yet — waiting on each runner's next heartbeat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="font-medium text-sm text-gray-700 mb-2">Database</div>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="text-gray-500">Driver</dt>
            <dd class="text-gray-900">{{ $database['driver'] }}</dd>
            <dt class="text-gray-500">Database</dt>
            <dd class="text-gray-900 font-mono text-xs">{{ $database['database'] ?? '—' }}</dd>
            <dt class="text-gray-500">Connected</dt>
            <dd class="{{ $database['connected'] ? 'text-green-600' : 'text-red-600' }}">{{ $database['connected'] ? 'Yes' : 'No' }}</dd>
            @if ($database['error'])
                <dt class="text-gray-500">Error</dt>
                <dd class="text-red-600 text-xs">{{ $database['error'] }}</dd>
            @endif
            <dt class="text-gray-500">Scripts</dt>
            <dd class="text-gray-900">{{ $database['script_count'] }}</dd>
            <dt class="text-gray-500">Scheduled Jobs</dt>
            <dd class="text-gray-900">{{ $database['job_count'] }}</dd>
            <dt class="text-gray-500">Execution History</dt>
            <dd class="text-gray-900">{{ $database['history_count'] }}</dd>
        </dl>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 p-4">
        <div class="font-medium text-sm text-gray-700">Management Plane</div>
        <div class="text-xs text-gray-400 mb-2">This Laravel host — it schedules and tracks jobs but never executes scripts itself.</div>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt class="text-gray-500">PHP Version</dt>
            <dd class="text-gray-900">{{ $appInfo['php_version'] }}</dd>
            <dt class="text-gray-500">Laravel Version</dt>
            <dd class="text-gray-900">{{ $appInfo['laravel_version'] }}</dd>
            <dt class="text-gray-500">OS</dt>
            <dd class="text-gray-900">{{ $appInfo['os'] }}</dd>
            <dt class="text-gray-500">Queue Driver</dt>
            <dd class="text-gray-900">{{ $appInfo['queue_driver'] }}</dd>
            <dt class="text-gray-500">Server Time</dt>
            <dd class="text-gray-900">{{ $appInfo['server_time'] }}</dd>
        </dl>
    </div>
</div>
