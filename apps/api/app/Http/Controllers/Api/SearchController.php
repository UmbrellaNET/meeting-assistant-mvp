<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingSpeaker;
use App\Models\MeetingSummary;
use App\Models\TranscriptSegment;
use App\Models\TranscriptVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Put in: apps/api/app/Http/Controllers/Api/SearchController.php
class SearchController extends Controller
{
    /**
     * Full-text search across the meetings the signed-in user attended (accepted participants only):
     * transcript lines, meeting notes and meeting titles. Matches are marked with ⟦ ⟧ so the web app can
     * highlight them without ever rendering database text as HTML.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $q = trim((string) $request->query('q', ''));
        $empty = ['query' => $q, 'notes' => [], 'segments' => [], 'meetings' => []];
        if (mb_strlen($q) < 2) {
            return response()->json($empty);
        }
        $q = mb_substr($q, 0, 200);
        $empty['query'] = $q;

        // Permissions first: only meetings this user attended are ever searched.
        $meetingIds = Meeting::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereHas('participants', fn ($participants) => $participants
                ->where('user_id', $user->id)
                ->where('status', 'accepted'))
            ->pluck('id')
            ->all();

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

        return response()->json([
            'query' => $q,
            'notes' => $notes,
            'segments' => $segments,
            'meetings' => $meetings,
        ]);
    }
}
