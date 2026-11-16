'use client';

// Put in: components/FollowUpEmailPanel.tsx
import { useCallback, useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import { participantEmail, participantLabel } from '@/lib/lists';
import type { FollowUpDraft, MeetingParticipant } from '@/lib/types';

type Props = {
  meetingId: string;
  participants: MeetingParticipant[];
  /** The follow-up is built from the meeting notes, so they must be ready. */
  summaryReady: boolean;
};

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function FollowUpEmailPanel({ meetingId, participants, summaryReady }: Props) {
  const toast = useToast();
  const [draft, setDraft] = useState<FollowUpDraft | null>(null);
  const [loaded, setLoaded] = useState(false);
  const [mailEnabled, setMailEnabled] = useState(false);
  const [error, setError] = useState('');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [recipients, setRecipients] = useState<string[]>([]);
  const [extra, setExtra] = useState('');
  const [generating, setGenerating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [sending, setSending] = useState(false);
  const [confirmSend, setConfirmSend] = useState(false);
  const [confirmRegenerate, setConfirmRegenerate] = useState(false);

  const applyDraft = useCallback((next: FollowUpDraft | null) => {
    setDraft(next);
    setSubject(next?.subject ?? '');
    setBody(next?.body ?? '');
    setRecipients(next?.recipients ?? []);
  }, []);

  const load = useCallback(async () => {
    try {
      const result = await api<{ draft: FollowUpDraft | null; mail_enabled?: boolean }>(`/meetings/${meetingId}/follow-up`);
      applyDraft(result?.draft ?? null);
      setMailEnabled(Boolean(result?.mail_enabled));
      setError('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load the follow-up email');
    } finally {
      setLoaded(true);
    }
  }, [meetingId, applyDraft]);

  useEffect(() => {
    void load();
  }, [load]);

  // Attendees who have an email address on file.
  const people = useMemo(
    () =>
      participants
        .map((participant) => ({
          email: (participantEmail(participant) ?? '').toLowerCase(),
          label: participantLabel(participant),
        }))
        .filter((person) => person.email),
    [participants],
  );
  const knownEmails = useMemo(() => new Set(people.map((person) => person.email)), [people]);
  const extraRecipients = recipients.filter((email) => !knownEmails.has(email));

  const toggle = (email: string) =>
    setRecipients((current) => (current.includes(email) ? current.filter((item) => item !== email) : [...current, email]));

  const addExtra = () => {
    const email = extra.trim().toLowerCase();
    if (!emailPattern.test(email)) {
      toast.error('Enter a valid email address.');
      return;
    }
    setRecipients((current) => (current.includes(email) ? current : [...current, email]));
    setExtra('');
  };

  const sameRecipients =
    draft !== null && JSON.stringify([...recipients].sort()) === JSON.stringify([...(draft.recipients ?? [])].sort());
  const dirty = draft !== null && (subject !== draft.subject || body !== draft.body || !sameRecipients);

  // Until sending from the app is set up: copy the draft, or open it ready to send in Outlook on the web.
  const outlookUrl =
    'https://outlook.office.com/mail/deeplink/compose' +
    `?to=${encodeURIComponent(recipients.join(';'))}` +
    `&subject=${encodeURIComponent(subject)}` +
    `&body=${encodeURIComponent(body)}`;
  const outlookUrlTooLong = outlookUrl.length > 1900;

  const copyDraft = async () => {
    try {
      await navigator.clipboard.writeText(`Subject: ${subject}\n\n${body}`);
      toast.success('Email copied to clipboard.');
    } catch {
      toast.error('Unable to copy the email.');
    }
  };

  const generate = async () => {
    setGenerating(true);
    setError('');
    try {
      const result = await api<{ draft: FollowUpDraft }>(`/meetings/${meetingId}/follow-up/generate`, { method: 'POST' });
      applyDraft(result.draft);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to create the draft');
    } finally {
      setGenerating(false);
      setConfirmRegenerate(false);
    }
  };

  const save = async (): Promise<boolean> => {
    if (!draft) return false;
    setSaving(true);
    try {
      const result = await api<{ draft: FollowUpDraft }>(`/follow-up-drafts/${draft.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ subject, body, recipients }),
      });
      applyDraft(result.draft);
      return true;
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to save the draft');
      return false;
    } finally {
      setSaving(false);
    }
  };

  const send = async () => {
    if (!draft) return;
    setSending(true);
    try {
      if (dirty && !(await save())) return;
      const result = await api<{ draft: FollowUpDraft; failed: string[]; mailer: string }>(
        `/follow-up-drafts/${draft.id}/send`,
        { method: 'POST' },
      );
      applyDraft(result.draft);
      if (['log', 'array'].includes(result.mailer)) {
        toast.error('Mail is not configured on the server, so the email was only written to its log.');
      } else if (result.failed?.length) {
        toast.error(`Sent, but ${result.failed.length} address(es) failed: ${result.failed.join(', ')}`);
      } else {
        toast.success('Follow-up email sent.');
      }
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to send the email');
    } finally {
      setSending(false);
      setConfirmSend(false);
    }
  };

  const sent = draft?.status === 'sent';

  return (
    <section className="panel followup-panel">
      <div className="panel-heading">
        <div>
          <h2>Follow-up Email</h2>
          <p>A recap of the notes and action items that you can edit and send to attendees.</p>
        </div>
        {draft && !sent ? (
          <button
            type="button"
            className="secondary-button"
            onClick={() => (dirty ? setConfirmRegenerate(true) : void generate())}
            disabled={generating || !summaryReady}
          >
            {generating ? 'Rebuilding...' : 'Rebuild from notes'}
          </button>
        ) : null}
      </div>

      {error ? <div className="error-box">{error}</div> : null}
      {!loaded ? <p className="muted">Loading...</p> : null}

      {loaded && !draft ? (
        <div className="empty-state">
          <h3>No follow-up email yet</h3>
          <p>
            {summaryReady
              ? 'Create a draft from the meeting notes and action items, then edit it before sending.'
              : 'Available once the meeting notes have been generated.'}
          </p>
          <button
            type="button"
            className="primary-button"
            onClick={() => void generate()}
            disabled={generating || !summaryReady}
          >
            {generating ? 'Creating...' : 'Create draft'}
          </button>
        </div>
      ) : null}

      {draft && sent ? (
        <div className="followup-form">
          <div className="success-box">
            Sent {draft.sent_at ? new Date(draft.sent_at).toLocaleString() : ''} to {(draft.sent_to ?? []).length}{' '}
            {(draft.sent_to ?? []).length === 1 ? 'person' : 'people'}.
          </div>
          <label>
            Subject
            <input value={draft.subject} readOnly />
          </label>
          <label>
            Message
            <textarea value={draft.body} rows={12} readOnly />
          </label>
          <p className="muted followup-sent-to">To: {(draft.sent_to ?? []).join(', ')}</p>
          <div className="form-actions">
            <button type="button" className="secondary-button" onClick={() => void generate()} disabled={generating || !summaryReady}>
              {generating ? 'Creating...' : 'Draft a new recap'}
            </button>
          </div>
        </div>
      ) : null}

      {draft && !sent ? (
        <div className="followup-form">
          <fieldset className="followup-recipients">
            <legend>To ({recipients.length} selected)</legend>
            {people.map((person) => (
              <label className="followup-recipient" key={person.email}>
                <input type="checkbox" checked={recipients.includes(person.email)} onChange={() => toggle(person.email)} />
                <span>
                  <strong>{person.label}</strong>
                  <small>{person.email}</small>
                </span>
              </label>
            ))}
            {extraRecipients.map((email) => (
              <label className="followup-recipient" key={email}>
                <input type="checkbox" checked onChange={() => toggle(email)} />
                <span>
                  <strong>{email}</strong>
                  <small>Added by you</small>
                </span>
              </label>
            ))}
            {!people.length && !extraRecipients.length ? (
              <p className="muted">No attendees have an email address on file. Add one below.</p>
            ) : null}
            <div className="followup-add">
              <input
                type="email"
                value={extra}
                onChange={(event) => setExtra(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault();
                    addExtra();
                  }
                }}
                placeholder="Add another email address"
                aria-label="Add another email address"
              />
              <button type="button" className="secondary-button" onClick={addExtra}>
                Add
              </button>
            </div>
          </fieldset>

          <label>
            Subject
            <input value={subject} onChange={(event) => setSubject(event.target.value)} maxLength={255} />
          </label>
          <label>
            Message
            <textarea value={body} onChange={(event) => setBody(event.target.value)} rows={16} />
          </label>

          <div className="form-actions">
            <button type="button" className="secondary-button" onClick={() => void save()} disabled={!dirty || saving || sending}>
              {saving ? 'Saving...' : dirty ? 'Save draft' : 'Saved'}
            </button>
            {mailEnabled ? (
              <button
                type="button"
                className="primary-button"
                onClick={() => setConfirmSend(true)}
                disabled={sending || saving || !recipients.length || !subject.trim() || !body.trim()}
              >
                {sending ? 'Sending...' : 'Send'}
              </button>
            ) : (
              <>
                <button type="button" className="secondary-button" onClick={() => void copyDraft()}>
                  Copy
                </button>
                <a
                  className="primary-button"
                  href={outlookUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-disabled={!recipients.length}
                >
                  Open in Outlook
                </a>
              </>
            )}
          </div>
          {!mailEnabled ? (
            <p className="muted followup-note">
              Sending from the app is not set up yet. Copy the draft, or open it in Outlook with the recipients filled in.
              {outlookUrlTooLong ? ' This recap is long, so Outlook may cut it off; use Copy instead.' : ''}
            </p>
          ) : null}
        </div>
      ) : null}

      <ConfirmDialog
        open={confirmSend}
        title="Send follow-up email"
        description={`Send this recap to ${recipients.length} ${recipients.length === 1 ? 'person' : 'people'}? Each person gets their own copy, and replies come back to you.`}
        confirmLabel="Send"
        busyLabel="Sending..."
        busy={sending}
        onClose={() => {
          if (!sending) setConfirmSend(false);
        }}
        onConfirm={() => void send()}
      />

      <ConfirmDialog
        open={confirmRegenerate}
        title="Rebuild from notes"
        description="This replaces your edits with a fresh draft built from the current notes and action items."
        confirmLabel="Rebuild"
        busyLabel="Rebuilding..."
        busy={generating}
        onClose={() => {
          if (!generating) setConfirmRegenerate(false);
        }}
        onConfirm={() => void generate()}
      />
    </section>
  );
}
