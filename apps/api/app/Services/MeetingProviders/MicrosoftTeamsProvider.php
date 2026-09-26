<?php
namespace App\Services\MeetingProviders;
use RuntimeException;
class MicrosoftTeamsProvider implements MeetingProvider
{
    public function provider(): string { return 'teams'; }
    public function ingestEvent(array $payload): void { throw new RuntimeException('Microsoft Teams OAuth and artifact mapping are not configured. Implement this adapter after the manual-upload vertical slice.'); }
    public function listArtifacts(string $providerMeetingId): array { return []; }
    public function downloadArtifact(string $providerArtifactId): mixed { throw new RuntimeException('Microsoft Teams download is not configured.'); }
    public function listParticipants(string $providerMeetingId): array { return []; }
}
