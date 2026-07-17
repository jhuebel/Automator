<div class="space-y-4">
    <div class="bg-white rounded-lg border border-gray-200 p-4 flex items-center justify-between">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Automatically offer runner updates</h3>
            <p class="text-xs text-gray-500 mt-0.5">When on, runners are told about newer released builds on their next heartbeat and update themselves between jobs.</p>
        </div>
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" wire:click="toggleAutoUpdate" @checked($autoUpdateEnabled) class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
            <span class="text-sm text-gray-600">Enabled</span>
        </label>
    </div>

    <div class="flex justify-end">
        <button wire:click="generateToken" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">
            + Generate Enrollment Token
        </button>
    </div>

    @if ($generatedToken)
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 text-sm space-y-2">
            <div class="flex justify-between items-start">
                <p class="font-medium text-blue-900">Enrollment token (shown once — copy it now)</p>
                <button wire:click="dismissToken" class="text-blue-400 hover:text-blue-600">&times;</button>
            </div>
            <code class="block bg-white border border-blue-200 rounded px-3 py-2 text-xs break-all">{{ $generatedToken }}</code>
            <p class="text-blue-800 text-xs">Expires in 60 minutes, single use. Run on the runner host:</p>
            <code class="block bg-gray-900 text-gray-100 rounded px-3 py-2 text-xs">automator-runner register --server {{ url('/') }} --token {{ $generatedToken }} --name my-runner --tags linux</code>
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-500">
                    <th class="px-4 py-2"></th>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Host</th>
                    <th class="px-4 py-2">OS</th>
                    <th class="px-4 py-2">Tags</th>
                    <th class="px-4 py-2">Languages</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Jobs</th>
                    <th class="px-4 py-2">Last Seen</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($this->runners as $runner)
                    <tr wire:key="runner-{{ $runner->id }}" class="hover:bg-gray-50 cursor-pointer" wire:click="toggleExpand('{{ $runner->id }}')">
                        <td class="px-4 py-2 text-gray-400">
                            <span class="inline-block transition-transform {{ $expandedId === $runner->id ? 'rotate-90' : '' }}">&#9656;</span>
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $runner->name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $runner->hostname ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $runner->os ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @foreach ($runner->tags as $tag)
                                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $tag }}</span>
                            @endforeach
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($this->languages as $language)
                                    <span
                                        title="{{ $language->label() }}"
                                        class="text-xs px-1.5 py-0.5 rounded {{ $runner->supportsLanguage($language) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-400' }}"
                                    >
                                        {{ $language->label() }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2">
                            <span class="text-xs px-2 py-0.5 rounded
                                {{ match($runner->status) {
                                    'online' => 'bg-green-100 text-green-800',
                                    'disabled' => 'bg-gray-200 text-gray-600',
                                    default => 'bg-red-100 text-red-800',
                                } }}">
                                {{ ucfirst($runner->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $runner->current_job_count }}/{{ $runner->max_concurrent_jobs }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $runner->last_seen_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-2 space-x-2 whitespace-nowrap" wire:click.stop>
                            <button wire:click="toggleDisabled('{{ $runner->id }}')" class="text-gray-600 hover:text-gray-900">
                                {{ $runner->status === 'disabled' ? 'Enable' : 'Disable' }}
                            </button>
                            <button wire:click="confirmDelete('{{ $runner->id }}')" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                    @if ($expandedId === $runner->id)
                        <tr wire:key="runner-detail-{{ $runner->id }}">
                            <td colspan="10" class="px-4 py-3 bg-gray-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="text-xs text-gray-600 space-y-1">
                                        <p><span class="text-gray-400">Runner ID:</span> <span class="font-mono">{{ $runner->id }}</span></p>
                                        <p><span class="text-gray-400">Max concurrent jobs:</span> {{ $runner->max_concurrent_jobs }}</p>
                                        <p><span class="text-gray-400">Registered:</span> {{ $runner->created_at?->diffForHumans() ?? '—' }}</p>
                                        <p>
                                            <span class="text-gray-400">Runner version:</span> {{ $runner->version ?? '—' }}
                                            @if ($update = $this->availableUpdateFor($runner))
                                                <span class="text-amber-600">(update to {{ $update->version }} available)</span>
                                            @endif
                                        </p>
                                        <p><span class="text-gray-400">Architecture:</span> {{ $runner->arch ?? '—' }}</p>
                                        <p>
                                            <span class="text-gray-400">Disk space:</span>
                                            @if ($runner->disk_total_bytes)
                                                @php
                                                    $freePercent = round(($runner->disk_free_bytes / $runner->disk_total_bytes) * 100);
                                                @endphp
                                                <span class="{{ $freePercent < 10 ? 'text-red-600 font-medium' : '' }}">
                                                    {{ \Illuminate\Support\Number::fileSize($runner->disk_free_bytes, precision: 1) }}
                                                    free of {{ \Illuminate\Support\Number::fileSize($runner->disk_total_bytes, precision: 1) }}
                                                    ({{ $freePercent }}%)
                                                </span>
                                            @else
                                                —
                                            @endif
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-1">Runtimes reported in last heartbeat</p>
                                        @if (empty($runner->runtimes))
                                            <p class="text-xs text-gray-400">No heartbeat received yet.</p>
                                        @else
                                            <table class="w-full text-xs">
                                                <tbody class="divide-y divide-gray-200">
                                                    @foreach ($runner->runtimes as $runtime)
                                                        <tr>
                                                            <td class="py-1 pr-2 font-medium text-gray-700">{{ $runtime['name'] }}</td>
                                                            <td class="py-1 pr-2 text-gray-400">{{ $runtime['description'] ?? '' }}</td>
                                                            <td class="py-1">
                                                                @if ($runtime['available'] ?? false)
                                                                    <span class="text-green-600">&check; {{ $runtime['version'] ?? '' }}</span>
                                                                @else
                                                                    <span class="text-gray-400">{{ $runtime['error'] ?? 'Not available' }}</span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-6 text-center text-gray-500">No runners registered yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50" wire:click.self="cancelDelete">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h2 class="text-lg font-medium text-gray-900">Delete this runner?</h2>
                <p class="mt-1 text-sm text-gray-600">Its credential will be revoked immediately. This cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                    <button wire:click="delete" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
