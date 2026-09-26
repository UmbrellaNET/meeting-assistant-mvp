<?php
namespace App\Services\MeetingProviders;
use RuntimeException;
class GoogleMeetProvider implements MeetingProvider
{
    public function provider(): string { return 'google-meet'; }
    public function ingestEvent(array $payload): void { throw new RuntimeException('Google Meet OAuth and artifact mapping are not configured. Implement this adapter after the manual-upload vertical slice.'); }
    public function listArtifacts(string $providerMeetingId): array { return []; }
    public function downloadArtifact(string $providerArtifactId): mixed { throw new RuntimeException('Google Meet download is not configured.'); }
    public function listParticipants(string $providerMeetingId): array { return []; }
}
