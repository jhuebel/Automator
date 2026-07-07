<div class="space-y-4">
    <div class="flex justify-end">
        @if (! $isAdding && ! $editingId)
            <button wire:click="startAdding" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                + Add User
            </button>
        @endif
    </div>

    @if ($isAdding || $editingId)
        @php($editingUser = $editingId ? \App\Models\User::find($editingId) : null)
        @php($locked = $editingUser && ($this->isProtected($editingUser) || $this->isSelf($editingUser)))

        <div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" wire:model="username" @disabled($locked) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100" />
                    @error('username') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('email') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Password @if ($editingId) <span class="text-gray-400 font-normal">(leave blank to keep)</span> @endif
                    </label>
                    <input type="password" wire:model="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('password') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Role</label>
                    <select wire:model="role" @disabled($locked) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                        <option value="">Select a role...</option>
                        @foreach ($this->roles as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            @if ($locked)
                <p class="text-xs text-gray-500">Username and role are locked for the protected admin account and your own account.</p>
            @endif

            <div class="flex gap-2">
                <button wire:click="save" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">Save</button>
                <button wire:click="cancelForm" class="px-4 py-2 bg-white border border-gray-300 text-sm rounded-md text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-500">
                    <th class="px-4 py-2">Username</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Role</th>
                    <th class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach ($this->users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-2 font-medium text-gray-900">
                            {{ $user->username }}
                            @if ($this->isProtected($user)) <span class="text-xs text-gray-400">(protected)</span> @endif
                            @if ($this->isSelf($user)) <span class="text-xs text-gray-400">(you)</span> @endif
                        </td>
                        <td class="px-4 py-2 text-gray-600">{{ $user->email }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $user->roles->first()?->name ?? '—' }}</td>
                        <td class="px-4 py-2 space-x-2">
                            <button wire:click="editUser({{ $user->id }})" class="text-gray-600 hover:text-gray-900">Edit</button>
                            @unless ($this->isProtected($user) || $this->isSelf($user))
                                <button wire:click="confirmDelete({{ $user->id }})" class="text-red-600 hover:text-red-800">Delete</button>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50" wire:click.self="cancelDelete">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h2 class="text-lg font-medium text-gray-900">Delete this user?</h2>
                <p class="mt-1 text-sm text-gray-600">This cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                    <button wire:click="delete" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
