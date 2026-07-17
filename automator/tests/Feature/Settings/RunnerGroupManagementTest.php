<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\RunnerGroupManagement;
use App\Models\Runner;
use App\Models\RunnerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RunnerGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * UserFactory doesn't set 'username' (a pre-existing gap unrelated to
     * this feature — the users table requires it NOT NULL), so it's passed
     * explicitly here rather than relying on the factory's defaults.
     */
    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge(['username' => 'user-'.uniqid()], $overrides));
    }

    private function makeAdmin(): User
    {
        Permission::findOrCreate('settings.manage');

        $user = $this->makeUser();
        $user->givePermissionTo('settings.manage');

        return $user;
    }

    private function makeRunner(string $name): Runner
    {
        return Runner::create([
            'name' => $name,
            'os' => 'linux',
            'arch' => 'amd64',
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    public function test_create_group_with_members_persists_pivot_rows(): void
    {
        $this->actingAs($this->makeAdmin());

        $runnerA = $this->makeRunner('runner-a');
        $runnerB = $this->makeRunner('runner-b');

        Livewire::test(RunnerGroupManagement::class)
            ->call('newGroup')
            ->set('name', 'us-east')
            ->set('description', 'East coast datacenter')
            ->set('selectedRunnerIds', [$runnerA->id, $runnerB->id])
            ->call('save')
            ->assertHasNoErrors();

        $group = RunnerGroup::where('name', 'us-east')->firstOrFail();
        $this->assertSame('East coast datacenter', $group->description);
        $this->assertEqualsCanonicalizing(
            [$runnerA->id, $runnerB->id],
            $group->runners->pluck('id')->all()
        );
    }

    public function test_editing_a_group_syncs_membership(): void
    {
        $this->actingAs($this->makeAdmin());

        $runnerA = $this->makeRunner('runner-a');
        $runnerB = $this->makeRunner('runner-b');

        $group = RunnerGroup::create(['name' => 'pool']);
        $group->runners()->attach([$runnerA->id, $runnerB->id]);

        Livewire::test(RunnerGroupManagement::class)
            ->call('editGroup', $group->id)
            ->assertSet('selectedRunnerIds', [$runnerA->id, $runnerB->id])
            ->set('selectedRunnerIds', [$runnerB->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing([$runnerB->id], $group->fresh('runners')->runners->pluck('id')->all());
    }

    public function test_deleting_a_group_cascades_pivot_rows_without_deleting_runners(): void
    {
        $this->actingAs($this->makeAdmin());

        $runner = $this->makeRunner('runner-a');
        $group = RunnerGroup::create(['name' => 'pool']);
        $group->runners()->attach($runner->id);

        Livewire::test(RunnerGroupManagement::class)
            ->call('confirmDelete', $group->id)
            ->call('delete');

        $this->assertDatabaseMissing('runner_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('runner_group_runner', ['runner_group_id' => $group->id]);
        $this->assertDatabaseHas('runners', ['id' => $runner->id]);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin());

        RunnerGroup::create(['name' => 'us-east']);

        Livewire::test(RunnerGroupManagement::class)
            ->call('newGroup')
            ->set('name', 'us-east')
            ->call('save')
            ->assertHasErrors('name');
    }

    public function test_non_admin_cannot_save(): void
    {
        $this->actingAs($this->makeUser());

        Livewire::test(RunnerGroupManagement::class)
            ->set('name', 'forbidden')
            ->call('save')
            ->assertForbidden();
    }
}
