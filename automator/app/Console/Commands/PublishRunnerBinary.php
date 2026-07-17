<?php

namespace App\Console\Commands;

use App\Models\RunnerRelease;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Signature('automator:publish-runner-binary {path : Path to the built automator-runner binary} {--os= : linux or windows} {--arch=amd64} {--runner-version= : Version string this binary reports}')]
#[Description('Store a runner binary as a draft release — not visible to the fleet until released with automator:release-runner-binary')]
class PublishRunnerBinary extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path');
        $os = $this->option('os');
        $arch = $this->option('arch');
        $version = $this->option('runner-version');

        if (! is_file($path)) {
            $this->error("No such file: {$path}");

            return self::FAILURE;
        }

        if (! in_array($os, ['linux', 'windows'], true)) {
            $this->error('--os must be "linux" or "windows"');

            return self::FAILURE;
        }

        if (blank($version)) {
            $this->error('--runner-version is required');

            return self::FAILURE;
        }

        // Reuse the existing release's id (and storage path) if this exact
        // version/os/arch tuple was published before — a republish is a
        // conscious overwrite, not a new row.
        $existing = RunnerRelease::query()
            ->where('version', $version)
            ->where('os', $os)
            ->where('arch', $arch)
            ->first();

        $id = $existing->id ?? (string) Str::ulid();
        $filename = $os === 'windows' ? 'automator-runner.exe' : 'automator-runner';
        $storagePath = "runner-releases/{$id}/{$filename}";

        Storage::disk('local')->put($storagePath, file_get_contents($path));

        RunnerRelease::updateOrCreate(
            ['version' => $version, 'os' => $os, 'arch' => $arch],
            [
                'id' => $id,
                'checksum_sha256' => hash_file('sha256', $path),
                'storage_path' => $storagePath,
                'size_bytes' => filesize($path),
                'is_released' => false,
            ]
        );

        $this->info("Published {$version} ({$os}/{$arch}) — {$storagePath}");
        $this->line('Not yet released. Run automator:release-runner-binary to make it live to the fleet.');

        return self::SUCCESS;
    }
}
