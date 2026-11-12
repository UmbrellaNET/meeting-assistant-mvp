<?php

namespace App\Jobs;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\MeetingSummary;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GenerateMeetingSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // These class values override the --tries/--backoff flags on `queue:work`.
    public int $tries = 6;
    public int $timeout = 300;

    public function __construct(public readonly string $meetingId) {}

    /** Seconds to wait between attempts (the AI worker or model may be busy or waking up). */
    public function backoff(): array
    {
        return [30, 60, 120, 240, 300];
    }

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

        // Everyone in the meeting: participants with accounts, plus every speaker heard in the transcript.
        $attendees = $meeting->participants
            ->map(fn ($participant) => $participant->user?->name ?? $participant->display_name ?? null)
            ->merge($transcript->segments->map(fn ($segment) => $segment->speaker?->display_name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower(trim((string) $name)))
            ->values()
            ->all();

        try {
            $response = Http::acceptJson()
                ->withUserAgent('MeetingAssistant-API/1.0')
                ->withHeader('X-Internal-Secret', (string) config('services.ai_worker.secret'))
                ->timeout(280)
                ->post(rtrim((string) config('services.ai_worker.url'), '/').'/v1/summarize', [
                    'meeting_id' => $meeting->id,
                    'title' => $meeting->title,
                    'date' => optional($meeting->scheduled_start_at)->toDateString(),
                    'attendees' => $attendees,
                    'transcript_text' => $transcriptText,
                ]);

            // Temporary problems (rate limit, worker waking up, quota, gateway errors): wait and retry
            // instead of marking the summary as failed.
            $retryable = in_array($response->status(), [429, 502, 503, 504], true);
            if ($retryable && $this->attempts() < $this->tries) {
                $delay = (int) ($response->header('Retry-After') ?: 0);
                if ($delay < 1) {
                    $delay = [30, 60, 120, 240, 300][min($this->attempts() - 1, 4)];
                }
                $this->release($delay);

                return;
            }

            if (!$response->successful()) {
                throw new RuntimeException('Summary worker failed: '.$response->status().' '.mb_substr($response->body(), 0, 500));
            }

            $data = $response->json();

            $summary = MeetingSummary::updateOrCreate(
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

            // Action items are tracked in their own table. A problem here must never fail the summary.
            try {
                $this->syncActionItems($meeting, is_array($data['action_items'] ?? null) ? $data['action_items'] : []);
            } catch (\Throwable $syncError) {
                report($syncError);
            }
        } catch (\Throwable $e) {
            MeetingSummary::updateOrCreate(
                ['meeting_id' => $meeting->id],
                ['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 4000)]
            );
            throw $e;
        }
    }

    /**
     * Turn the summary's action items into tracked rows. Re-running a summary never duplicates items
     * and never overwrites an item a person has already edited.
     */
    private function syncActionItems(Meeting $meeting, array $items): void
    {
        $keep = [];

        foreach ($items as $item) {
            $action = trim((string) ($item['action'] ?? ''));
            if ($action === '') {
                continue;
            }

            $ownerName = trim((string) ($item['owner'] ?? ''));
            if (in_array(mb_strtolower($ownerName), ['unassigned', 'unknown', 'n/a', 'none', 'tbd', 'null'], true)) {
                $ownerName = '';
            }
            $fingerprint = sha1(mb_strtolower($ownerName.'|'.$action));

            $existing = ActionItem::where('meeting_id', $meeting->id)->where('fingerprint', $fingerprint)->first();
            if ($existing) {
                $keep[] = $existing->id;
                continue;
            }

            $when = trim((string) ($item['when'] ?? ''));
            $priority = strtolower(trim((string) ($item['priority'] ?? 'medium')));
            if (!in_array($priority, ActionItem::PRIORITIES, true)) {
                $priority = 'medium';
            }

            $created = ActionItem::create([
                'tenant_id' => $meeting->tenant_id,
                'meeting_id' => $meeting->id,
                'owner_user_id' => $this->matchOwner($ownerName, $meeting->participants),
                'owner_name' => $ownerName !== '' ? $ownerName : null,
                'title' => $action,
                'context' => trim((string) ($item['context'] ?? '')) ?: null,
                'due_date' => $this->extractDueDate((string) ($item['due_date'] ?? '')) ?? $this->extractDueDate($when),
                'due_text' => $this->cleanDueText($when) ?: null,
                'priority' => $priority,
                'status' => 'open',
                'source' => 'ai',
                'fingerprint' => $fingerprint,
            ]);
            $keep[] = $created->id;
        }

        // A regenerated summary dropped an item nobody has touched: remove it. Edited items always stay.
        ActionItem::where('meeting_id', $meeting->id)
            ->where('source', 'ai')
            ->whereNull('edited_at')
            ->where('status', 'open')
            ->whereNotIn('id', $keep)
            ->delete();
    }

    /** Match a spoken owner name ("Tom") to exactly one participant; anything ambiguous stays unassigned. */
    private function matchOwner(string $ownerName, Collection $participants): ?string
    {
        $needle = mb_strtolower(trim($ownerName));
        if ($needle === '') {
            return null;
        }

        $matches = $participants->filter(function ($participant) use ($needle) {
            $name = mb_strtolower((string) ($participant->user?->name ?? $participant->display_name ?? ''));
            if ($name === '' || empty($participant->user_id)) {
                return false;
            }

            return $name === $needle || in_array($needle, preg_split('/\s+/', $name) ?: [], true);
        });

        return $matches->count() === 1 ? $matches->first()->user_id : null;
    }

    /** The worker returns due_date as YYYY-MM-DD (older replies put it in brackets inside "when"). */
    private function extractDueDate(string $when): ?string
    {
        if (!preg_match('/\[(\d{4}-\d{2}-\d{2})\]/', $when, $m) && !preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $when, $m)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $m[1]);
        } catch (\Throwable) {
            return null;
        }

        // Reject impossible dates such as 2026-02-31, which would otherwise roll over silently.
        return $date && $date->format('Y-m-d') === $m[1] ? $m[1] : null;
    }

    private function cleanDueText(string $when): string
    {
        return trim((string) preg_replace('/\s*\[\d{4}-\d{2}-\d{2}\]\s*/', ' ', $when));
    }
}
