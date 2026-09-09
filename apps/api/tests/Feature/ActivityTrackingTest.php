<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivityTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_general_user_cannot_list_activity(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('user@umbrellanet.com');

        $this->withToken($token)
            ->getJson('/api/admin/activity')
            ->assertForbidden();
    }

    public function test_super_admin_can_list_paginated_activity(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('meetings@umbrellanet.com');

        $this->withToken($token)
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'total'])
            ->assertJsonPath('current_page', 1);
    }

    public function test_non_super_admin_with_activity_view_is_tenant_scoped(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('email', 'user@umbrellanet.com')->firstOrFail();
        setPermissionsTeamId($user->tenant_id);
        $user->givePermissionTo('activity.view');

        $otherTenantId = $this->postJson('/api/auth/register', [
            'name' => 'Other Admin',
            'organisation' => 'Other Org',
            'email' => 'other-admin@example.com',
            'password' => 'very-secure-password',
        ])->assertCreated()->json('user.tenant.id');

        $plain = Str::random(64);
        ApiToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plain),
        ]);

        $payload = $this->withToken($plain)
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($payload);
        foreach ($payload as $row) {
            $this->assertSame($user->tenant_id, $row['tenant_id']);
            $this->assertNotSame($otherTenantId, $row['tenant_id']);
        }
    }

    public function test_super_admin_can_see_other_tenant_activity(): void
    {
        $this->seed(DatabaseSeeder::class);
        $adminToken = $this->loginToken('meetings@umbrellanet.com');

        $otherTenantId = $this->postJson('/api/auth/register', [
            'name' => 'Other Admin',
            'organisation' => 'Other Org',
            'email' => 'other-admin@example.com',
            'password' => 'very-secure-password',
        ])->assertCreated()->json('user.tenant.id');

        $payload = $this->withToken($adminToken)
            ->getJson('/api/admin/activity')
            ->assertOk()
            ->json('data');

        $this->assertTrue(collect($payload)->contains(fn (array $row) => $row['tenant_id'] === $otherTenantId));
        $this->assertTrue(collect($payload)->pluck('event')->contains('auth.registered'));
    }

    public function test_login_records_ip_user_agent_and_browser(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('email', 'meetings@umbrellanet.com')->firstOrFail();

        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ])->postJson('/api/auth/login', [
            'email' => 'meetings@umbrellanet.com',
            'password' => 'password',
        ])->assertOk();

        $activity = Activity::query()->where('event', 'auth.login')->latest()->first();
        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertNotEmpty($activity->ip_address);
        $this->assertStringContainsString('Chrome', (string) $activity->user_agent);
        $this->assertSame('Chrome', $activity->browser);
        $this->assertSame('Windows', $activity->os);
        $this->assertSame('desktop', $activity->device_type);
        $this->assertSame('api', $activity->source);
    }

    public function test_failed_login_is_recorded_without_password(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/auth/login', [
            'email' => 'meetings@umbrellanet.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);

        $activity = Activity::query()->where('event', 'auth.login_failed')->latest()->first();
        $this->assertNotNull($activity);
        $this->assertSame('meetings@umbrellanet.com', $activity->properties['email'] ?? null);
        $encoded = json_encode($activity->properties);
        $this->assertStringNotContainsString('wrong-password', (string) $encoded);
        $this->assertStringNotContainsString('"password"', (string) $encoded);
    }

    public function test_meeting_create_is_logged(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('meetings@umbrellanet.com');

        $this->withToken($token)
            ->postJson('/api/meetings', ['title' => 'Architecture Review', 'provider' => 'manual'])
            ->assertCreated();

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'meeting.created',
            'subject_label' => 'Architecture Review',
            'log_name' => 'meetings',
        ]);
    }

    public function test_password_is_redacted_from_logged_properties(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('meetings@umbrellanet.com');

        $this->withToken($token)
            ->postJson('/api/admin/users', [
                'name' => 'Pat Member',
                'email' => 'pat@example.com',
                'password' => 'super-secret-password',
                'role' => 'user',
            ])
            ->assertCreated();

        $created = Activity::query()->where('event', 'user.created')->latest()->first();
        $this->assertNotNull($created);
        $encoded = strtolower((string) json_encode($created->properties));
        $this->assertStringNotContainsString('super-secret-password', $encoded);
        $this->assertStringNotContainsString('"password":"super-secret-password"', $encoded);
    }

    public function test_impersonated_actions_store_impersonator_id(): void
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
            ->patchJson('/api/auth/profile', [
                'job_title' => 'Impersonated title',
            ])
            ->assertOk();

        $activity = Activity::query()->where('event', 'user.updated')->latest()->first();
        $this->assertNotNull($activity);
        $this->assertSame($target->id, $activity->causer_id);
        $this->assertSame($admin->id, $activity->impersonator_id);
    }

    public function test_activity_detail_endpoint_returns_forensic_fields(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('meetings@umbrellanet.com');
        $activity = Activity::query()->latest()->firstOrFail();

        $this->withToken($token)
            ->getJson('/api/admin/activity/'.$activity->id)
            ->assertOk()
            ->assertJsonPath('id', $activity->id)
            ->assertJsonStructure([
                'id',
                'event',
                'actor',
                'ip_address',
                'user_agent',
                'browser',
                'properties',
            ]);
    }

    public function test_location_job_updates_city_and_country(): void
    {
        config(['activity.location_enabled' => true]);
        Http::fake([
            'http://ip-api.com/*' => Http::response([
                'status' => 'success',
                'country' => 'Australia',
                'countryCode' => 'AU',
                'region' => 'VIC',
                'regionName' => 'Victoria',
                'city' => 'Melbourne',
                'query' => '1.1.1.1',
            ]),
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])
            ->postJson('/api/auth/login', [
                'email' => 'meetings@umbrellanet.com',
                'password' => 'password',
            ])
            ->assertOk();

        $activity = Activity::query()->where('event', 'auth.login')->latest()->first();
        $this->assertNotNull($activity);
        $this->assertSame('Melbourne', $activity->fresh()->city);
        $this->assertSame('Australia', $activity->fresh()->country);
        $this->assertSame('Victoria', $activity->fresh()->region);
    }

    public function test_provider_webhook_is_logged_as_integration(): void
    {
        Queue::fake();
        config(['services.provider_webhooks.secret' => 'webhook-secret']);
        $payload = ['event_id' => 'evt-1', 'event_type' => 'meeting.started'];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $raw, 'webhook-secret');

        $this->call('POST', '/api/webhooks/zoom', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
        ], $raw)->assertStatus(202);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'webhook.received',
            'source' => 'webhook',
            'integration' => 'zoom',
            'log_name' => 'integrations',
        ]);
    }

    private function loginToken(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }
}
