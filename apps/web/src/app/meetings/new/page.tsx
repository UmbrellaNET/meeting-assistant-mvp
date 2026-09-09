'use client';

import { FormEvent, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import type { Meeting } from '@/lib/types';

export default function NewMeeting() {
  const router = useRouter();
  const toast = useToast();
  const [title, setTitle] = useState('');
  const [provider, setProvider] = useState('manual');
  const [scheduled, setScheduled] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const meeting = await api<Meeting>('/meetings', {
        method: 'POST',
        body: JSON.stringify({
          title,
          provider,
          scheduled_start_at: scheduled || null,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        }),
      });
      toast.success('Meeting created.');
      router.push(`/meetings/${meeting.id}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to create meeting');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="narrow-page">
      <header className="page-header">
        <div>
          <span className="eyebrow">NEW MEETING</span>
          <h1>Register a Meeting</h1>
          <p>Create the meeting record before importing a recording or transcript.</p>
        </div>
      </header>
      <form className="panel form-grid" onSubmit={(event) => void submit(event)}>
        <label className="full">
          Meeting title
          <input
            value={title}
            onChange={(event) => setTitle(event.target.value)}
            placeholder="Executive product review"
            required
          />
        </label>
        <label>
          Source provider
          <select value={provider} onChange={(event) => setProvider(event.target.value)}>
            <option value="manual">Manual upload</option>
            <option value="teams">Microsoft Teams</option>
            <option value="zoom">Zoom</option>
            <option value="google-meet">Google Meet</option>
          </select>
        </label>
        <label>
          Scheduled start
          <input
            type="datetime-local"
            value={scheduled}
            onChange={(event) => setScheduled(event.target.value)}
          />
        </label>
        {error ? <div className="error-box full">{error}</div> : null}
        <div className="form-actions full">
          <button className="primary-button" disabled={busy}>
            {busy ? 'Creating...' : 'Create Meeting'}
          </button>
        </div>
      </form>
    </div>
  );
}
