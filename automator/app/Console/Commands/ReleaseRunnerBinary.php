<?php

namespace App\Console\Commands;

use App\Models\RunnerRelease;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automator:release-runner-binary {version} {--os= : linux or windows} {--arch=amd64}')]
#[Description('Promote an already-published runner binary to live — the only way a build becomes visible to the fleet')]
class ReleaseRunnerBinary extends Command
{
    public function handle(): int
    {
        $version = $this->argument('version');
        $os = $this->option('os');
        $arch = $this->option('arch');

        if (! in_array($os, ['linux', 'windows'], true)) {
            $this->error('--os must be "linux" or "windows"');

            return self::FAILURE;
        }

        $release = RunnerRelease::query()
            ->where('version', $version)
            ->where('os', $os)
            ->where('arch', $arch)
            ->first();

        if (! $release) {
            $this->error("No published release found for {$version} ({$os}/{$arch}). Run automator:publish-runner-binary first.");

            return self::FAILURE;
        }

        $release->forceFill(['is_released' => true, 'released_at' => now()])->save();

        $this->info("Released {$version} ({$os}/{$arch}) — the fleet will offer it starting with their next heartbeat.");

        return self::SUCCESS;
    }
}
