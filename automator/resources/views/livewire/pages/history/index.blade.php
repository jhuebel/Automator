<?php

use App\Models\AppSetting;
use App\Models\ScriptExecutionResult;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'History'])] class extends Component
{
    public ?string $expandedId = null;

    #[Computed]
    public function executions()
    {
        return ScriptExecutionResult::query()
            ->with('runner')
            ->orderByDesc('started_at')
            ->limit(AppSetting::current()->max_history_records)
            ->get();
    }

    public function toggleExpand(string $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }
}; ?>

<div class="p-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500"></th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Script</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Language</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Runner</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Started</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Duration</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Exit Code</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Lines</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->executions as $execution)
                    <tr wire:key="row-{{ $execution->id }}" class="hover:bg-gray-50 cursor-pointer" wire:click="toggleExpand('{{ $execution->id }}')">
                        <td class="px-4 py-2">
                            @if ($execution->is_running)
                                <span class="inline-block h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                            @elseif ($execution->is_success)
                                <span class="text-green-600">&check;</span>
                            @else
                                <span class="text-red-600">&times;</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $execution->script_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $execution->language->label() }}</td>
                        <td class="px-4 py-2 text-gray-500">
                            @if ($execution->runner)
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full {{ $execution->runner->status === 'online' ? 'bg-green-500' : 'bg-gray-300' }}"></span>
                                    {{ $execution->runner->name }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $execution->started_at->diffForHumans() }}</td>
                        <td class="px-4 py-2 text-gray-500">
                            @if ($execution->duration_seconds !== null)
                                {{ $execution->duration_seconds }}s
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $execution->exit_code ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ count($execution->output) }}</td>
                    </tr>
                    @if ($expandedId === $execution->id)
                        <tr wire:key="expanded-{{ $execution->id }}">
                            <td colspan="8" class="px-4 py-2 bg-gray-900 space-y-2">
                                @if ($execution->runner)
                                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-300 border-b border-gray-800 pb-2">
                                        <span><span class="text-gray-500">Runner:</span> {{ $execution->runner->name }}</span>
                                        <span><span class="text-gray-500">Host:</span> {{ $execution->runner->hostname ?? '—' }}</span>
                                        <span><span class="text-gray-500">OS:</span> {{ $execution->runner->os ?? '—' }}</span>
                                        <span><span class="text-gray-500">Tags:</span> {{ !empty($execution->runner->tags) ? implode(', ', $execution->runner->tags) : '—' }}</span>
                                        <span>
                                            <span class="text-gray-500">Current status:</span>
                                            <span class="{{ $execution->runner->status === 'online' ? 'text-green-400' : 'text-gray-400' }}">{{ ucfirst($execution->runner->status) }}</span>
                                        </span>
                                        <span><span class="text-gray-500">Last seen:</span> {{ $execution->runner->last_seen_at?->diffForHumans() ?? '—' }}</span>
                                    </div>
                                @else
                                    <p class="text-xs text-gray-500 border-b border-gray-800 pb-2">No runner recorded for this execution.</p>
                                @endif
                                <pre
                                    class="text-xs text-gray-100 font-mono overflow-auto max-h-[300px]"
                                    x-data
                                    x-html="$ansiToHtml(@js(collect($execution->output)->pluck('text')->implode("\n")))"
                                ></pre>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">No executions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
