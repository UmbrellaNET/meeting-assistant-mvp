<?php
namespace App\Jobs;

use App\Models\MeetingArtifact;
use App\Models\MeetingSpeaker;
use App\Models\ProcessingJob;
use App\Models\TranscriptSegment;
use App\Models\TranscriptVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessMeetingArtifact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // These class values override the --tries/--backoff flags on `queue:work`.
    public int $tries = 6;
    public int $timeout = 900;

    public function __construct(public readonly string $artifactId) {}

    /** Seconds to wait between attempts when the job throws (gives a sleeping worker time to wake). */
    public function backoff(): array
    {
        return [30, 60, 120, 240, 300];
    }

    public function handle(): void
    {
        $artifact = MeetingArtifact::query()->with('meeting')->findOrFail($this->artifactId);
        $meeting = $artifact->meeting;
        $job = ProcessingJob::create([
            'meeting_id'=>$meeting->id,'stage'=>'transcription','attempt'=>$this->attempts(),'status'=>'running',
            'input_artifact_id'=>$artifact->id,'started_at'=>now(),'metadata'=>['queue_job_id'=>$this->job?->getJobId()],
        ]);

        try {
            $artifact->update(['status'=>'processing']);
            $meeting->update(['status'=>'processing','processing_status'=>'transcribing']);

            if ($this->isVtt($artifact)) {
                // WebVTT transcripts (including Microsoft Teams transcripts) are already text, so they are
                // parsed here. No AI worker call is needed, so nothing can be rate limited on the way.
                $payload = $this->payloadFromVtt($artifact);
            } else {
                $sourceUrl = Storage::disk($artifact->storage_disk)->temporaryUrl($artifact->storage_key, now()->addMinutes(30));

                $response = Http::acceptJson()
                    ->withUserAgent('MeetingAssistant-API/1.0')
                    ->withHeader('X-Internal-Secret', (string) config('services.ai_worker.secret'))
                    ->timeout(850)
                    ->post(rtrim((string) config('services.ai_worker.url'), '/').'/v1/transcribe', [
                        'meeting_id'=>$meeting->id,
                        'artifact_id'=>$artifact->id,
                        'artifact_type'=>$artifact->artifact_type,
                        'source_url'=>$sourceUrl,
                        'filename'=>$artifact->original_filename,
                        'mime_type'=>$artifact->mime_type,
                        'language'=>'en',
                    ]);

                // Temporary problems (rate limit, worker waking up, gateway errors): wait and retry
                // instead of marking the meeting as failed.
                $retryable = in_array($response->status(), [429, 502, 503, 504], true);
                if ($retryable && $this->attempts() < $this->tries) {
                    $delay = (int) ($response->header('Retry-After') ?: 0);
                    if ($delay < 1) {
                        $delay = [30, 60, 120, 240, 300][min($this->attempts() - 1, 4)];
                    }
                    $job->update([
                        'status'=>'failed','completed_at'=>now(),'error_code'=>'WorkerBusy',
                        'error_message'=>'AI worker returned '.$response->status().'; retrying in '.$delay.'s',
                    ]);
                    $artifact->update(['status'=>'stored']);
                    $meeting->update(['status'=>'artifact_received','processing_status'=>'queued']);
                    $this->release($delay);
                    return;
                }

                if (!$response->successful()) {
                    throw new RuntimeException('AI worker failed: '.$response->status().' '.mb_substr($response->body(), 0, 500));
                }

                $payload = $response->json();
            }

            if (!is_array($payload['segments'] ?? null) || $payload['segments'] === []) {
                throw new RuntimeException('No transcript segments were produced.');
            }

            DB::transaction(function () use ($meeting, $artifact, $payload, $job): void {
                TranscriptVersion::where('meeting_id',$meeting->id)->update(['is_current'=>false]);
                $nextVersion = ((int) TranscriptVersion::where('meeting_id',$meeting->id)->max('version_number')) + 1;
                $version = TranscriptVersion::create([
                    'meeting_id'=>$meeting->id,'source_artifact_id'=>$artifact->id,'version_number'=>$nextVersion,
                    'version_type'=>'normalized','language'=>$payload['language'] ?? 'en','transcription_provider'=>$payload['provider'] ?? 'unknown',
                    'model_identifier'=>$payload['model'] ?? null,'status'=>'completed','is_current'=>true,
                    'metadata'=>['duration_ms'=>$payload['duration_ms'] ?? null,'warnings'=>$payload['warnings'] ?? []],
                ]);

                $speakerMap = [];
                foreach ($payload['segments'] as $index => $segment) {
                    $label = (string) ($segment['speaker'] ?? 'Speaker 1');
                    if (!isset($speakerMap[$label])) {
                        $speakerMap[$label] = MeetingSpeaker::firstOrCreate(
                            ['meeting_id'=>$meeting->id,'diarization_label'=>$label],
                            ['display_name'=>$label,'identity_status'=>'unresolved','identity_confidence'=>null]
                        );
                    }
                    TranscriptSegment::create([
                        'transcript_version_id'=>$version->id,'sequence'=>$index + 1,'speaker_id'=>$speakerMap[$label]->id,
                        'start_ms'=>(int)($segment['start_ms'] ?? 0),'end_ms'=>(int)($segment['end_ms'] ?? 0),
                        'text'=>(string)($segment['text'] ?? ''),'confidence'=>$segment['confidence'] ?? null,
                        'source_reference'=>$segment['source_reference'] ?? null,'metadata'=>$segment['metadata'] ?? [],
                    ]);
                }

                $artifact->update(['status'=>'processed']);
                $meeting->update(['status'=>'ready','processing_status'=>'completed']);
                $job->update(['status'=>'completed','completed_at'=>now(),'output_transcript_version_id'=>$version->id]);
                \App\Jobs\GenerateMeetingSummary::dispatch($meeting->id);
            });
        } catch (\Throwable $e) {
            $artifact->update(['status'=>'failed']);
            $meeting->update(['status'=>'processing_failed','processing_status'=>'failed']);
            $job->update(['status'=>'failed','completed_at'=>now(),'error_code'=>class_basename($e),'error_message'=>mb_substr($e->getMessage(),0,4000)]);
            throw $e;
        }
    }

    private function isVtt(MeetingArtifact $artifact): bool
    {
        $extension = strtolower(pathinfo((string) $artifact->original_filename, PATHINFO_EXTENSION));

        return $extension === 'vtt' || $artifact->mime_type === 'text/vtt';
    }

    /** Build the same payload shape the AI worker returns, from a WebVTT file in storage. */
    private function payloadFromVtt(MeetingArtifact $artifact): array
    {
        $disk = Storage::disk($artifact->storage_disk);
        if (!$disk->exists($artifact->storage_key)) {
            throw new RuntimeException('The uploaded file is no longer in storage. Please upload it again.');
        }

        $segments = $this->parseVtt((string) $disk->get($artifact->storage_key));

        return [
            'provider'=>'source-parser',
            'model'=>null,
            'language'=>'en',
            'duration_ms'=>$segments ? max(array_column($segments, 'end_ms')) : 0,
            'segments'=>$segments,
            'warnings'=>[],
        ];
    }

    /**
     * Parse WebVTT into segments. Handles cue identifiers, multi-line cues, <v Name>text</v> voice tags
     * (the format Microsoft Teams uses) and "Name: text" prefixes.
     */
    private function parseVtt(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $segments = [];
        foreach (preg_split('/\n{2,}/', trim($content)) ?: [] as $block) {
            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($line) => $line !== ''));
            if ($lines === []) {
                continue;
            }

            $timeIndex = null;
            foreach ($lines as $i => $line) {
                if (str_contains($line, '-->')) {
                    $timeIndex = $i;
                    break;
                }
            }
            if ($timeIndex === null) {
                continue; // WEBVTT header, NOTE or STYLE block
            }

            [$startRaw, $endRaw] = array_map('trim', explode('-->', $lines[$timeIndex], 2));
            $endRaw = preg_split('/\s+/', $endRaw)[0] ?? $endRaw; // drop cue settings such as "align:start"

            $text = implode(' ', array_slice($lines, $timeIndex + 1));
            if ($text === '') {
                continue;
            }

            $speaker = 'Speaker 1';
            if (preg_match('/^<v(?:\.[^\s>]+)?\s+([^>]+)>(.*)$/s', $text, $m)) {
                $speaker = trim($m[1]);
                $text = $m[2];
            } elseif (preg_match('/^([\p{L}][\p{L}\p{N} .\'\-]{0,40}):\s+(.+)$/su', $text, $m)) {
                $speaker = trim($m[1]);
                $text = $m[2];
            }

            $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                continue;
            }

            $segments[] = [
                'speaker'=>$speaker,
                'start_ms'=>$this->vttTimeToMs($startRaw),
                'end_ms'=>$this->vttTimeToMs($endRaw),
                'text'=>$text,
                'confidence'=>null,
                'source_reference'=>null,
                'metadata'=>[],
            ];
        }

        return $segments;
    }

    /** "HH:MM:SS.mmm" or "MM:SS.mmm" to milliseconds. */
    private function vttTimeToMs(string $time): int
    {
        $parts = explode(':', str_replace(',', '.', trim($time)));
        $seconds = (float) array_pop($parts);
        $minutes = (int) (array_pop($parts) ?? 0);
        $hours = (int) (array_pop($parts) ?? 0);

        return (int) round((($hours * 60 + $minutes) * 60 + $seconds) * 1000);
    }
}
