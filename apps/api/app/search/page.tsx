'use client';

// Save as: app/search/page.tsx
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { FormEvent, Suspense, useEffect, useMemo, useState } from 'react';
import { api } from '@/lib/api';

type SegmentHit = {
  segment_id: string;
  meeting_id: string;
  meeting_title: string;
  scheduled_start_at?: string | null;
  created_at: string;
  speaker?: string | null;
  start_ms: number;
  end_ms: number;
  snippet: string;
  rank: number;
};
type NoteHit = { meeting_id: string; meeting_title: string; scheduled_start_at?: string | null; snippet: string };
type MeetingHit = { id: string; title: string; scheduled_start_at?: string | null; created_at: string };
type SearchResponse = { query: string; notes: NoteHit[]; segments: SegmentHit[]; meetings: MeetingHit[] };

/** Shows a snippet with the matched words highlighted. The server marks matches with ⟦ ⟧, so no HTML is ever injected. */
function Snippet({ text }: { text: string }) {
  const parts = text.split(/(⟦[^⟧]*⟧)/g);
  return (
    <>
      {parts.map((part, index) =>
        part.startsWith('⟦') ? <mark key={index}>{part.slice(1, -1)}</mark> : <span key={index}>{part}</span>,
      )}
    </>
  );
}

function formatTime(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000));
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = String(total % 60).padStart(2, '0');
  return hours > 0 ? `${hours}:${String(minutes).padStart(2, '0')}:${seconds}` : `${minutes}:${seconds}`;
}

function formatDate(value?: string | null): string {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString();
}

function SearchContent() {
  const router = useRouter();
  const params = useSearchParams();
  const query = params.get('q') ?? '';
  const [input, setInput] = useState(query);
  const [result, setResult] = useState<SearchResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    setInput(query);
  }, [query]);

  useEffect(() => {
    if (query.trim().length < 2) {
      setResult(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError('');
    api<SearchResponse>(`/search?q=${encodeURIComponent(query)}`)
      .then((data) => {
        if (!cancelled) setResult(data);
      })
      .catch((err) => {
        if (!cancelled) setError(err instanceof Error ? err.message : 'Search failed');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [query]);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    const next = input.trim();
    router.push(next ? `/search?q=${encodeURIComponent(next)}` : '/search');
  };

  // Transcript hits grouped by meeting, best match first.
  const groups = useMemo(() => {
    const map = new Map<string, { title: string; date?: string | null; hits: SegmentHit[] }>();
    for (const hit of result?.segments ?? []) {
      const group = map.get(hit.meeting_id) ?? { title: hit.meeting_title, date: hit.scheduled_start_at ?? hit.created_at, hits: [] };
      group.hits.push(hit);
      map.set(hit.meeting_id, group);
    }
    return Array.from(map.entries());
  }, [result]);

  const total = (result?.segments.length ?? 0) + (result?.notes.length ?? 0) + (result?.meetings.length ?? 0);

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>Search</h1>
          <p>Find what was said across your meetings. Use quotes for an exact phrase, OR for either word, and a minus sign to exclude a word.</p>
        </div>
      </header>

      <form className="search-form" onSubmit={submit}>
        <input
          type="search"
          value={input}
          onChange={(event) => setInput(event.target.value)}
          placeholder="Search transcripts, notes and meeting titles"
          aria-label="Search"
          autoFocus
        />
        <button className="primary-button" disabled={input.trim().length < 2}>
          Search
        </button>
      </form>

      {error ? <div className="error-box">{error}</div> : null}
      {loading ? <p className="muted">Searching...</p> : null}

      {!loading && result && total === 0 ? (
        <div className="panel empty-state">
          <h3>No results for “{result.query}”</h3>
          <p>Try fewer or different words. Search covers meetings you attended and have accepted.</p>
        </div>
      ) : null}

      {result?.meetings.length ? (
        <section className="panel search-section">
          <h2>Meetings</h2>
          {result.meetings.map((meeting) => (
            <Link className="search-result" href={`/meetings/${meeting.id}`} key={meeting.id}>
              <strong>{meeting.title}</strong>
              <small>{formatDate(meeting.scheduled_start_at ?? meeting.created_at)}</small>
            </Link>
          ))}
        </section>
      ) : null}

      {result?.notes.length ? (
        <section className="panel search-section">
          <h2>Meeting notes</h2>
          {result.notes.map((note) => (
            <Link className="search-result" href={`/meetings/${note.meeting_id}`} key={note.meeting_id}>
              <strong>{note.meeting_title}</strong>
              <span>
                <Snippet text={note.snippet} />
              </span>
            </Link>
          ))}
        </section>
      ) : null}

      {groups.length ? (
        <section className="panel search-section">
          <h2>Transcript</h2>
          {groups.map(([meetingId, group]) => (
            <div className="search-group" key={meetingId}>
              <h3>
                {group.title}
                <small>{formatDate(group.date)}</small>
              </h3>
              {group.hits.map((hit) => (
                <Link
                  className="search-result"
                  href={`/meetings/${meetingId}?segment=${hit.segment_id}#transcript`}
                  key={hit.segment_id}
                >
                  <small>
                    {formatTime(hit.start_ms)}
                    {hit.speaker ? ` · ${hit.speaker}` : ''}
                  </small>
                  <span>
                    <Snippet text={hit.snippet} />
                  </span>
                </Link>
              ))}
            </div>
          ))}
        </section>
      ) : null}
    </div>
  );
}

export default function SearchPage() {
  // useSearchParams needs a Suspense boundary in the Next.js app router.
  return (
    <Suspense fallback={<p className="muted">Loading...</p>}>
      <SearchContent />
    </Suspense>
  );
}
