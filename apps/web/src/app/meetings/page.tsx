'use client';
import Link from 'next/link';
import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import type { Meeting, Paginated } from '@/lib/types';
import { StatusBadge } from '@/components/StatusBadge';

export default function MeetingsIndex() {
  const [data, setData] = useState<Paginated<Meeting> | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api<Paginated<Meeting>>('/meetings')
      .then(setData)
      .catch((e) => setError(e.message));
  }, []);

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>Meetings</h1>
          <p>All meetings in this tenant.</p>
        </div>
        <Link className="primary-button" href="/meetings/new">
          Create meeting
        </Link>
      </header>
      <section className="panel">
        {error && <div className="error-box">{error}</div>}
        <div className="meeting-table">
          <div className="table-row table-head">
            <span>Meeting</span>
            <span>Provider</span>
            <span>Status</span>
            <span>Created</span>
          </div>
          {data?.data.map((meeting) => (
            <Link className="table-row" href={`/meetings/${meeting.id}`} key={meeting.id}>
              <span>
                <strong>{meeting.title}</strong>
                <small>{meeting.processing_status.replaceAll('_', ' ')}</small>
              </span>
              <span className="provider">{meeting.provider}</span>
              <span>
                <StatusBadge value={meeting.status} />
              </span>
              <span>{new Date(meeting.created_at).toLocaleDateString()}</span>
            </Link>
          ))}
          {data && !data.data.length && (
            <div className="empty-state">
              <h3>No meetings yet</h3>
              <p>Create a meeting to register a recording or transcript.</p>
            </div>
          )}
        </div>
      </section>
    </div>
  );
}
