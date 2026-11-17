<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Models\TranscriptChunk;
use App\Services\GeminiClient;
use App\Services\TranscriptChunker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Put in: apps/api/app/Jobs/EmbedMeetingTranscript.php
// Splits a meeting's current transcript into passages, embeds them with Gemini and stores them for search.
class EmbedMeetingTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 300;

    public function __construct(public readonly string $meetingId) {}

    public function backoff(): array
    {
        return [30, 60, 120, 240, 300];
    }

    public function handle(GeminiClient $gemini, TranscriptChunker $chunker): void
    {
        if (!$gemini->configured()) {
            return; // GEMINI_API_KEY is not set on the API yet: nothing to index.
        }

        $meeting = Meeting::with('currentTranscript.segments.speaker')->find($this->meetingId);
        $transcript = $meeting?->currentTranscript;
        if (!$meeting || !$transcript || $transcript->segments->isEmpty()) {
            return;
        }

        $chunks = $chunker->chunk($transcript->segments->sortBy('sequence')->values());
        if ($chunks === []) {
            return;
        }

        // Each passage is embedded with the meeting title so questions that mention the meeting match better.
        $inputs = array_map(fn (array $chunk) => 'Meeting: '.$meeting->title."\n".$chunk['content'], $chunks);
        $vectors = $gemini->embed($inputs, 'RETRIEVAL_DOCUMENT');

        DB::transaction(function () use ($meeting, $transcript, $chunks, $vectors): void {
            TranscriptChunk::where('meeting_id', $meeting->id)->delete();

            foreach ($chunks as $index => $chunk) {
                DB::insert(
                    'INSERT INTO transcript_chunks
                        (id, tenant_id, meeting_id, transcript_version_id, sequence, start_ms, end_ms, content, segment_ids, embedding, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::vector, now(), now())',
                    [
                        (string) Str::orderedUuid(),
                        $meeting->tenant_id,
                        $meeting->id,
                        $transcript->id,
                        $index + 1,
                        $chunk['start_ms'],
                        $chunk['end_ms'],
                        $chunk['content'],
                        json_encode($chunk['segment_ids']),
                        GeminiClient::vectorLiteral($vectors[$index]),
                    ]
                );
            }
        });
    }
}
