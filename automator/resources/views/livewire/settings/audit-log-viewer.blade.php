<div class="space-y-3">
    <div class="flex justify-between items-center">
        <select wire:model.live="filter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="all">All actions</option>
            <option value="login">Login</option>
            <option value="script">Script</option>
            <option value="job">Job</option>
            <option value="user">User</option>
            <option value="settings">Settings</option>
        </select>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr class="text-left text-gray-500">
                    <th class="px-4 py-2">Timestamp</th>
                    <th class="px-4 py-2">Username</th>
                    <th class="px-4 py-2">Action</th>
                    <th class="px-4 py-2">Resource</th>
                    <th class="px-4 py-2">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse ($this->logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $log->username ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">{{ $log->action }}</span>
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $log->resource ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $log->details ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">No audit log entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
