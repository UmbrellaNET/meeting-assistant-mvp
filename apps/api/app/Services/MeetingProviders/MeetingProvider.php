<?php
namespace App\Services\MeetingProviders;
interface MeetingProvider
{
    public function provider(): string;
    public function ingestEvent(array $payload): void;
    public function listArtifacts(string $providerMeetingId): array;
    public function downloadArtifact(string $providerArtifactId): mixed;
    public function listParticipants(string $providerMeetingId): array;
}
