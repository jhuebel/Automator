<?php

namespace App\Livewire\Settings;

use App\Models\Runner;
use App\Models\RunnerEnrollmentToken;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RunnerManagement extends Component
{
    public ?string $generatedToken = null;

    public ?string $confirmingDeleteId = null;

    #[Computed]
    public function runners()
    {
        return Runner::orderBy('name')->get();
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

    public function render()
    {
        return view('livewire.settings.runner-management');
    }
}
