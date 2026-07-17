<div class="space-y-4">
    @can('settings.manage')
        <div class="flex justify-end">
            @if (! $isEditing)
                <button wire:click="newGroup" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                    + New Group
                </button>
            @endif
        </div>
    @endcan

    @if ($isEditing)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" wire:model="description" placeholder="e.g. us-east datacenter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('description') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Member Runners</label>
                <p class="text-xs text-gray-500 mt-1 mb-2">A runner may belong to more than one group. A script/job targeting this group is routed to whichever eligible member is least busy.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-1 max-h-64 overflow-y-auto border border-gray-200 rounded-md p-3">
                    @forelse ($this->allRunners as $runner)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="selectedRunnerIds" value="{{ $runner->id }}" class="rounded border-gray-300" />
                            <span>{{ $runner->name }}</span>
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $runner->status === 'online' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                {{ ucfirst($runner->status) }}
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No runners registered yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="flex gap-2">
                <button wire:click="save" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">Save Group</button>
                <button wire:click="cancelEdit" class="px-4 py-2 bg-white border border-gray-300 text-sm rounded-md text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-500">
                    <th class="px-4 py-2"></th>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Description</th>
                    <th class="px-4 py-2">Members</th>
                    <th class="px-4 py-2">Languages (aggregate)</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($this->groups as $group)
                    <tr wire:key="group-{{ $group->id }}" class="hover:bg-gray-50 cursor-pointer" wire:click="toggleExpand('{{ $group->id }}')">
                        <td class="px-4 py-2 text-gray-400">
                            <span class="inline-block transition-transform {{ $expandedId === $group->id ? 'rotate-90' : '' }}">&#9656;</span>
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $group->name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $group->description ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $group->runners->count() }}</td>
                        <td class="px-4 py-2">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($this->languages as $language)
                                    <span
                                        title="{{ $language->label() }}"
                                        class="text-xs px-1.5 py-0.5 rounded {{ $group->supportsLanguage($language) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-400' }}"
                                    >
                                        {{ $language->label() }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2 space-x-2 whitespace-nowrap" wire:click.stop>
                            @can('settings.manage')
                                <button wire:click="editGroup('{{ $group->id }}')" class="text-gray-600 hover:text-gray-900">Edit</button>
                                <button wire:click="confirmDelete('{{ $group->id }}')" class="text-red-600 hover:text-red-800">Delete</button>
                            @endcan
                        </td>
                    </tr>
                    @if ($expandedId === $group->id)
                        <tr wire:key="group-detail-{{ $group->id }}">
                            <td colspan="6" class="px-4 py-3 bg-gray-50">
                                @if ($group->runners->isEmpty())
                                    <p class="text-xs text-gray-400">No member runners yet.</p>
                                @else
                                    <table class="w-full text-xs">
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach ($group->runners as $runner)
                                                <tr>
                                                    <td class="py-1 pr-2 font-medium text-gray-700">{{ $runner->name }}</td>
                                                    <td class="py-1 pr-2 text-gray-400">{{ $runner->hostname ?? '—' }}</td>
                                                    <td class="py-1">
                                                        <span class="px-1.5 py-0.5 rounded {{ $runner->status === 'online' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}">
                                                            {{ ucfirst($runner->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">No runner groups yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50" wire:click.self="cancelDelete">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h2 class="text-lg font-medium text-gray-900">Delete this runner group?</h2>
                <p class="mt-1 text-sm text-gray-600">Member runners are unaffected — only the group and its membership are removed. Any scheduled job pinned to this group falls back to Auto. This cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                    <button wire:click="delete" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
