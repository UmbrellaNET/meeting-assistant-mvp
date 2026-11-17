'use client';

// Put in: apps/web/src/components/AskMeetingsPanel.tsx
import Link from 'next/link';
import { FormEvent, useState } from 'react';
import { api, formatTime } from '@/lib/api';

type Source = {
  n: number;
  meeting_id: string;
  meeting_title: string;
  date?: string | null;
  segment_id?: string | null;
  start_ms: number;
  end_ms: number;
  excerpt: string;
  cited: boolean;
};
type AskResponse = { answer: string; citations: number[]; sources: Source[] };

const examples = ['What did we decide about the launch date?', 'Who is responsible for the security audit?'];

function sourceHref(source: Source): string {
  return source.segment_id
    ? `/meetings/${source.meeting_id}?segment=${source.segment_id}#transcript`
    : `/meetings/${source.meeting_id}`;
}

function formatDate(value?: string | null): string {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString();
}

export function AskMeetingsPanel() {
  const [question, setQuestion] = useState('');
  const [asked, setAsked] = useState('');
  const [result, setResult] = useState<AskResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const ask = async (text: string) => {
    const value = text.trim();
    if (value.length < 3) return;
    setLoading(true);
    setError('');
    setResult(null);
    setAsked(value);
    try {
      setResult(await api<AskResponse>('/ask', { method: 'POST', body: JSON.stringify({ question: value }) }));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'The question could not be answered.');
    } finally {
      setLoading(false);
    }
  };

  const submit = (event: FormEvent) => {
    event.preventDefault();
    void ask(question);
  };

  // Turn "[2]" markers in the answer into links to the transcript line they came from.
  const sourcesByNumber = new Map((result?.sources ?? []).map((source) => [source.n, source]));
  const answerParts = (result?.answer ?? '').split(/(\[\d{1,2}\])/g);
  const citedSources = (result?.sources ?? []).filter((source) => source.cited);
  const shownSources = citedSources.length ? citedSources : (result?.sources ?? []).slice(0, 3);

  return (
    <section className="panel ask-panel">
      <div className="panel-heading">
        <div>
          <h2>Ask your meetings</h2>
          <p>Ask a question and get an answer from your meeting transcripts, with links to where it was said.</p>
        </div>
      </div>

      <form className="ask-form" onSubmit={submit}>
        <input
          value={question}
          onChange={(event) => setQuestion(event.target.value)}
          placeholder="For example: What did we decide about the budget?"
          aria-label="Ask a question about your meetings"
          maxLength={500}
        />
        <button className="primary-button" disabled={loading || question.trim().length < 3}>
          {loading ? 'Thinking...' : 'Ask'}
        </button>
      </form>

      {!result && !loading && !error ? (
        <div className="ask-examples">
          {examples.map((example) => (
            <button
              type="button"
              key={example}
              className="filter-chip"
              onClick={() => {
                setQuestion(example);
                void ask(example);
              }}
            >
              {example}
            </button>
          ))}
        </div>
      ) : null}

      {error ? <div className="error-box">{error}</div> : null}

      {result ? (
        <div className="ask-result">
          <p className="ask-question">{asked}</p>
          <p className="ask-answer">
            {answerParts.map((part, index) => {
              const match = part.match(/^\[(\d{1,2})\]$/);
              const source = match ? sourcesByNumber.get(Number(match[1])) : undefined;
              return source ? (
                <Link
                  key={index}
                  className="cite"
                  href={sourceHref(source)}
                  title={`${source.meeting_title} · ${formatTime(source.start_ms)}`}
                >
                  {source.n}
                </Link>
              ) : (
                <span key={index}>{part}</span>
              );
            })}
          </p>

          {shownSources.length ? (
            <div className="ask-sources">
              <h3>{citedSources.length ? 'Sources' : 'Closest passages (not used in the answer)'}</h3>
              {shownSources.map((source) => (
                <Link className="ask-source" href={sourceHref(source)} key={source.n}>
                  <strong>
                    <span className="cite-number">{source.n}</span>
                    {source.meeting_title}
                    <small>
                      {formatDate(source.date)} · {formatTime(source.start_ms)}
                    </small>
                  </strong>
                  <span>{source.excerpt}</span>
                </Link>
              ))}
            </div>
          ) : null}
          <p className="muted ask-note">Answers come from an AI reading your transcripts. Check the sources before relying on them.</p>
        </div>
      ) : null}
    </section>
  );
}
