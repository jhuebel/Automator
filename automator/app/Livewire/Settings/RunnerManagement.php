<?php

namespace App\Livewire\Settings;

use App\Enums\ScriptLanguage;
use App\Models\AppSetting;
use App\Models\Runner;
use App\Models\RunnerEnrollmentToken;
use App\Models\RunnerRelease;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RunnerManagement extends Component
{
    public ?string $generatedToken = null;

    public ?string $confirmingDeleteId = null;

    public ?string $expandedId = null;

    public bool $autoUpdateEnabled = false;

    public function mount(): void
    {
        $this->autoUpdateEnabled = AppSetting::current()->runner_auto_update_enabled;
    }

    #[Computed]
    public function runners()
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

    public function generateToken(): void
    {
        $this->authorize('settings.manage');

        $this->generatedToken = RunnerEnrollmentToken::issue(Auth::id());
    }

    public function dismissToken(): void
    {
        $this->generatedToken = null;
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

        $runner = Runner::findOrFail($this->confirmingDeleteId);
        $runner->tokens()->delete();
        $runner->delete();

        $this->confirmingDeleteId = null;
        unset($this->runners);
    }

    public function toggleDisabled(string $id): void
    {
        $this->authorize('settings.manage');

        $runner = Runner::findOrFail($id);
        $runner->status = $runner->status === 'disabled' ? 'offline' : 'disabled';
        $runner->save();

        unset($this->runners);
    }

    public function toggleAutoUpdate(): void
    {
        $this->authorize('settings.manage');

        $this->autoUpdateEnabled = ! $this->autoUpdateEnabled;
        AppSetting::current()->update(['runner_auto_update_enabled' => $this->autoUpdateEnabled]);
    }

    /**
     * The released build this runner could move to, if any — null if it's
     * already current, has no eligible release, or hasn't reported an
     * os/arch yet.
     */
    public function availableUpdateFor(Runner $runner): ?RunnerRelease
    {
        if (! $runner->os || ! $runner->arch) {
            return null;
        }

        $release = RunnerRelease::latestFor($runner->os, $runner->arch);

        return $release && $release->version !== $runner->version ? $release : null;
    }

    public function render()
    {
        return view('livewire.settings.runner-management');
    }
}
