<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\SsoAccountResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SsoAccountResolverTest extends TestCase
{
    use RefreshDatabase;

    private SsoAccountResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->resolver = app(SsoAccountResolver::class);
    }

    public function test_existing_link_by_provider_id_is_returned(): void
    {
        $user = User::factory()->create([
            'username' => 'alice',
            'email' => 'alice@example.com',
            'entra_object_id' => 'entra-123',
        ]);

        $resolved = $this->resolver->resolve('entra', 'entra-123', 'alice@example.com', 'Alice');

        $this->assertNotNull($resolved);
        $this->assertSame($user->id, $resolved->id);
    }

    public function test_existing_user_is_linked_by_email_on_first_sso_login(): void
    {
        $user = User::factory()->create([
            'username' => 'bob',
            'email' => 'bob@example.com',
        ]);

        $this->assertNull($user->google_id);

        $resolved = $this->resolver->resolve('google', 'google-456', 'bob@example.com', 'Bob');

        $this->assertNotNull($resolved);
        $this->assertSame($user->id, $resolved->id);
        $this->assertSame('google-456', $resolved->fresh()->google_id);
    }

    public function test_auto_provisioning_creates_a_new_user_with_the_default_role(): void
    {
        AppSetting::current()->update([
            'sso_auto_provision_enabled' => true,
            'sso_default_role' => 'Operator',
        ]);

        $resolved = $this->resolver->resolve('google', 'google-789', 'newperson@example.com', 'New Person');

        $this->assertNotNull($resolved);
        $this->assertSame('newperson@example.com', $resolved->email);
        $this->assertSame('google-789', $resolved->google_id);
        $this->assertNull($resolved->password);
        $this->assertNotNull($resolved->email_verified_at);
        $this->assertTrue($resolved->hasRole('Operator'));
    }

    public function test_auto_provisioning_is_rejected_when_disabled(): void
    {
        AppSetting::current()->update(['sso_auto_provision_enabled' => false]);

        $resolved = $this->resolver->resolve('google', 'google-999', 'nobody@example.com', 'Nobody');

        $this->assertNull($resolved);
        $this->assertDatabaseMissing('users', ['email' => 'nobody@example.com']);
    }

    public function test_auto_provisioning_is_rejected_outside_allowed_domains(): void
    {
        AppSetting::current()->update([
            'sso_auto_provision_enabled' => true,
            'google_allowed_domains' => 'example.com, example.org',
        ]);

        $resolved = $this->resolver->resolve('google', 'google-111', 'someone@other.com', 'Someone');

        $this->assertNull($resolved);
        $this->assertDatabaseMissing('users', ['email' => 'someone@other.com']);
    }

    public function test_auto_provisioning_succeeds_when_domain_is_allowed(): void
    {
        AppSetting::current()->update([
            'sso_auto_provision_enabled' => true,
            'google_allowed_domains' => 'example.com, example.org',
        ]);

        $resolved = $this->resolver->resolve('google', 'google-222', 'someone@example.org', 'Someone');

        $this->assertNotNull($resolved);
        $this->assertSame('someone@example.org', $resolved->email);
    }

    public function test_duplicate_usernames_get_a_numeric_suffix(): void
    {
        AppSetting::current()->update(['sso_auto_provision_enabled' => true]);

        User::factory()->create(['username' => 'sameone', 'email' => 'first@example.com']);

        $resolved = $this->resolver->resolve('google', 'google-333', 'sameone@example.com', 'Sam Eone');

        $this->assertNotNull($resolved);
        $this->assertNotSame('sameone', $resolved->username);
        $this->assertStringStartsWith('sameone', $resolved->username);
    }
}
