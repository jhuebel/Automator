<?php

use App\Models\ScheduledJob;
use App\Models\ScriptDefinition;
use App\Models\ScriptExecutionResult;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Dashboard'])] class extends Component
{
    #[Computed]
    public function scriptCount(): int
    {
        return ScriptDefinition::count();
    }

    #[Computed]
    public function successfulRuns(): int
    {
        return ScriptExecutionResult::where('exit_code', 0)->count();
    }

    #[Computed]
    public function failedRuns(): int
    {
        return ScriptExecutionResult::whereNotNull('completed_at')->where('exit_code', '!=', 0)->count();
    }

    #[Computed]
    public function runningNow(): int
    {
        return ScriptExecutionResult::whereNull('completed_at')->count();
    }

    #[Computed]
    public function activeSchedules(): int
    {
        return ScheduledJob::where('is_enabled', true)->count();
    }

    #[Computed]
    public function recentExecutions()
    {
        return ScriptExecutionResult::orderByDesc('started_at')->limit(5)->get();
    }

    #[Computed]
    public function dailyChartData(): array
    {
        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        $rows = ScriptExecutionResult::query()
            ->selectRaw("date(started_at) as day, sum(case when exit_code = 0 then 1 else 0 end) as successful, sum(case when exit_code is not null and exit_code != 0 then 1 else 0 end) as failed")
            ->where('started_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        return [
            'labels' => $days->map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'))->values()->all(),
            'datasets' => [
                [
                    'label' => 'Successful',
                    'data' => $days->map(fn ($d) => (int) ($rows[$d]->successful ?? 0))->values()->all(),
                    'backgroundColor' => '#22c55e',
                ],
                [
                    'label' => 'Failed',
                    'data' => $days->map(fn ($d) => (int) ($rows[$d]->failed ?? 0))->values()->all(),
                    'backgroundColor' => '#ef4444',
                ],
            ],
        ];
    }

    #[Computed]
    public function languageChartData(): array
    {
        $counts = ScriptDefinition::query()
            ->selectRaw('language, count(*) as total')
            ->groupBy('language')
            ->pluck('total', 'language');

        return [
            'labels' => $counts->keys()->values()->all(),
            'datasets' => [[
                'data' => $counts->values()->all(),
                'backgroundColor' => ['#22c55e', '#3b82f6', '#eab308', '#ef4444', '#a855f7'],
            ]],
        ];
    }
}; ?>

<div class="p-6 space-y-6">
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <a href="{{ route('scripts.index') }}" wire:navigate class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <p class="text-2xl font-semibold text-gray-900">{{ $this->scriptCount }}</p>
            <p class="text-xs text-gray-500 mt-1">Scripts</p>
        </a>
        <a href="{{ route('history.index') }}" wire:navigate class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <p class="text-2xl font-semibold text-green-600">{{ $this->successfulRuns }}</p>
            <p class="text-xs text-gray-500 mt-1">Successful Runs</p>
        </a>
        <a href="{{ route('history.index') }}" wire:navigate class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <p class="text-2xl font-semibold text-red-600">{{ $this->failedRuns }}</p>
            <p class="text-xs text-gray-500 mt-1">Failed Runs</p>
        </a>
        <a href="{{ route('runner.index') }}" wire:navigate class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <p class="text-2xl font-semibold text-blue-600">{{ $this->runningNow }}</p>
            <p class="text-xs text-gray-500 mt-1">Running Now</p>
        </a>
        <a href="{{ route('jobs.index') }}" wire:navigate class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
            <p class="text-2xl font-semibold text-gray-900">{{ $this->activeSchedules }}</p>
            <p class="text-xs text-gray-500 mt-1">Active Schedules</p>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Executions (last 14 days)</p>
            <x-chart-widget
                type="bar"
                :data="$this->dailyChartData"
                :options="['responsive' => true, 'scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]]"
                style="height: 260px"
            />
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <p class="text-sm font-medium text-gray-700 mb-2">Scripts by Language</p>
            <x-chart-widget
                type="pie"
                :data="$this->languageChartData"
                style="height: 260px"
            />
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-4 py-2 bg-gray-50 font-medium text-sm text-gray-700">Recent Executions</div>
        <table class="min-w-full text-sm divide-y divide-gray-100">
            <tbody class="divide-y divide-gray-50">
                @forelse ($this->recentExecutions as $execution)
                    <tr>
                        <td class="px-4 py-2 w-6">
                            @if ($execution->is_running)
                                <span class="inline-block h-2 w-2 rounded-full bg-blue-500 animate-pulse"></span>
                            @elseif ($execution->is_success)
                                <span class="text-green-600">&check;</span>
                            @else
                                <span class="text-red-600">&times;</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 font-medium text-gray-900">{{ $execution->script_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $execution->started_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-gray-500">No executions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
