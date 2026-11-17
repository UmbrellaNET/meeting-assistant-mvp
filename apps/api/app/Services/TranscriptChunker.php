<?php

namespace App\Services;

use Illuminate\Support\Collection;

// Put in: apps/api/app/Services/TranscriptChunker.php
// Groups consecutive transcript lines into short passages (about a minute) that are embedded and retrieved.
class TranscriptChunker
{
    private const MAX_CHARS = 1000;
    private const MAX_MS = 75000;

    /**
     * @param  Collection  $segments  ordered transcript segments, each with its speaker loaded
     * @return array<int, array{start_ms:int,end_ms:int,content:string,segment_ids:array<int,string>}>
     */
    public function chunk(Collection $segments): array
    {
        $chunks = [];
        $current = null;

        foreach ($segments as $segment) {
            $text = trim((string) $segment->text);
            if ($text === '') {
                continue;
            }
            $line = ($segment->speaker?->display_name ?? 'Unknown').': '.$text;

            if ($current !== null
                && ($current['chars'] + mb_strlen($line) > self::MAX_CHARS || (int) $segment->end_ms - $current['start_ms'] > self::MAX_MS)) {
                $chunks[] = $current;
                $current = null;
            }

            $current ??= ['start_ms' => (int) $segment->start_ms, 'end_ms' => (int) $segment->end_ms, 'lines' => [], 'segment_ids' => [], 'chars' => 0];
            $current['lines'][] = $line;
            $current['segment_ids'][] = (string) $segment->id;
            $current['end_ms'] = max($current['end_ms'], (int) $segment->end_ms);
            $current['chars'] += mb_strlen($line) + 1;
        }

        if ($current !== null) {
            $chunks[] = $current;
        }

        return array_map(fn (array $chunk) => [
            'start_ms' => $chunk['start_ms'],
            'end_ms' => $chunk['end_ms'],
            'content' => mb_substr(implode("\n", $chunk['lines']), 0, 6000),
            'segment_ids' => $chunk['segment_ids'],
        ], $chunks);
    }
}
