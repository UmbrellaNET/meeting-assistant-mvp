<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_impersonate_same_tenant_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $target = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();
        $adminToken = $this->loginToken('meetings@umbrellanet.com');

        $response = $this->withToken($adminToken)
            ->postJson('/api/admin/users/'.$target->id.'/impersonate')
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('user.email', 'user@umbrellanet.com')
            ->assertJsonPath('user.impersonation.active', true)
            ->assertJsonPath('user.impersonation.impersonator.email', 'meetings@umbrellanet.com')
            ->assertJsonPath('impersonator.id', $admin->id)
            ->assertJsonPath('impersonator.name', $admin->name)
            ->assertJsonPath('impersonator.email', 'meetings@umbrellanet.com');

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull($response->json('user.impersonation.expires_at'));

        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $target->id)
            ->assertJsonPath('email', 'user@umbrellanet.com')
            ->assertJsonPath('impersonation.active', true)
            ->assertJsonPath('impersonation.impersonator.id', $admin->id)
            ->assertJsonPath('impersonation.impersonator.name', $admin->name)
            ->assertJsonPath('impersonation.impersonator.email', 'meetings@umbrellanet.com');
    }

    public function test_impersonation_token_cannot_list_admin_users(): void
    {
        $impersonationToken = $this->impersonateSeededUser();

        $this->withToken($impersonationToken)
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_regular_user_cannot_impersonate(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $userToken = $this->loginToken('user@umbrellanet.com');

        $this->withToken($userToken)
            ->postJson('/api/admin/users/'.$admin->id.'/impersonate')
            ->assertForbidden();
    }

    public function test_cannot_impersonate_self(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $adminToken = $this->loginToken('meetings@umbrellanet.com');

        $this->withToken($adminToken)
            ->postJson('/api/admin/users/'.$admin->id.'/impersonate')
            ->assertStatus(422);
    }

    public function test_cannot_impersonate_user_in_another_tenant(): void
    {
        $this->seed(DatabaseSeeder::class);
        $adminToken = $this->loginToken('meetings@umbrellanet.com');

        $otherUserId = $this->postJson('/api/auth/register', [
            'name' => 'Other Admin',
            'organisation' => 'Other Org',
            'email' => 'other@example.com',
            'password' => 'very-secure-password',
        ])->assertCreated()->json('user.id');

        $this->withToken($adminToken)
            ->postJson('/api/admin/users/'.$otherUserId.'/impersonate')
            ->assertNotFound();
    }

    public function test_nested_impersonation_is_rejected(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $impersonationToken = $this->impersonateSeededUser();

        $this->withToken($impersonationToken)
            ->postJson('/api/admin/users/'.$admin->id.'/impersonate')
            ->assertStatus(422);
    }

    public function test_expired_impersonation_token_returns_401(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $target = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();

        $plain = Str::random(64);
        ApiToken::create([
            'user_id' => $target->id,
            'name' => 'impersonation',
            'token_hash' => hash('sha256', $plain),
            'impersonator_id' => $admin->id,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withToken($plain)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_stop_impersonation_revokes_the_token(): void
    {
        $impersonationToken = $this->impersonateSeededUser();

        $this->withToken($impersonationToken)
            ->postJson('/api/auth/stop-impersonation')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->withToken($impersonationToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_password_change_is_rejected_while_impersonating(): void
    {
        $impersonationToken = $this->impersonateSeededUser();

        $this->withToken($impersonationToken)
            ->patchJson('/api/auth/profile', [
                'password' => 'new-password-123',
                'current_password' => 'password',
            ])
            ->assertStatus(422);

        $target = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();
        $this->assertTrue(Hash::check('password', $target->password));
    }

    public function test_activity_log_rows_exist_for_start_and_stop(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();
        $target = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();
        $adminToken = $this->loginToken('meetings@umbrellanet.com');

        $impersonationToken = $this->withToken($adminToken)
            ->postJson('/api/admin/users/'.$target->id.'/impersonate')
            ->assertOk()
            ->json('token');

        $this->withToken($impersonationToken)
            ->postJson('/api/auth/stop-impersonation')
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'impersonation.started',
            'causer_id' => $target->id,
            'impersonator_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'impersonation.stopped',
            'causer_id' => $target->id,
            'impersonator_id' => $admin->id,
        ]);

        $activity = $this->withToken($adminToken)
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->json('data');

        $events = collect($activity)->pluck('event')->all();
        $this->assertContains('impersonation.started', $events);
        $this->assertContains('impersonation.stopped', $events);
        $this->assertGreaterThanOrEqual(2, \App\Models\Activity::query()->count());
    }

    public function test_normal_session_me_has_null_impersonation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('meetings@umbrellanet.com');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('impersonation', null);
    }

    private function impersonateSeededUser(): string
    {
        $this->seed(DatabaseSeeder::class);
        $target = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();
        $adminToken = $this->loginToken('meetings@umbrellanet.com');

        return $this->withToken($adminToken)
            ->postJson('/api/admin/users/'.$target->id.'/impersonate')
            ->assertOk()
            ->json('token');
    }

    private function loginToken(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }
}
