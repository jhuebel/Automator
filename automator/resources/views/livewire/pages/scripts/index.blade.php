<?php

use App\Enums\ScriptLanguage;
use App\Models\ScriptDefinition;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Script Library'])] class extends Component
{
    public string $search = '';

    public string $languageFilter = '';

    public ?string $expandedId = null;

    public ?string $confirmingDeleteId = null;

    #[Computed]
    public function scripts()
    {
        return ScriptDefinition::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $term = '%'.$this->search.'%';
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereJsonContains('tags', $this->search);
            }))
            ->when($this->languageFilter, fn ($q) => $q->where('language', $this->languageFilter))
            ->orderBy('name')
            ->get();
    }

    public function toggleExpand(string $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function confirmDelete(string $id): void
    {
        $this->confirmingDeleteId = $id;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function delete(): void
    {
        $this->authorize('scripts.delete');

        $script = ScriptDefinition::findOrFail($this->confirmingDeleteId);
        $script->delete();

        $this->confirmingDeleteId = null;
        unset($this->scripts);
    }
}; ?>

<div class="p-6 space-y-4">
    <div class="flex flex-wrap items-center gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by name, description, or tag..."
            class="flex-1 min-w-[240px] rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
        />

        <select wire:model.live="languageFilter" class="rounded-md border-gray-300 shadow-sm text-sm">
            <option value="">All Languages</option>
            @foreach (ScriptLanguage::cases() as $lang)
                <option value="{{ $lang->value }}">{{ $lang->label() }}</option>
            @endforeach
        </select>

        @can('scripts.edit')
            <a href="{{ route('scripts.create') }}" wire:navigate
               class="ms-auto inline-flex items-center px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                + New Script
            </a>
        @endcan
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @forelse ($this->scripts as $script)
            <div wire:key="script-{{ $script->id }}" class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col">
                <div class="p-4 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-gray-900">{{ $script->name }}</h3>
                        <span class="text-xs font-medium px-2 py-0.5 rounded whitespace-nowrap {{ $script->language->badgeClasses() }}">
                            {{ $script->language->label() }}
                        </span>
                    </div>

                    <p class="text-sm text-gray-500 mt-1">{{ $script->description }}</p>

                    @if (!empty($script->tags))
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach ($script->tags as $tag)
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif

                    <p class="text-xs text-gray-400 mt-2">Updated {{ $script->updated_at->diffForHumans() }}</p>

                    <button wire:click="toggleExpand('{{ $script->id }}')" class="text-xs text-indigo-600 hover:text-indigo-800 mt-2">
                        {{ $this->expandedId === $script->id ? 'Hide source' : 'View source' }}
                    </button>

                    @if ($this->expandedId === $script->id)
                        <pre class="mt-2 bg-gray-900 text-gray-100 text-xs rounded p-3 overflow-auto max-h-64 font-mono">{{ $script->content }}</pre>
                    @endif
                </div>

                <div class="border-t border-gray-100 p-3 flex items-center gap-2">
                    @can('scripts.run')
                        <a href="{{ route('runner.index', ['script' => $script->id]) }}" wire:navigate
                           class="text-sm px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700">
                            Run
                        </a>
                    @endcan

                    @can('scripts.edit')
                        <a href="{{ route('scripts.edit', $script) }}" wire:navigate
                           class="text-sm px-3 py-1.5 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            Edit
                        </a>
                    @endcan

                    @can('scripts.delete')
                        <button wire:click="confirmDelete('{{ $script->id }}')"
                                class="text-sm px-3 py-1.5 bg-red-50 text-red-700 rounded hover:bg-red-100 ms-auto">
                            Delete
                        </button>
                    @endcan
                </div>
            </div>
        @empty
            <div class="col-span-full text-center text-gray-500 py-12">
                No scripts found.
            </div>
        @endforelse
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50" wire:click.self="cancelDelete">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h2 class="text-lg font-medium text-gray-900">Delete this script?</h2>
                <p class="mt-1 text-sm text-gray-600">This cannot be undone. Execution history for this script is kept.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                    <button wire:click="delete" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
