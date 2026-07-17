<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Runner;
use App\Models\RunnerRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeRunner(string $version = '1.0.0'): Runner
    {
        return Runner::create([
            'name' => 'test-runner-'.uniqid(),
            'os' => 'linux',
            'arch' => 'amd64',
            'version' => $version,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Runner doesn't implement Authenticatable, so Sanctum::actingAs() (which
     * requires it) isn't usable here — authenticate with a real token via
     * the Authorization header instead, the same path a runner uses for
     * every authenticated call in production.
     */
    private function tokenFor(Runner $runner): string
    {
        return $runner->createToken('runner', ['runner'])->plainTextToken;
    }

    private function heartbeat(Runner $runner): array
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($runner))
            ->postJson('/api/runner/heartbeat', [
                'version' => $runner->version,
                'arch' => $runner->arch,
            ])
            ->assertOk()
            ->json();
    }

    public function test_heartbeat_omits_update_when_toggle_is_off(): void
    {
        AppSetting::current()->update(['runner_auto_update_enabled' => false]);
        $runner = $this->makeRunner('1.0.0');

        RunnerRelease::create([
            'version' => '1.1.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'x', 'size_bytes' => 1,
            'is_released' => true, 'released_at' => now(),
        ]);

        $this->assertArrayNotHasKey('update', $this->heartbeat($runner));
    }

    public function test_heartbeat_omits_update_when_no_eligible_release(): void
    {
        AppSetting::current()->update(['runner_auto_update_enabled' => true]);
        $runner = $this->makeRunner('1.0.0');

        $this->assertArrayNotHasKey('update', $this->heartbeat($runner));
    }

    public function test_heartbeat_omits_update_when_release_exists_but_unreleased(): void
    {
        AppSetting::current()->update(['runner_auto_update_enabled' => true]);
        $runner = $this->makeRunner('1.0.0');

        RunnerRelease::create([
            'version' => '1.1.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'x', 'size_bytes' => 1,
            'is_released' => false,
        ]);

        $this->assertArrayNotHasKey('update', $this->heartbeat($runner));
    }

    public function test_heartbeat_omits_update_when_runner_already_current(): void
    {
        AppSetting::current()->update(['runner_auto_update_enabled' => true]);
        $runner = $this->makeRunner('1.1.0');

        RunnerRelease::create([
            'version' => '1.1.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'x', 'size_bytes' => 1,
            'is_released' => true, 'released_at' => now(),
        ]);

        $this->assertArrayNotHasKey('update', $this->heartbeat($runner));
    }

    public function test_heartbeat_includes_update_when_eligible(): void
    {
        AppSetting::current()->update(['runner_auto_update_enabled' => true]);
        $runner = $this->makeRunner('1.0.0');

        $release = RunnerRelease::create([
            'version' => '1.1.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'x', 'size_bytes' => 42,
            'is_released' => true, 'released_at' => now(),
        ]);

        $body = $this->heartbeat($runner);

        $this->assertArrayHasKey('update', $body);
        $this->assertSame('1.1.0', $body['update']['version']);
        $this->assertSame($release->checksum_sha256, $body['update']['checksum_sha256']);
        $this->assertSame(42, $body['update']['size_bytes']);
    }

    public function test_download_returns_404_for_unreleased_release(): void
    {
        $runner = $this->makeRunner();

        $release = RunnerRelease::create([
            'version' => '1.1.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'runner-releases/x/automator-runner', 'size_bytes' => 1,
            'is_released' => false,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($runner))
            ->get("/api/runner/releases/{$release->id}/download")
            ->assertNotFound();
    }

    public function test_latest_for_picks_most_recently_released_row(): void
    {
        RunnerRelease::create([
            'version' => '1.0.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'x', 'size_bytes' => 1,
            'is_released' => true, 'released_at' => now()->subDay(),
        ]);

        $newest = RunnerRelease::create([
            'version' => '1.2.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('b', 64), 'storage_path' => 'y', 'size_bytes' => 1,
            'is_released' => true, 'released_at' => now(),
        ]);

        RunnerRelease::create([
            'version' => '1.1.0', 'os' => 'linux', 'arch' => 'amd64',
            'checksum_sha256' => str_repeat('c', 64), 'storage_path' => 'z', 'size_bytes' => 1,
            'is_released' => false,
        ]);

        $this->assertSame($newest->id, RunnerRelease::latestFor('linux', 'amd64')->id);
    }
}
