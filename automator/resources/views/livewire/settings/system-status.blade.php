<div class="space-y-4">
    <div class="flex justify-end">
        <button wire:click="refresh" class="text-sm px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md">
            Re-check
        </button>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <div class="px-4 py-2 bg-gray-50 font-medium text-sm text-gray-700">Script Runtimes</div>
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="px-4 py-2"></th>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Version</th>
                    <th class="px-4 py-2">Path</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($runtimes as $runtime)
                    <tr>
                        <td class="px-4 py-2">
                            @if ($runtime['available'])
                                <span class="text-green-600">&check;</span>
                            @else
                                <span class="text-red-600">&times;</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">
                            {{ $runtime['name'] }}
                            <div class="text-xs text-gray-400 font-normal">{{ $runtime['description'] }}</div>
                        </td>
                        <td class="px-4 py-2 text-gray-600 text-xs font-mono">{{ $runtime['version'] ?? $runtime['error'] ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs font-mono">{{ $runtime['path'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
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
        <div class="font-medium text-sm text-gray-700 mb-2">Application</div>
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
