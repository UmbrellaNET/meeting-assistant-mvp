<?php

namespace App\Services;

use App\Models\Meeting;
use Carbon\Carbon;

/**
 * Builds a minimal, standards-compliant iCalendar (.ics) file for a single
 * meeting, so it can be opened by Google Calendar, Outlook, Apple Calendar,
 * or any other client that reads RFC 5545 files.
 *
 * Deliberately timezone-agnostic on output: all times are converted to UTC
 * with a trailing "Z", which every major calendar client interprets
 * correctly regardless of the viewer's own timezone. This sidesteps
 * needing to map this app's stored `timezone` string to a VTIMEZONE block,
 * which is a much bigger spec surface for little practical benefit here.
 */
class IcsGenerator
{
    private const DEFAULT_DURATION_MINUTES = 60;

    public function build(Meeting $meeting): string
    {
        $start = $this->resolveStart($meeting);
        $end = $this->resolveEnd($meeting, $start);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//UmbrellaNET//Meeting Assistant//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $this->uid($meeting),
            'DTSTAMP:' . $this->toUtcIcsDate(now()),
            'DTSTART:' . $this->toUtcIcsDate($start),
            'DTEND:' . $this->toUtcIcsDate($end),
            'SUMMARY:' . $this->escape($meeting->title),
        ];

        if (! empty($meeting->meeting_url)) {
            $lines[] = 'URL:' . $this->escape($meeting->meeting_url);
            $lines[] = 'DESCRIPTION:' . $this->escape("Join: {$meeting->meeting_url}");
        }

        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // RFC 5545 requires CRLF line endings.
        return implode("\r\n", $lines) . "\r\n";
    }

    public function filename(Meeting $meeting): string
    {
        $slug = \Illuminate\Support\Str::slug($meeting->title) ?: 'meeting';

        return "{$slug}.ics";
    }

    private function resolveStart(Meeting $meeting): Carbon
    {
        return $meeting->scheduled_start_at
            ?? $meeting->actual_start_at
            ?? now();
    }

    private function resolveEnd(Meeting $meeting, Carbon $start): Carbon
    {
        if ($meeting->actual_end_at) {
            return $meeting->actual_end_at;
        }

        return $start->copy()->addMinutes(self::DEFAULT_DURATION_MINUTES);
    }

    private function toUtcIcsDate(Carbon $date): string
    {
        return $date->copy()->utc()->format('Ymd\THis\Z');
    }

    private function uid(Meeting $meeting): string
    {
        // Stable per meeting, so re-downloading/re-importing updates the
        // same calendar entry rather than creating a duplicate.
        return "meeting-{$meeting->id}@umbrellanet";
    }

    private function escape(string $value): string
    {
        // Per RFC 5545 §3.3.11: escape backslash, semicolon, comma, and
        // convert newlines to literal \n.
        $value = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $value);

        return str_replace(["\r\n", "\n"], '\\n', $value);
    }
}
