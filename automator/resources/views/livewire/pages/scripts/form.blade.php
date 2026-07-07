<?php

use App\Enums\ScriptLanguage;
use App\Enums\ScriptVariableType;
use App\Jobs\StreamClaudeCompletionJob;
use App\Models\ScriptDefinition;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?ScriptDefinition $script = null;

    public string $name = '';

    public string $description = '';

    public string $language = 'Bash';

    public string $content = '';

    public string $tagsInput = '';

    public array $variables = [];

    public string $activeTab = 'general';

    public function mount(?ScriptDefinition $script = null): void
    {
        if ($script?->exists) {
            $this->script = $script;
            $this->name = $script->name;
            $this->description = (string) $script->description;
            $this->language = $script->language->value;
            $this->content = $script->content;
            $this->tagsInput = implode(', ', $script->tags);
            $this->variables = $script->variables;
        }
    }

    public function getTitleProperty(): string
    {
        return $this->script ? 'Edit Script' : 'New Script';
    }

    public function getCodeMirrorModeProperty(): string
    {
        return ScriptLanguage::from($this->language)->codeMirrorMode();
    }

    public function getVariableTypesProperty(): array
    {
        return ScriptVariableType::cases();
    }

    public function getLanguagesProperty(): array
    {
        return ScriptLanguage::cases();
    }

    public function updatedLanguage(string $value): void
    {
        $this->dispatch('language-changed', mode: ScriptLanguage::from($value)->codeMirrorMode());
    }

    public function runAi(string $mode, string $prompt = ''): string
    {
        $this->authorize('scripts.edit');

        $language = ScriptLanguage::from($this->language)->label();

        [$system, $user] = match ($mode) {
            'generate' => [
                "You are an expert at writing {$language} scripts. Generate a complete, working script based on the user's request. Only output the raw script content — no explanations, no markdown code fences.",
                $prompt,
            ],
            'improve' => [
                "You are an expert at improving {$language} scripts. Given the existing script and the requested change, output the complete improved script. Only output the raw script content — no explanations, no markdown code fences.",
                "Existing script:\n{$this->content}\n\nRequested change: {$prompt}",
            ],
            'explain' => [
                "You are an expert at explaining {$language} scripts clearly and concisely for someone maintaining them.",
                "Explain what this script does:\n\n{$this->content}",
            ],
            default => throw new \InvalidArgumentException("Unknown AI mode: {$mode}"),
        };

        $requestId = (string) Str::uuid();

        StreamClaudeCompletionJob::dispatch($requestId, $system, $user);

        return $requestId;
    }

    public function addVariable(): void
    {
        $this->variables[] = [
            'name' => '',
            'type' => ScriptVariableType::Text->value,
            'description' => '',
            'default_value' => '',
            'required' => false,
        ];
    }

    public function removeVariable(int $index): void
    {
        unset($this->variables[$index]);
        $this->variables = array_values($this->variables);
    }

    public function save(): void
    {
        $this->authorize('scripts.edit');

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'language' => 'required|string',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'variables.*.name' => 'nullable|string',
        ]);

        $tags = collect(explode(',', $this->tagsInput))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->values()
            ->all();

        $variables = collect($this->variables)
            ->filter(fn ($v) => filled($v['name'] ?? null))
            ->values()
            ->all();

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'language' => $validated['language'],
            'content' => $validated['content'] ?? '',
            'tags' => $tags,
            'variables' => $variables,
        ];

        if ($this->script) {
            $this->script->update($attributes);
        } else {
            $this->script = ScriptDefinition::create($attributes);
        }

        $this->dispatch('saved');

        $this->redirect(route('scripts.index'), navigate: true);
    }
}; ?>

<div
    class="p-6 max-w-4xl"
    x-data="{ dirty: false }"
    x-on:input.capture="dirty = true"
    x-on:change.capture="dirty = true"
    x-on:saved.window="dirty = false"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-semibold text-gray-900">{{ $this->title }}</h1>
        <div class="flex gap-2">
            <button type="button"
                    x-on:click="(!dirty || confirm('You have unsaved changes. Discard them?')) && Livewire.navigate('{{ route('scripts.index') }}')"
                    class="px-4 py-2 text-sm rounded-md border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </button>
            <button wire:click="save" type="button"
                    class="px-4 py-2 text-sm rounded-md bg-gray-900 text-white hover:bg-gray-700">
                Save
            </button>
        </div>
    </div>

    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-6 -mb-px">
            @foreach (['general' => 'General', 'code' => 'Code', 'variables' => 'Variables'] as $tab => $label)
                <button
                    type="button"
                    wire:click="$set('activeTab', '{{ $tab }}')"
                    class="py-2 border-b-2 text-sm font-medium {{ $activeTab === $tab ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div @if ($activeTab !== 'general') style="display:none" @endif>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Language</label>
                    <select wire:model.live="language" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        @foreach ($this->languages as $lang)
                            <option value="{{ $lang->value }}">{{ $lang->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Tags <span class="text-gray-400 font-normal">(comma-separated)</span></label>
                    <input type="text" wire:model="tagsInput" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="system, diagnostics, linux" />
                </div>
            </div>
        </div>

        <div @if ($activeTab !== 'code') style="display:none" @endif>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2">
                    <x-code-editor
                        id="script-content-editor"
                        wire:key="editor-{{ $script?->id ?? 'new' }}"
                        :value="$content"
                        :language="$this->codeMirrorMode"
                        x-on:language-changed.window="setLanguage($event.detail.mode)"
                    />
                </div>
                <div
                    class="border border-gray-200 rounded-md p-4 text-sm"
                    x-data="aiAssistant('script-content-editor')"
                    x-on:destroy="destroy()"
                    wire:ignore
                >
                    <p class="font-medium text-gray-700 mb-2">AI Assistant</p>

                    <div class="flex gap-1 mb-3">
                        <button type="button" x-on:click="mode = 'generate'" :class="mode === 'generate' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded text-xs">Generate</button>
                        <button type="button" x-on:click="mode = 'improve'" :class="mode === 'improve' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded text-xs">Improve</button>
                        <button type="button" x-on:click="mode = 'explain'" :class="mode === 'explain' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'" class="px-2 py-1 rounded text-xs">Explain</button>
                    </div>

                    <textarea
                        x-show="mode !== 'explain'"
                        x-model="prompt"
                        rows="3"
                        placeholder="Describe what you want..."
                        class="w-full rounded-md border-gray-300 shadow-sm text-xs"
                    ></textarea>

                    <button
                        type="button"
                        x-on:click="run()"
                        x-bind:disabled="generating"
                        class="mt-2 w-full px-3 py-1.5 bg-gray-900 text-white text-xs rounded-md disabled:opacity-50"
                    >
                        <span x-show="!generating">Run</span>
                        <span x-show="generating">Generating...</span>
                    </button>

                    <p x-show="error" x-text="error" class="text-red-600 text-xs mt-2"></p>

                    <div x-show="mode === 'explain' && explanation" class="mt-3 p-2 bg-gray-50 rounded text-xs whitespace-pre-wrap max-h-64 overflow-y-auto" x-text="explanation"></div>
                </div>
            </div>
        </div>

        <div @if ($activeTab !== 'variables') style="display:none" @endif>
            <div class="space-y-3">
                @foreach ($variables as $i => $variable)
                    <div wire:key="var-{{ $i }}" class="flex flex-wrap gap-2 items-center border border-gray-100 rounded-md p-3">
                        <div class="flex-1 min-w-[120px]">
                            <input type="text" wire:model="variables.{{ $i }}.name" placeholder="name" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div class="w-32">
                            <select wire:model="variables.{{ $i }}.type" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                @foreach ($this->variableTypes as $type)
                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-[140px]">
                            <input type="text" wire:model="variables.{{ $i }}.description" placeholder="description" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div class="w-28">
                            <input type="text" wire:model="variables.{{ $i }}.default_value" placeholder="default" class="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div class="flex items-center justify-center" title="Required">
                            <input type="checkbox" wire:model="variables.{{ $i }}.required" class="rounded border-gray-300" />
                        </div>
                        <div class="flex items-center justify-center">
                            <button type="button" wire:click="removeVariable({{ $i }})" class="text-red-600 text-sm">&times;</button>
                        </div>
                    </div>
                @endforeach

                <button type="button" wire:click="addVariable" class="text-sm text-indigo-600 hover:text-indigo-800">
                    + Add Variable
                </button>
            </div>
        </div>
    </div>
</div>
