<?php

namespace App\Console\Commands;

use App\Models\RunnerEnrollmentToken;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('automator:generate-runner-token {--ttl=60 : Minutes until the token expires}')]
#[Description('Generate a one-time runner enrollment token (for scripted/unattended installs)')]
class GenerateRunnerToken extends Command
{
    public function handle(): int
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Admin'))->first();

        $token = RunnerEnrollmentToken::issue($admin?->id, (int) $this->option('ttl'));

        // Bare token on its own line so install scripts can capture it with
        // simple command substitution.
        $this->line($token);

        return self::SUCCESS;
    }
}
