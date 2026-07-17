<?php

use App\Models\Runner;
use App\Models\RunnerGroup;
use App\Models\ScriptDefinition;
use App\Models\ScriptExecutionResult;
use App\Services\RunnerAssignmentService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Run Script'])] class extends Component
{
    #[Url(as: 'script')]
    public ?string $scriptId = null;

    public string $search = '';

    public array $variableValues = [];

    public string $runnerId = '';

    public ?string $executionId = null;

    public ?int $exitCode = null;

    public bool $isRunning = false;

    public function mount(): void
    {
        if ($this->scriptId) {
            $this->populateDefaults();
        }
    }

    #[Computed]
    public function scripts()
    {
        return ScriptDefinition::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedScript(): ?ScriptDefinition
    {
        return $this->scriptId ? ScriptDefinition::find($this->scriptId) : null;
    }

    #[Computed]
    public function onlineRunners()
    {
        $language = $this->selectedScript?->language;

        return Runner::where('status', 'online')->orderBy('name')->get()
            ->filter(fn (Runner $runner) => ! $language || $runner->supportsLanguage($language))
            ->values();
    }

    #[Computed]
    public function runnerGroups()
    {
        $language = $this->selectedScript?->language;

        return RunnerGroup::with('runners')->orderBy('name')->get()
            ->filter(fn (RunnerGroup $group) => ! $language || $group->supportsLanguage($language))
            ->values();
    }

    public function selectScript(string $id): void
    {
        $this->scriptId = $id;
        $this->runnerId = '';
        $this->executionId = null;
        $this->exitCode = null;
        $this->isRunning = false;
        $this->populateDefaults();

        // The terminal's output lines live in the scriptTerminal Alpine
        // component (resources/js/script-terminal.js), not in this Livewire
        // component's state — it's marked wire:ignore so per-line output
        // events don't trigger full Livewire re-renders. That div survives
        // a script switch untouched, so it needs an explicit signal to
        // clear itself; otherwise the previous script's output just sits
        // there under the newly-selected script.
        $this->dispatch('script-selected');
    }

    /**
     * The runner picker's value is discriminated: '' = Auto, 'r:<id>' = a
     * specific runner, 'g:<id>' = a runner group. Returns [runnerId, groupId].
     */
    private function parseRunnerTarget(string $value): array
    {
        return match (true) {
            str_starts_with($value, 'r:') => [substr($value, 2), null],
            str_starts_with($value, 'g:') => [null, substr($value, 2)],
            default => [null, null],
        };
    }

    private function populateDefaults(): void
    {
        $this->variableValues = [];
        foreach ($this->selectedScript?->variables ?? [] as $variable) {
            $this->variableValues[$variable['name']] = $variable['default_value'] ?? '';
        }
    }

    public function run(): void
    {
        $this->authorize('scripts.run');

        $script = $this->selectedScript;
        abort_unless($script, 404);

        foreach ($script->variables as $variable) {
            if (($variable['required'] ?? false) && trim((string) ($this->variableValues[$variable['name']] ?? '')) === '') {
                $this->addError('run', "\"{$variable['name']}\" is required.");

                return;
            }
        }

        $result = ScriptExecutionResult::create([
            'script_id' => $script->id,
            'script_name' => $script->name,
            'language' => $script->language,
            'username' => Auth::user()->username,
            'started_at' => now(),
            'output' => [],
        ]);

        [$runnerId, $groupId] = $this->parseRunnerTarget($this->runnerId);
        app(RunnerAssignmentService::class)->assign($result, null, $runnerId, $groupId);

        $this->executionId = $result->id;
        $this->exitCode = null;
        $this->isRunning = true;

        $this->dispatch('execution-started', executionId: $result->id);
    }

    public function markFinished(?int $exitCode): void
    {
        $this->exitCode = $exitCode;
        $this->isRunning = false;
    }

    public function cancel(): void
    {
        if (! $this->executionId) {
            return;
        }

        $result = ScriptExecutionResult::find($this->executionId);
        if ($result) {
            app(RunnerAssignmentService::class)->requestCancel($result);
        }
    }
}; ?>

<div class="p-6 grid grid-cols-1 lg:grid-cols-4 gap-4 h-full">
    <div class="lg:col-span-1 bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col max-h-[70vh]">
        <div class="p-3 border-b border-gray-100">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search scripts..."
                   class="w-full rounded-md border-gray-300 shadow-sm text-sm" />
        </div>
        <div class="overflow-y-auto flex-1">
            @foreach ($this->scripts as $script)
                <button
                    type="button"
                    wire:key="script-opt-{{ $script->id }}"
                    wire:click="selectScript('{{ $script->id }}')"
                    class="w-full text-left px-3 py-2 border-b border-gray-50 hover:bg-gray-50 {{ $scriptId === $script->id ? 'bg-indigo-50' : '' }}"
                >
                    <div class="text-sm font-medium text-gray-900">{{ $script->name }}</div>
                    <div class="text-xs text-gray-500">{{ $script->language->label() }}</div>
                </button>
            @endforeach
        </div>
    </div>

    <div class="lg:col-span-3 flex flex-col gap-4 h-full min-h-0">
        @if ($this->selectedScript)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900">{{ $this->selectedScript->name }}</h2>
                        <p class="text-sm text-gray-500">{{ $this->selectedScript->description }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @unless ($isRunning)
                            <select wire:model="runnerId" class="rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Auto (least busy)</option>
                                @if ($this->runnerGroups->isNotEmpty())
                                    <optgroup label="Runner Groups">
                                        @foreach ($this->runnerGroups as $group)
                                            <option value="g:{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if ($this->onlineRunners->isNotEmpty())
                                    <optgroup label="Individual Runners">
                                        @foreach ($this->onlineRunners as $runner)
                                            <option value="r:{{ $runner->id }}">{{ $runner->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        @endunless
                        @if ($isRunning)
                            <button wire:click="cancel" class="px-4 py-2 text-sm bg-red-600 text-white rounded-md">Cancel</button>
                        @else
                            <button wire:click="run" class="px-4 py-2 text-sm bg-green-600 text-white rounded-md">Run</button>
                        @endif
                    </div>
                </div>

                @error('run') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror

                @if ($this->onlineRunners->isEmpty())
                    <p class="text-sm text-amber-600 mt-2">
                        No online runner currently reports {{ $this->selectedScript->language->label() }} as available — running this script will fail.
                    </p>
                @endif

                @if (! empty($this->selectedScript->variables))
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach ($this->selectedScript->variables as $variable)
                            <div>
                                <label class="block text-xs font-medium text-gray-600">
                                    {{ $variable['name'] }} @if($variable['required'] ?? false) <span class="text-red-500">*</span> @endif
                                </label>
                                @if (($variable['type'] ?? 'Text') === 'Boolean')
                                    <input type="checkbox" wire:model="variableValues.{{ $variable['name'] }}" class="mt-1 rounded border-gray-300" />
                                @else
                                    <input
                                        type="{{ ($variable['type'] ?? 'Text') === 'Number' ? 'number' : 'text' }}"
                                        wire:model="variableValues.{{ $variable['name'] }}"
                                        placeholder="{{ $variable['description'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                    />
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($executionId)
                    <div class="mt-3 text-sm text-gray-600">
                        @if ($isRunning)
                            <span class="inline-flex items-center gap-1 text-blue-600">
                                <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                Running...
                            </span>
                        @elseif ($exitCode !== null)
                            <span class="{{ $exitCode === 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                Finished — exit code {{ $exitCode }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <div
                x-data="scriptTerminal()"
                x-init="init()"
                x-on:execution-started.window="subscribe($event.detail.executionId)"
                x-on:script-selected.window="clear()"
                x-on:destroy="destroy()"
                wire:ignore
                class="bg-gray-900 rounded-lg shadow-sm border border-gray-800 p-4 font-mono text-xs text-gray-100 flex-1 min-h-0 overflow-y-auto"
                x-ref="output"
            >
                <template x-for="(line, i) in lines" :key="i">
                    <div class="whitespace-pre" :class="line.isError ? 'text-red-400' : 'text-gray-200'" x-html="line.html"></div>
                </template>
                <div x-show="lines.length === 0" class="text-gray-500">No output yet.</div>
            </div>
        @else
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-gray-500 text-sm">
                Select a script from the list to run it.
            </div>
        @endif
    </div>
</div>
