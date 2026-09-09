<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeetingInvitationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_tenant_user_cannot_view_meeting_until_invite_is_accepted(): void
    {
        $admin = $this->registerOrg('Acme', 'admin@acme.test');
        $convenor = $this->createTenantUser($admin['token'], 'Ada Convenor', 'ada@acme.test');
        $attendee = $this->createTenantUser($admin['token'], 'Ben Attendee', 'ben@acme.test');

        $meetingId = $this->withToken($convenor['token'])
            ->postJson('/api/meetings', ['title' => 'Sprint planning', 'provider' => 'manual'])
            ->assertCreated()
            ->json('id');

        $this->withToken($attendee['token'])
            ->getJson('/api/meetings/'.$meetingId)
            ->assertForbidden();

        $this->assertMeetingIds($attendee['token'], []);
        $this->assertMeetingIds($admin['token'], [$meetingId]);

        $this->withToken($admin['token'])
            ->getJson('/api/meetings/'.$meetingId)
            ->assertOk()
            ->assertJsonPath('id', $meetingId);

        $this->withToken($convenor['token'])
            ->postJson('/api/meetings/'.$meetingId.'/invitations', ['user_id' => $attendee['id']])
            ->assertCreated()
            ->assertJsonPath('user_id', $attendee['id'])
            ->assertJsonPath('participation_role', 'attendee')
            ->assertJsonPath('status', 'pending');

        $this->withToken($attendee['token'])
            ->getJson('/api/meetings/'.$meetingId)
            ->assertForbidden();

        $this->assertMeetingIds($attendee['token'], []);
        $this->assertMeetingIds($convenor['token'], [$meetingId]);

        $this->withToken($attendee['token'])
            ->postJson('/api/meetings/'.$meetingId.'/invitations/respond', ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->withToken($attendee['token'])
            ->getJson('/api/meetings/'.$meetingId)
            ->assertOk()
            ->assertJsonPath('id', $meetingId)
            ->assertJsonPath('my_participation.participation_role', 'attendee')
            ->assertJsonPath('my_participation.status', 'accepted');

        $this->assertMeetingIds($attendee['token'], [$meetingId]);
        $this->assertMeetingIds($admin['token'], [$meetingId]);
    }

    public function test_attendee_cannot_upload_artifact_but_convenor_can(): void
    {
        Storage::fake(config('filesystems.default'));
        Queue::fake();

        $admin = $this->registerOrg('Upload Co', 'admin@upload.test');
        $convenor = $this->createTenantUser($admin['token'], 'Cara Convenor', 'cara@upload.test');
        $attendee = $this->createTenantUser($admin['token'], 'Drew Attendee', 'drew@upload.test');

        $meetingId = $this->withToken($convenor['token'])
            ->postJson('/api/meetings', ['title' => 'Retro', 'provider' => 'manual'])
            ->assertCreated()
            ->json('id');

        $this->withToken($convenor['token'])
            ->postJson('/api/meetings/'.$meetingId.'/invitations', ['user_id' => $attendee['id']])
            ->assertCreated();

        $this->withToken($attendee['token'])
            ->postJson('/api/meetings/'.$meetingId.'/invitations/respond', ['status' => 'accepted'])
            ->assertOk();

        $this->withToken($attendee['token'])
            ->post('/api/meetings/'.$meetingId.'/artifacts', [
                'artifact_type' => 'audio',
                'file' => UploadedFile::fake()->create('clip.mp3', 20, 'audio/mpeg'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->withToken($convenor['token'])
            ->post('/api/meetings/'.$meetingId.'/artifacts', [
                'artifact_type' => 'audio',
                'file' => UploadedFile::fake()->create('clip.mp3', 20, 'audio/mpeg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(202)
            ->assertJsonPath('artifact.artifact_type', 'audio');
    }

    public function test_cross_tenant_meeting_is_not_found_even_for_other_super_admin(): void
    {
        $acme = $this->registerOrg('Acme', 'owner@acme.test');
        $globex = $this->registerOrg('Globex', 'owner@globex.test');

        $meetingId = $this->withToken($acme['token'])
            ->postJson('/api/meetings', ['title' => 'Acme only', 'provider' => 'manual'])
            ->assertCreated()
            ->json('id');

        $this->withToken($globex['token'])
            ->getJson('/api/meetings/'.$meetingId)
            ->assertNotFound();

        $this->assertMeetingIds($globex['token'], []);
    }

    /**
     * @return array{id: string, token: string}
     */
    private function registerOrg(string $organisation, string $email): array
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Org Owner',
            'organisation' => $organisation,
            'email' => $email,
            'password' => 'very-secure-password',
        ])->assertCreated();

        return [
            'id' => $response->json('user.id'),
            'token' => $response->json('token'),
        ];
    }

    /**
     * @return array{id: string, token: string}
     */
    private function createTenantUser(string $adminToken, string $name, string $email): array
    {
        $user = $this->withToken($adminToken)
            ->postJson('/api/admin/users', [
                'name' => $name,
                'email' => $email,
                'password' => 'password12',
                'role' => 'user',
            ])
            ->assertCreated();

        $token = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password12',
        ])->assertOk()->json('token');

        return [
            'id' => $user->json('id'),
            'token' => $token,
        ];
    }

    /**
     * @param  list<string>  $expectedIds
     */
    private function assertMeetingIds(string $token, array $expectedIds): void
    {
        $ids = $this->withToken($token)
            ->getJson('/api/meetings')
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing($expectedIds, collect($ids)->pluck('id')->all());
    }
}
