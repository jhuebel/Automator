<?php

use App\Models\Runner;
use App\Models\RunnerGroup;
use App\Models\ScheduledJob;
use App\Models\ScriptDefinition;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Scheduled Jobs'])] class extends Component
{
    public bool $isEditing = false;

    public ?string $editingId = null;

    public string $name = '';

    public ?string $scriptId = null;

    public string $cronExpression = '';

    public string $preferredRunnerId = '';

    public bool $isEnabled = true;

    public ?string $confirmingDeleteId = null;

    private const PRESETS = [
        'Every minute' => '* * * * *',
        'Every 5 minutes' => '*/5 * * * *',
        'Every 15 minutes' => '*/15 * * * *',
        'Hourly' => '0 * * * *',
        'Daily at midnight' => '0 0 * * *',
        'Weekly (Sun 00:00)' => '0 0 * * 0',
    ];

    #[Computed]
    public function jobs()
    {
        return ScheduledJob::with('script', 'preferredRunner', 'preferredRunnerGroup')->orderBy('name')->get();
    }

    #[Computed]
    public function runners()
    {
        $language = $this->selectedScript?->language;

        return Runner::orderBy('name')->get()
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

    #[Computed]
    public function scripts()
    {
        return ScriptDefinition::orderBy('name')->get();
    }

    #[Computed]
    public function selectedScript(): ?ScriptDefinition
    {
        return $this->scriptId ? ScriptDefinition::find($this->scriptId) : null;
    }

    #[Computed]
    public function presets(): array
    {
        return self::PRESETS;
    }

    #[Computed]
    public function nextRunPreview(): ?string
    {
        $next = ScheduledJob::nextOccurrence($this->cronExpression);

        return $next?->format('Y-m-d H:i:s').' UTC';
    }

    public function newJob(): void
    {
        $this->authorize('jobs.manage');

        $this->reset(['editingId', 'name', 'scriptId', 'cronExpression', 'preferredRunnerId']);
        $this->isEnabled = true;
        $this->isEditing = true;
    }

    public function editJob(string $id): void
    {
        $this->authorize('jobs.manage');

        $job = ScheduledJob::findOrFail($id);
        $this->editingId = $job->id;
        $this->name = $job->name;
        $this->scriptId = $job->script_id;
        $this->cronExpression = $job->cron_expression;
        $this->preferredRunnerId = match (true) {
            (bool) $job->preferred_runner_id => "r:{$job->preferred_runner_id}",
            (bool) $job->preferred_runner_group_id => "g:{$job->preferred_runner_group_id}",
            default => '',
        };
        $this->isEnabled = $job->is_enabled;
        $this->isEditing = true;
    }

    public function cancelEdit(): void
    {
        $this->isEditing = false;
    }

    public function applyPreset(string $cron): void
    {
        $this->cronExpression = $cron;
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

    public function updatedScriptId(): void
    {
        // The previously-pinned runner may not support the newly-selected
        // script's language — reset to Auto rather than silently keeping an
        // invalid pick.
        $this->preferredRunnerId = '';
    }

    public function save(): void
    {
        $this->authorize('jobs.manage');

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'scriptId' => 'required|exists:script_definitions,id',
            'cronExpression' => 'required|string',
        ]);

        if (ScheduledJob::nextOccurrence($validated['cronExpression']) === null) {
            $this->addError('cronExpression', 'Invalid cron expression.');

            return;
        }

        [$runnerId, $groupId] = $this->parseRunnerTarget($this->preferredRunnerId);
        $script = ScriptDefinition::find($validated['scriptId']);

        if ($runnerId) {
            $runner = Runner::find($runnerId);

            if (! $runner || ! $runner->supportsLanguage($script->language)) {
                $this->addError('preferredRunnerId', 'That runner does not report '.$script->language->label().' as available.');

                return;
            }
        } elseif ($groupId) {
            $group = RunnerGroup::with('runners')->find($groupId);

            if (! $group || ! $group->supportsLanguage($script->language)) {
                $this->addError('preferredRunnerId', 'That runner group does not have a member that reports '.$script->language->label().' as available.');

                return;
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'script_id' => $validated['scriptId'],
            'cron_expression' => $validated['cronExpression'],
            'preferred_runner_id' => $runnerId,
            'preferred_runner_group_id' => $groupId,
            'is_enabled' => $this->isEnabled,
        ];

        if ($this->editingId) {
            $job = ScheduledJob::findOrFail($this->editingId);
            $job->fill($attributes);
            $job->refreshNextRunAt();
            $job->save();
        } else {
            $job = new ScheduledJob($attributes);
            $job->refreshNextRunAt();
            $job->save();
        }

        $this->isEditing = false;
        unset($this->jobs);
    }

    public function toggleEnabled(string $id): void
    {
        $this->authorize('jobs.manage');

        $job = ScheduledJob::findOrFail($id);
        $job->is_enabled = ! $job->is_enabled;
        $job->refreshNextRunAt();
        $job->save();

        unset($this->jobs);
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
        $this->authorize('jobs.manage');

        ScheduledJob::findOrFail($this->confirmingDeleteId)->delete();
        $this->confirmingDeleteId = null;
        unset($this->jobs);
    }
}; ?>

<div class="p-6 space-y-4">
    <div class="flex justify-end">
        @can('jobs.manage')
            @if (! $isEditing)
                <button wire:click="newJob" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                    + New Job
                </button>
            @endif
        @endcan
    </div>

    @if ($isEditing)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    @error('name') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Script</label>
                    <select wire:model.live="scriptId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">Select a script...</option>
                        @foreach ($this->scripts as $script)
                            <option value="{{ $script->id }}">{{ $script->name }}</option>
                        @endforeach
                    </select>
                    @error('scriptId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Cron Expression</label>
                <input type="text" wire:model.live="cronExpression" placeholder="* * * * *" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" />
                @error('cronExpression') <span class="text-xs text-red-600">{{ $message }}</span> @enderror

                <div class="flex flex-wrap gap-1 mt-2">
                    @foreach ($this->presets as $label => $cron)
                        <button type="button" wire:click="applyPreset('{{ $cron }}')" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2 py-1 rounded">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                @if ($cronExpression)
                    <p class="text-xs text-gray-500 mt-2">
                        @if ($this->nextRunPreview)
                            Next run: <span class="font-medium text-gray-700">{{ $this->nextRunPreview }}</span>
                        @else
                            <span class="text-red-600">Could not parse this cron expression.</span>
                        @endif
                    </p>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Runner</label>
                <select wire:model="preferredRunnerId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Auto (any online runner)</option>
                    @if ($this->runnerGroups->isNotEmpty())
                        <optgroup label="Runner Groups">
                            @foreach ($this->runnerGroups as $group)
                                <option value="g:{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if ($this->runners->isNotEmpty())
                        <optgroup label="Individual Runners">
                            @foreach ($this->runners as $runner)
                                <option value="r:{{ $runner->id }}">{{ $runner->name }} ({{ ucfirst($runner->status) }})</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <p class="text-xs text-gray-500 mt-1">Only runners that report the selected script's language as available are listed. Pin this job to a specific runner, or leave on Auto to use the least-busy online runner.</p>
                @if ($this->selectedScript && $this->runners->isEmpty())
                    <p class="text-sm text-amber-600 mt-1">
                        No registered runner reports {{ $this->selectedScript->language->label() }} as available — this job will fail whenever it runs.
                    </p>
                @endif
                @error('preferredRunnerId') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="isEnabled" class="rounded border-gray-300" />
                <span class="text-sm text-gray-700">Enabled</span>
            </label>

            <div class="flex gap-2">
                <button wire:click="save" class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-700">Save Job</button>
                <button wire:click="cancelEdit" class="px-4 py-2 bg-white border border-gray-300 text-sm rounded-md text-gray-700">Cancel</button>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Name</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Script</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Cron</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Runner</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Next Run</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Last Run</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Enabled</th>
                    <th class="px-4 py-2 text-left font-medium text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($this->jobs as $job)
                    <tr wire:key="job-{{ $job->id }}">
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $job->name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $job->script?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500 font-mono text-xs">{{ $job->cron_expression }}</td>
                        <td class="px-4 py-2 text-gray-500">
                            {{ $job->preferredRunnerGroup ? "Group: {$job->preferredRunnerGroup->name}" : ($job->preferredRunner?->name ?? 'Auto') }}
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $job->next_run_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">
                            @if ($job->last_run_at)
                                {{ $job->last_run_at->diffForHumans() }}
                                <span class="{{ $job->last_run_succeeded ? 'text-green-600' : 'text-red-600' }}">
                                    (exit {{ $job->last_exit_code }})
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            @can('jobs.manage')
                                <button wire:click="toggleEnabled('{{ $job->id }}')"
                                        class="relative inline-flex h-5 w-9 items-center rounded-full {{ $job->is_enabled ? 'bg-green-500' : 'bg-gray-300' }}">
                                    <span class="inline-block h-3 w-3 transform rounded-full bg-white transition {{ $job->is_enabled ? 'translate-x-5' : 'translate-x-1' }}"></span>
                                </button>
                            @else
                                {{ $job->is_enabled ? 'Yes' : 'No' }}
                            @endcan
                        </td>
                        <td class="px-4 py-2 space-x-2 whitespace-nowrap">
                            @can('scripts.run')
                                <a href="{{ route('runner.index', ['script' => $job->script_id]) }}" wire:navigate class="text-green-600 hover:text-green-800">Run Now</a>
                            @endcan
                            @can('jobs.manage')
                                <button wire:click="editJob('{{ $job->id }}')" class="text-gray-600 hover:text-gray-900">Edit</button>
                                <button wire:click="confirmDelete('{{ $job->id }}')" class="text-red-600 hover:text-red-800">Delete</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-6 text-center text-gray-500">No scheduled jobs yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($confirmingDeleteId)
        <div class="fixed inset-0 bg-gray-500/75 flex items-center justify-center z-50" wire:click.self="cancelDelete">
            <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
                <h2 class="text-lg font-medium text-gray-900">Delete this scheduled job?</h2>
                <p class="mt-1 text-sm text-gray-600">This cannot be undone.</p>
                <div class="mt-6 flex justify-end gap-3">
                    <button wire:click="cancelDelete" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700">Cancel</button>
                    <button wire:click="delete" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>
