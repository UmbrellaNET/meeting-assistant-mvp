'use client';

// Put in: apps/web/src/components/TranscriptViewer.tsx  (replaces the existing file)
import { useEffect, useState } from 'react';
import { api, formatTime } from '@/lib/api';
import type { Segment } from '@/lib/types';

export function TranscriptViewer({ meetingId, initialSegments }: { meetingId: string; initialSegments: Segment[] }) {
  const [segments, setSegments] = useState(initialSegments);
  const [editing, setEditing] = useState<string | null>(null);
  const [name, setName] = useState('');
  const [highlightId, setHighlightId] = useState<string | null>(null);

  // A link from search or an answer (?segment=<id>) highlights that line; the page scrolls it into view.
  useEffect(() => {
    setHighlightId(new URLSearchParams(window.location.search).get('segment'));
  }, []);

  const save = async (segment: Segment) => {
    if (!segment.speaker) return;
    const updated = (await api(`/meetings/${meetingId}/speakers/${segment.speaker.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ display_name: name }),
    })) as { display_name: string };
    setSegments((current) =>
      current.map((item) =>
        item.speaker?.id === segment.speaker?.id
          ? { ...item, speaker: { ...item.speaker!, display_name: updated.display_name, identity_status: 'confirmed' } }
          : item,
      ),
    );
    setEditing(null);
  };

  return (
    <div className="transcript-list">
      {segments.map((segment) => (
        <article
          className={`transcript-segment${highlightId === segment.id ? ' is-highlighted' : ''}`}
          id={`segment-${segment.id}`}
          key={segment.id}
        >
          <button
            className="timestamp"
            title="Playback integration point"
            onClick={() => window.dispatchEvent(new CustomEvent('meeting-seek', { detail: { ms: segment.start_ms } }))}
          >
            {formatTime(segment.start_ms)}
          </button>
          <div className="segment-body">
            <div className="speaker-row">
              {editing === segment.speaker?.id ? (
                <>
                  <input value={name} onChange={(e) => setName(e.target.value)} autoFocus />
                  <button className="small-button" onClick={() => void save(segment)}>
                    Save
                  </button>
                </>
              ) : (
                <button
                  className="speaker-name"
                  onClick={() => {
                    setEditing(segment.speaker?.id ?? null);
                    setName(segment.speaker?.display_name ?? 'Speaker');
                  }}
                >
                  {segment.speaker?.display_name ?? 'Unknown speaker'}
                </button>
              )}
              <span className="identity-state">{segment.speaker?.identity_status}</span>
            </div>
            <p>{segment.text}</p>
          </div>
        </article>
      ))}
    </div>
  );
}
