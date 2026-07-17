<?php

namespace Tests\Feature;

use App\Enums\ScriptLanguage;
use App\Models\Runner;
use App\Models\RunnerGroup;
use App\Models\ScriptDefinition;
use App\Models\ScriptExecutionResult;
use App\Services\RunnerAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunnerGroupTest extends TestCase
{
    use RefreshDatabase;

    private function makeRunner(array $overrides = []): Runner
    {
        return Runner::create(array_merge([
            'name' => 'runner-'.uniqid(),
            'os' => 'linux',
            'arch' => 'amd64',
            'status' => 'online',
            'last_seen_at' => now(),
            'runtimes' => [],
        ], $overrides));
    }

    private function runtimesFor(ScriptLanguage ...$languages): array
    {
        return collect($languages)->map(fn ($language) => [
            'name' => $language->runtimeName(),
            'available' => true,
        ])->all();
    }

    public function test_group_supports_language_if_any_member_does(): void
    {
        $bashOnly = $this->makeRunner(['runtimes' => $this->runtimesFor(ScriptLanguage::Bash)]);
        $pythonOnly = $this->makeRunner(['runtimes' => $this->runtimesFor(ScriptLanguage::Python)]);

        $group = RunnerGroup::create(['name' => 'mixed-group']);
        $group->runners()->attach([$bashOnly->id, $pythonOnly->id]);
        $group->load('runners');

        $this->assertTrue($group->supportsLanguage(ScriptLanguage::Bash));
        $this->assertTrue($group->supportsLanguage(ScriptLanguage::Python));
        $this->assertFalse($group->supportsLanguage(ScriptLanguage::Terraform));
    }

    public function test_group_satisfies_tags_if_any_member_does(): void
    {
        $windowsRunner = $this->makeRunner(['tags' => ['windows']]);
        $linuxRunner = $this->makeRunner(['tags' => ['linux']]);

        $group = RunnerGroup::create(['name' => 'tagged-group']);
        $group->runners()->attach([$windowsRunner->id, $linuxRunner->id]);
        $group->load('runners');

        $this->assertTrue($group->satisfiesTags(['windows']));
        $this->assertTrue($group->satisfiesTags(['linux']));
        $this->assertFalse($group->satisfiesTags(['terraform']));
    }

    private function makeExecution(ScriptLanguage $language): ScriptExecutionResult
    {
        $script = ScriptDefinition::create([
            'name' => 'test-script',
            'language' => $language,
            'content' => 'echo hi',
            'tags' => [],
            'variables' => [],
        ]);

        return ScriptExecutionResult::create([
            'script_id' => $script->id,
            'script_name' => $script->name,
            'language' => $language,
            'started_at' => now(),
            'output' => [],
        ]);
    }

    public function test_assign_to_group_picks_least_busy_eligible_member(): void
    {
        $busy = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'current_job_count' => 1,
            'max_concurrent_jobs' => 5,
        ]);
        $idle = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'current_job_count' => 0,
            'max_concurrent_jobs' => 5,
        ]);

        $group = RunnerGroup::create(['name' => 'pool']);
        $group->runners()->attach([$busy->id, $idle->id]);

        $result = $this->makeExecution(ScriptLanguage::Bash);
        app(RunnerAssignmentService::class)->assign($result, null, null, $group->id);

        $this->assertSame($idle->id, $result->fresh()->runner_id);
    }

    public function test_assign_to_group_skips_offline_and_at_capacity_members(): void
    {
        $offline = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'status' => 'offline',
        ]);
        $atCapacity = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'current_job_count' => 1,
            'max_concurrent_jobs' => 1,
        ]);
        $eligible = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'current_job_count' => 0,
            'max_concurrent_jobs' => 1,
        ]);

        $group = RunnerGroup::create(['name' => 'pool']);
        $group->runners()->attach([$offline->id, $atCapacity->id, $eligible->id]);

        $result = $this->makeExecution(ScriptLanguage::Bash);
        app(RunnerAssignmentService::class)->assign($result, null, null, $group->id);

        $this->assertSame($eligible->id, $result->fresh()->runner_id);
    }

    public function test_assign_to_group_fails_clearly_when_no_member_qualifies(): void
    {
        $wrongLanguage = $this->makeRunner(['runtimes' => $this->runtimesFor(ScriptLanguage::Python)]);

        $group = RunnerGroup::create(['name' => 'python-pool']);
        $group->runners()->attach($wrongLanguage->id);

        $result = $this->makeExecution(ScriptLanguage::Bash);
        app(RunnerAssignmentService::class)->assign($result, null, null, $group->id);

        $result->refresh();
        $this->assertSame(-1, $result->exit_code);
        $this->assertStringContainsString('python-pool', $result->output[0]['text']);
        $this->assertStringContainsString('Bash', $result->output[0]['text']);
    }

    public function test_assign_to_group_fails_clearly_when_group_no_longer_exists(): void
    {
        $result = $this->makeExecution(ScriptLanguage::Bash);
        app(RunnerAssignmentService::class)->assign($result, null, null, 'nonexistent-group-id');

        $result->refresh();
        $this->assertSame(-1, $result->exit_code);
        $this->assertStringContainsString('no longer exists', $result->output[0]['text']);
    }

    public function test_assign_to_group_still_honors_required_tags(): void
    {
        $wrongTag = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'tags' => ['east'],
        ]);
        $rightTag = $this->makeRunner([
            'runtimes' => $this->runtimesFor(ScriptLanguage::Bash),
            'tags' => ['west'],
        ]);

        $group = RunnerGroup::create(['name' => 'tagged-pool']);
        $group->runners()->attach([$wrongTag->id, $rightTag->id]);

        $result = $this->makeExecution(ScriptLanguage::Bash);
        app(RunnerAssignmentService::class)->assign($result, ['west'], null, $group->id);

        $this->assertSame($rightTag->id, $result->fresh()->runner_id);
    }
}
