'use client';

import type { MeetingSummary } from '@/lib/types';

function list<T>(value: T[] | null | undefined): T[] {
  return Array.isArray(value) ? value : [];
}

export function MeetingSummaryView({ summary }: { summary: MeetingSummary }) {
  if (!summary) return null;

  if (summary.status === 'failed') {
    return (
      <div className="empty-state">
        <h3>Summary generation failed</h3>
        <p>{summary.error_message ?? 'An unknown error occurred.'}</p>
      </div>
    );
  }

  // Anything that isn't a completed summary (e.g. a pending marker) renders nothing.
  if (summary.status !== 'completed') return null;

  const quick = list(summary.quick_summary);
  const decisions = list(summary.decisions);
  const topics = list(summary.topics);
  const actions = list(summary.action_items);
  const risks = list(summary.risks);
  const deps = list(summary.dependencies);
  const unknowns = list(summary.unknowns);

  return (
    <div className="meeting-summary">
      {summary.executive_summary ? (
        <>
          <h3>Executive Summary</h3>
          <p>{summary.executive_summary}</p>
        </>
      ) : null}

      {quick.length ? (
        <>
          <h3>Quick Summary</h3>
          <ul>
            {quick.map((item, i) => (
              <li key={i}>{item}</li>
            ))}
          </ul>
        </>
      ) : null}

      {decisions.length ? (
        <>
          <h3>Decisions Made</h3>
          <ul>
            {decisions.map((item, i) => (
              <li key={i}>{item}</li>
            ))}
          </ul>
        </>
      ) : null}

      {topics.length ? (
        <>
          <h3>Topic Breakdown</h3>
          {topics.map((topic, i) => (
            <div className="summary-topic" key={i}>
              <h4>Topic: {topic.topic}</h4>
              {topic.owner ? (
                <p>
                  <strong>Owner:</strong> {topic.owner}
                </p>
              ) : null}
              <p>{topic.summary}</p>
              <p>
                <strong>Outcome:</strong> {topic.outcome}
              </p>
            </div>
          ))}
        </>
      ) : null}

      {actions.length ? (
        <>
          <h3>Action Items</h3>
          <table className="summary-table">
            <thead>
              <tr>
                <th>Owner</th>
                <th>Action</th>
                <th>Context</th>
                <th>When</th>
                <th>Priority</th>
              </tr>
            </thead>
            <tbody>
              {actions.map((item, i) => (
                <tr key={i}>
                  <td>{item.owner}</td>
                  <td>{item.action}</td>
                  <td>{item.context}</td>
                  <td>{item.when}</td>
                  <td>{item.priority}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      ) : null}

      {risks.length || deps.length || unknowns.length ? (
        <>
          <h3>Risks / Open Items</h3>
          {risks.length ? (
            <>
              <h4>Risks</h4>
              <ul>
                {risks.map((r, i) => (
                  <li key={i}>{r}</li>
                ))}
              </ul>
            </>
          ) : null}
          {deps.length ? (
            <>
              <h4>Dependencies</h4>
              <ul>
                {deps.map((d, i) => (
                  <li key={i}>{d}</li>
                ))}
              </ul>
            </>
          ) : null}
          {unknowns.length ? (
            <>
              <h4>Unknowns</h4>
              <ul>
                {unknowns.map((u, i) => (
                  <li key={i}>{u}</li>
                ))}
              </ul>
            </>
          ) : null}
        </>
      ) : null}
    </div>
  );
}