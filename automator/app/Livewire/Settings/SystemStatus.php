<?php

namespace App\Livewire\Settings;

use App\Services\DependencyCheckService;
use Livewire\Component;

class SystemStatus extends Component
{
    public array $runtimes = [];

    public array $database = [];

    public array $appInfo = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $service = app(DependencyCheckService::class);

        $this->runtimes = $service->checkRuntimes();
        $this->database = $service->databaseStatus();
        $this->appInfo = $service->appInfo();
    }

    public function render()
    {
        return view('livewire.settings.system-status');
    }
}
