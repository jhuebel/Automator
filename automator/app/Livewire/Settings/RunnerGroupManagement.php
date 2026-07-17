<?php

namespace App\Livewire\Settings;

use App\Enums\ScriptLanguage;
use App\Models\AuditLog;
use App\Models\Runner;
use App\Models\RunnerGroup;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RunnerGroupManagement extends Component
{
    public ?string $expandedId = null;

    public bool $isEditing = false;

    public ?string $editingId = null;

    public string $name = '';

    public string $description = '';

    public array $selectedRunnerIds = [];

    public ?string $confirmingDeleteId = null;

    #[Computed]
    public function groups()
    {
        return RunnerGroup::with('runners')->orderBy('name')->get();
    }

    #[Computed]
    public function allRunners()
    {
        return Runner::orderBy('name')->get();
    }

    #[Computed]
    public function languages(): array
    {
        return ScriptLanguage::cases();
    }

    public function toggleExpand(string $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function newGroup(): void
    {
        $this->authorize('settings.manage');

        $this->reset(['editingId', 'name', 'description', 'selectedRunnerIds']);
        $this->isEditing = true;
    }

    public function editGroup(string $id): void
    {
        $this->authorize('settings.manage');

        $group = RunnerGroup::with('runners')->findOrFail($id);
        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->description = $group->description ?? '';
        $this->selectedRunnerIds = $group->runners->pluck('id')->all();
        $this->isEditing = true;
    }

    public function cancelEdit(): void
    {
        $this->isEditing = false;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('runner_groups', 'name')->ignore($this->editingId)],
            'description' => 'nullable|string|max:1000',
        ]);

        $group = $this->editingId ? RunnerGroup::findOrFail($this->editingId) : new RunnerGroup;
        $group->fill($validated);
        $group->save();
        $group->runners()->sync($this->selectedRunnerIds);

        AuditLog::record('RunnerGroup.'.($this->editingId ? 'Updated' : 'Created'), $group->name);

        $this->isEditing = false;
        unset($this->groups);
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
        $this->authorize('settings.manage');

        $group = RunnerGroup::findOrFail($this->confirmingDeleteId);
        $name = $group->name;
        $group->delete();

        AuditLog::record('RunnerGroup.Deleted', $name);

        $this->confirmingDeleteId = null;
        unset($this->groups);
    }

    public function render()
    {
        return view('livewire.settings.runner-group-management');
    }
}
