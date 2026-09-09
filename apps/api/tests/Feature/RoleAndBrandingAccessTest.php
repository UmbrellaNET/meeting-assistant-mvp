<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndBrandingAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_general_user_cannot_list_admin_users_or_update_branding(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('user@umbrellanet.com');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $adminId = User::query()->where('email', 'meetings@umbrellanet.com')->value('id');

        $this->withToken($token)
            ->patchJson('/api/admin/users/' . $adminId, [
                'name' => 'Pat',
                'email' => 'pat@example.com',
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->putJson('/api/settings/branding', $this->brandingPayload())
            ->assertForbidden();
    }

    public function test_super_admin_can_list_and_create_admin_users_and_update_branding(): void
    {
        $this->seed(DatabaseSeeder::class);
        $token = $this->loginToken('meetings@umbrellanet.com');

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['email' => 'meetings@umbrellanet.com'])
            ->assertJsonFragment(['email' => 'user@umbrellanet.com']);

        $this->withToken($token)
            ->postJson('/api/admin/users', [
                'name' => 'Pat Member',
                'email' => 'pat@example.com',
                'password' => 'password12',
                'role' => 'user',
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'pat@example.com')
            ->assertJsonPath('is_super_admin', false)
            ->assertJsonPath('roles.0', 'user');

        $userId = $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->json();
        $pat = collect($userId)->firstWhere('email', 'pat@example.com');

        $this->withToken($token)
            ->patchJson('/api/admin/users/' . $pat['id'], [
                'name' => 'Pat Updated',
                'email' => 'pat@example.com',
                'role' => 'user',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Pat Updated');

        $this->withToken($token)
            ->deleteJson('/api/admin/users/' . $pat['id'])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonMissing(['email' => 'pat@example.com']);

        $this->withToken($token)
            ->putJson('/api/settings/branding', $this->brandingPayload('#112233'))
            ->assertOk()
            ->assertJsonPath('colors.primary', '#112233');
    }

    private function loginToken(string $email): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk()->json('token');
    }

    /**
     * @return array<string, string|bool>
     */
    private function brandingPayload(string $primary = '#D040C0'): array
    {
        return [
            'primary' => $primary,
            'primaryHover' => '#A03090',
            'primarySoft' => '#F6E8F4',
            'action' => '#D040C0',
            'actionHover' => '#A03090',
            'derivedFromLogo' => false,
        ];
    }
}
