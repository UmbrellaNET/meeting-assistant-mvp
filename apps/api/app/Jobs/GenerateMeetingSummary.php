<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\MeetingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenerateMeetingSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(public readonly string $meetingId) {}

    public function handle(): void
    {
        $meeting = Meeting::with(['currentTranscript.segments.speaker', 'participants.user'])->findOrFail($this->meetingId);
        $transcript = $meeting->currentTranscript;

        if (!$transcript || $transcript->segments->isEmpty()) {
            return;
        }

        $transcriptText = $transcript->segments
            ->map(fn ($segment) => sprintf(
                '[%s] %s: %s',
                gmdate('i:s', intdiv($segment->start_ms, 1000)),
                $segment->speaker?->display_name ?? 'Unknown',
                $segment->text
            ))
            ->implode("\n");

        $attendees = $meeting->participants
            ->map(fn ($participant) => $participant->user?->name ?? $participant->display_name ?? null)
            ->filter()
            ->values()
            ->all();

        try {
            $response = Http::acceptJson()
                ->withHeader('X-Internal-Secret', (string) config('services.ai_worker.secret'))
                ->timeout(280)
                ->post(rtrim((string) config('services.ai_worker.url'), '/').'/v1/summarize', [
                    'meeting_id' => $meeting->id,
                    'title' => $meeting->title,
                    'date' => optional($meeting->scheduled_start_at)->toDateString(),
                    'attendees' => $attendees,
                    'transcript_text' => $transcriptText,
                ]);

            if (!$response->successful()) {
                throw new RuntimeException('Summary worker failed: '.$response->status().' '.$response->body());
            }

            $data = $response->json();

            MeetingSummary::updateOrCreate(
                ['meeting_id' => $meeting->id],
                [
                    'transcript_version_id' => $transcript->id,
                    'executive_summary' => $data['executive_summary'] ?? null,
                    'quick_summary' => $data['quick_summary'] ?? [],
                    'decisions' => $data['decisions'] ?? [],
                    'topics' => $data['topics'] ?? [],
                    'action_items' => $data['action_items'] ?? [],
                    'risks' => $data['risks'] ?? [],
                    'dependencies' => $data['dependencies'] ?? [],
                    'unknowns' => $data['unknowns'] ?? [],
                    'status' => 'completed',
                    'error_message' => null,
                ]
            );
        } catch (\Throwable $e) {
            MeetingSummary::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 4000)]
            );
            throw $e;
        }
    }
}
