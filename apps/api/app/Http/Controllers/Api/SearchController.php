<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingSpeaker;
use App\Models\MeetingSummary;
use App\Models\TranscriptSegment;
use App\Models\TranscriptVersion;
use App\Services\ChunkSearch;
use App\Services\GeminiClient;
use App\Support\MeetingAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Put in: apps/api/app/Http/Controllers/Api/SearchController.php  (replaces the earlier version)
class SearchController extends Controller
{
    /**
     * Search across the meetings the signed-in user attended (accepted participants only):
     * exact keyword matches in transcripts, notes and titles, plus passages that are close in meaning.
     * Keyword matches are marked with ⟦ ⟧ so the web app can highlight them without rendering database text as HTML.
     */
    public function index(Request $request, GeminiClient $gemini, ChunkSearch $chunkSearch): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $q = trim((string) $request->query('q', ''));
        $empty = ['query' => $q, 'notes' => [], 'segments' => [], 'meetings' => [], 'related' => [], 'semantic' => false];
        if (mb_strlen($q) < 2) {
            return response()->json($empty);
        }
        $q = mb_substr($q, 0, 200);
        $empty['query'] = $q;

        // Permissions first: only meetings this user attended are ever searched.
        $meetingIds = MeetingAccess::idsFor($user);
        if ($meetingIds === []) {
            return response()->json($empty);
        }

        $tSegments = (new TranscriptSegment)->getTable();
        $tVersions = (new TranscriptVersion)->getTable();
        $tMeetings = (new Meeting)->getTable();
        $tSpeakers = (new MeetingSpeaker)->getTable();
        $tSummaries = (new MeetingSummary)->getTable();

        $tsq = "websearch_to_tsquery('english', ?)";
        $headline = 'StartSel=⟦, StopSel=⟧, MaxFragments=1, MaxWords=35, MinWords=12';

        $segments = DB::table("{$tSegments} as s")
            ->join("{$tVersions} as v", 'v.id', '=', 's.transcript_version_id')
            ->join("{$tMeetings} as m", 'm.id', '=', 'v.meeting_id')
            ->leftJoin("{$tSpeakers} as sp", 'sp.id', '=', 's.speaker_id')
            ->where('v.is_current', true)
            ->whereIn('m.id', $meetingIds)
            ->whereRaw("s.search_vector @@ {$tsq}", [$q])
            ->selectRaw(
                "s.id as segment_id, m.id as meeting_id, m.title as meeting_title, m.scheduled_start_at, m.created_at, "
                ."sp.display_name as speaker, s.start_ms, s.end_ms, "
                ."ts_headline('english', s.\"text\", {$tsq}, '{$headline}') as snippet, "
                ."ts_rank_cd(s.search_vector, {$tsq}) as rank",
                [$q, $q]
            )
            ->orderByDesc('rank')
            ->orderByDesc('m.created_at')
            ->limit(40)
            ->get();

        $notes = DB::table("{$tSummaries} as ms")
            ->join("{$tMeetings} as m", 'm.id', '=', 'ms.meeting_id')
            ->whereIn('m.id', $meetingIds)
            ->where('ms.status', 'completed')
            ->whereRaw("to_tsvector('english', coalesce(ms.executive_summary, '')) @@ {$tsq}", [$q])
            ->selectRaw(
                "m.id as meeting_id, m.title as meeting_title, m.scheduled_start_at, "
                ."ts_headline('english', coalesce(ms.executive_summary, ''), {$tsq}, '{$headline}') as snippet",
                [$q]
            )
            ->limit(10)
            ->get();

        $meetings = Meeting::query()
            ->whereIn('id', $meetingIds)
            ->where(function ($where) use ($q): void {
                $where->whereRaw("to_tsvector('english', title) @@ websearch_to_tsquery('english', ?)", [$q])
                    ->orWhere('title', 'ilike', '%'.addcslashes($q, '%_\\').'%');
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'title', 'scheduled_start_at', 'created_at']);

        // Passages that are close in meaning (needs GEMINI_API_KEY and indexed meetings). Failure never breaks keyword search.
        $related = [];
        $semantic = false;
        if ($gemini->configured()) {
            try {
                $vector = GeminiClient::vectorLiteral($gemini->embedOne($q, 'RETRIEVAL_QUERY'));
                $related = $chunkSearch->nearest($vector, $meetingIds, 8, 0.55)
                    ->map(fn (array $row) => [
                        'chunk_id' => $row['id'],
                        'meeting_id' => $row['meeting_id'],
                        'meeting_title' => $row['meeting_title'],
                        'scheduled_start_at' => $row['scheduled_start_at'],
                        'created_at' => $row['meeting_created_at'],
                        'segment_id' => $row['segment_id'],
                        'start_ms' => $row['start_ms'],
                        'end_ms' => $row['end_ms'],
                        'text' => mb_substr($row['content'], 0, 400),
                        'distance' => round($row['distance'], 3),
                    ])
                    ->all();
                $semantic = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'query' => $q,
            'notes' => $notes,
            'segments' => $segments,
            'meetings' => $meetings,
            'related' => $related,
            'semantic' => $semantic,
        ]);
    }
}
