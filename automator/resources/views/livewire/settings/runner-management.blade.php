<div class="space-y-4">
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
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Host</th>
                    <th class="px-4 py-2">OS</th>
                    <th class="px-4 py-2">Tags</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Jobs</th>
                    <th class="px-4 py-2">Last Seen</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($this->runners as $runner)
                    <tr wire:key="runner-{{ $runner->id }}">
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $runner->name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $runner->hostname ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $runner->os ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @foreach ($runner->tags as $tag)
                                <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $tag }}</span>
                            @endforeach
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
                        <td class="px-4 py-2 space-x-2 whitespace-nowrap">
                            <button wire:click="toggleDisabled('{{ $runner->id }}')" class="text-gray-600 hover:text-gray-900">
                                {{ $runner->status === 'disabled' ? 'Enable' : 'Disable' }}
                            </button>
                            <button wire:click="confirmDelete('{{ $runner->id }}')" class="text-red-600 hover:text-red-800">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">No runners registered yet.</td>
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
