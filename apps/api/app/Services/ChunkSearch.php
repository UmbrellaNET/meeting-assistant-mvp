<?php

namespace App\Services;

use App\Models\Meeting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Put in: apps/api/app/Services/ChunkSearch.php
class ChunkSearch
{
    /**
     * The passages closest in meaning to a query vector, limited to the given meetings.
     *
     * @param  string[]  $meetingIds
     * @return Collection<int, array<string, mixed>>
     */
    public function nearest(string $vectorLiteral, array $meetingIds, int $limit, ?float $maxDistance = null): Collection
    {
        if ($meetingIds === []) {
            return collect();
        }

        $tMeetings = (new Meeting)->getTable();
        $placeholders = implode(',', array_fill(0, count($meetingIds), '?'));

        $rows = DB::select(
            "SELECT c.id, c.meeting_id, c.start_ms, c.end_ms, c.content, c.segment_ids,
                    m.title AS meeting_title, m.scheduled_start_at, m.created_at AS meeting_created_at,
                    (c.embedding <=> ?::vector) AS distance
             FROM transcript_chunks c
             JOIN {$tMeetings} m ON m.id = c.meeting_id
             WHERE c.meeting_id IN ({$placeholders}) AND c.embedding IS NOT NULL
             ORDER BY c.embedding <=> ?::vector
             LIMIT ?",
            array_merge([$vectorLiteral], $meetingIds, [$vectorLiteral, $limit])
        );

        return collect($rows)
            ->map(function ($row) {
                $segmentIds = json_decode((string) $row->segment_ids, true) ?: [];

                return [
                    'id' => $row->id,
                    'meeting_id' => $row->meeting_id,
                    'meeting_title' => $row->meeting_title,
                    'scheduled_start_at' => $row->scheduled_start_at,
                    'meeting_created_at' => $row->meeting_created_at,
                    'segment_id' => $segmentIds[0] ?? null,
                    'start_ms' => (int) $row->start_ms,
                    'end_ms' => (int) $row->end_ms,
                    'content' => (string) $row->content,
                    'distance' => (float) $row->distance,
                ];
            })
            ->filter(fn (array $row) => $maxDistance === null || $row['distance'] <= $maxDistance)
            ->values();
    }
}
