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

    public int $tries = 3;
    public int $timeout = 900;

    public function __construct(public readonly string $artifactId) {}

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
            $sourceUrl = Storage::disk($artifact->storage_disk)->temporaryUrl($artifact->storage_key, now()->addMinutes(30));

            $response = Http::acceptJson()
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

            if (!$response->successful()) {
                throw new RuntimeException('AI worker failed: '.$response->status().' '.$response->body());
            }

            $payload = $response->json();
            if (!is_array($payload['segments'] ?? null)) {
                throw new RuntimeException('AI worker returned no transcript segments.');
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
}
