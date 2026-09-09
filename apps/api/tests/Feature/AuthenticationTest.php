<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'organisation' => 'Test Org',
            'email' => 'test@example.com',
            'password' => 'very-secure-password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'tenant', 'roles', 'permissions', 'is_super_admin']])
            ->assertJsonPath('user.is_super_admin', true)
            ->assertJsonPath('user.roles.0', 'super-admin')
            ->assertJsonPath('user.first_name', 'Test')
            ->assertJsonPath('user.surname', 'User')
            ->assertJsonPath('user.name', 'Test User')
            ->assertJsonPath('user.has_avatar', false);

        $this->assertContains('users.manage', $response->json('user.permissions'));
        $this->assertContains('branding.manage', $response->json('user.permissions'));
        $this->assertContains('meetings.create', $response->json('user.permissions'));
    }

    public function test_auth_me_returns_roles_permissions_and_is_super_admin(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Org Owner',
            'organisation' => 'Northwind',
            'email' => 'owner@example.com',
            'password' => 'very-secure-password',
        ])->assertCreated()->json('token');

        $me = $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonStructure(['id', 'roles', 'permissions', 'is_super_admin', 'tenant'])
            ->assertJsonPath('is_super_admin', true)
            ->assertJsonPath('roles.0', 'super-admin');

        $this->assertContains('users.manage', $me->json('permissions'));
        $this->assertContains('branding.manage', $me->json('permissions'));
        $this->assertContains('meetings.view-any', $me->json('permissions'));
    }

    public function test_user_can_update_profile_fields(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Org Owner',
            'organisation' => 'Northwind',
            'email' => 'owner@example.com',
            'password' => 'very-secure-password',
        ])->assertCreated()->json('token');

        $this->withToken($token)
            ->patchJson('/api/auth/profile', [
                'first_name' => 'Ada',
                'surname' => 'Lovelace',
                'contact_number' => '+61 400 000 000',
                'job_title' => 'Analyst',
                'department' => 'Research',
                'timezone' => 'Australia/Sydney',
                'bio' => 'Notes specialist',
            ])
            ->assertOk()
            ->assertJsonPath('first_name', 'Ada')
            ->assertJsonPath('surname', 'Lovelace')
            ->assertJsonPath('name', 'Ada Lovelace')
            ->assertJsonPath('contact_number', '+61 400 000 000')
            ->assertJsonPath('job_title', 'Analyst')
            ->assertJsonPath('department', 'Research')
            ->assertJsonPath('timezone', 'Australia/Sydney')
            ->assertJsonPath('bio', 'Notes specialist')
            ->assertJsonPath('has_avatar', false);
    }

    public function test_user_can_upload_and_delete_profile_photo(): void
    {
        Storage::fake(config('filesystems.default'));

        $token = $this->postJson('/api/auth/register', [
            'name' => 'Org Owner',
            'organisation' => 'Northwind',
            'email' => 'owner@example.com',
            'password' => 'very-secure-password',
        ])->assertCreated()->json('token');

        $this->withToken($token)
            ->post('/api/auth/profile/photo', [
                'file' => UploadedFile::fake()->image('avatar.jpg', 80, 80),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('has_avatar', true);

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $this->assertNotNull($user->avatar_storage_key);
        Storage::disk(config('filesystems.default'))->assertExists($user->avatar_storage_key);
        $this->assertStringContainsString("tenants/{$user->tenant_id}/users/{$user->id}/avatar.", $user->avatar_storage_key);

        $this->withToken($token)
            ->deleteJson('/api/auth/profile/photo')
            ->assertOk()
            ->assertJsonPath('has_avatar', false)
            ->assertJsonPath('avatar_url', null);

        Storage::disk(config('filesystems.default'))->assertMissing($user->avatar_storage_key);
    }
}
