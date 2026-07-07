<?php

namespace App\Livewire\Settings;

use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AuditLogViewer extends Component
{
    public string $filter = 'all';

    private const FILTER_PREFIXES = [
        'login' => 'Auth.',
        'script' => 'Script.',
        'job' => 'Job.',
        'user' => 'User.',
        'settings' => 'Settings.',
    ];

    #[Computed]
    public function logs()
    {
        return AuditLog::query()
            ->when($this->filter !== 'all', function ($q) {
                $prefix = self::FILTER_PREFIXES[$this->filter] ?? null;
                if ($prefix) {
                    $q->where('action', 'like', "{$prefix}%");
                }
            })
            ->latest('created_at')
            ->limit(200)
            ->get();
    }

    public function render()
    {
        return view('livewire.settings.audit-log-viewer');
    }
}
