'use client';

import { FormEvent, use, useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/components/AuthProvider';
import { MediaPlayer } from '@/components/MediaPlayer';
import { MeetingSummaryView } from '@/components/MeetingSummaryView';
import { ParticipationBadge } from '@/components/ParticipationBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { TranscriptViewer } from '@/components/TranscriptViewer';
import { api, getMeetingSummary } from '@/lib/api';
import {
  excludeExistingParticipants,
  participantEmail,
  participantLabel,
  unwrapList,
} from '@/lib/lists';
import { isMeetingConvenor } from '@/lib/rbac';
import type { Meeting, MeetingParticipant, MeetingSummary, TenantUser } from '@/lib/types';

async function fetchTenantUsers(): Promise<TenantUser[]> {
  try {
    return unwrapList<TenantUser>(await api('/admin/users'));
  } catch {
    try {
      return unwrapList<TenantUser>(await api('/users'));
    } catch {
      return [];
    }
  }
}

async function fetchSummary(meetingId: string): Promise<MeetingSummary> {
  try {
    const result = await getMeetingSummary(meetingId);
    const status = (result as { status?: string } | null)?.status;
    // Only accept real summaries; ignore `{}` or `{ status: 'pending' }`.
    return status === 'completed' || status === 'failed' ? result : null;
  } catch {
    return null;
  }
}

export default function MeetingDetail({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const { user } = useAuth();
  const [meeting, setMeeting] = useState<Meeting | null>(null);
  const [participants, setParticipants] = useState<MeetingParticipant[]>([]);
  const [tenantUsers, setTenantUsers] = useState<TenantUser[]>([]);
  const [inviteUserId, setInviteUserId] = useState('');
  const [error, setError] = useState('');
  const [uploading, setUploading] = useState(false);
  const [inviting, setInviting] = useState(false);
  const [artifactType, setArtifactType] = useState('uploaded_transcript');
  const [summary, setSummary] = useState<MeetingSummary>(null);

  const load = useCallback(async () => {
    const next = await api<Meeting>(`/meetings/${id}`);
    setMeeting(next);
    void fetchSummary(id).then(setSummary);
    if (next.participants?.length) {
      setParticipants(next.participants);
      return next;
    }

    try {
      setParticipants(unwrapList<MeetingParticipant>(await api(`/meetings/${id}/participants`)));
    } catch {
      setParticipants([]);
    }
    return next;
  }, [id]);

  useEffect(() => {
    load().catch((err) => setError(err instanceof Error ? err.message : 'Unable to load meeting'));
  }, [load]);

  const canManage = meeting ? isMeetingConvenor(meeting, user) : false;

  useEffect(() => {
    if (!canManage) return;
    fetchTenantUsers().then(setTenantUsers);
  }, [canManage]);

  const upload = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const input = event.currentTarget.elements.namedItem('file') as HTMLInputElement;
    if (!input.files?.[0]) return;
    setUploading(true);
    setError('');
    const body = new FormData();
    body.append('artifact_type', artifactType);
    body.append('file', input.files[0]);
    try {
      await api(`/meetings/${id}/artifacts`, { method: 'POST', body });
      input.value = '';
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Upload failed');
    } finally {
      setUploading(false);
    }
  };

  const invite = async (event: FormEvent) => {
    event.preventDefault();
    if (!inviteUserId) return;
    setInviting(true);
    setError('');
    try {
      await api(`/meetings/${id}/invitations`, {
        method: 'POST',
        body: JSON.stringify({ user_id: inviteUserId }),
      });
      setInviteUserId('');
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to send invitation');
    } finally {
      setInviting(false);
    }
  };

  if (!meeting) {
    return (
      <div className="center-screen">
        <div className="loader" />
        <p>Loading meeting...</p>
      </div>
    );
  }

  const transcript = meeting.current_transcript;
  const recording = meeting.artifacts?.find(
    (artifact) => artifact.artifact_type === 'audio' || artifact.artifact_type === 'video',
  );
  const invitees = excludeExistingParticipants(tenantUsers, participants, user?.id);

  return (
    <div>
      <header className="page-header">
        <div>
          <span className="eyebrow">{meeting.provider.toUpperCase()}</span>
          <div className="title-with-badge">
            <h1>{meeting.title}</h1>
            <ParticipationBadge role={meeting.my_participation?.participation_role} />
          </div>
          <p>Meeting ID {meeting.id}</p>
        </div>
        <StatusBadge value={meeting.status} />
      </header>
      {error ? <div className="error-box">{error}</div> : null}

      <section className="detail-grid">
        <div className="panel">
          <div className="panel-heading">
            <div>
              <h2>Source Artifacts</h2>
              <p>Original files are checksum-linked and processed asynchronously.</p>
            </div>
          </div>
          {canManage ? (
            <form className="upload-box" onSubmit={(event) => void upload(event)}>
              <select value={artifactType} onChange={(event) => setArtifactType(event.target.value)}>
                <option value="uploaded_transcript">Uploaded transcript</option>
                <option value="provider_transcript">Provider transcript</option>
                <option value="audio">Audio recording</option>
                <option value="video">Video recording</option>
              </select>
              <input name="file" type="file" accept=".vtt,.txt,.md,audio/*,video/*" required />
              <button className="primary-button" disabled={uploading}>
                {uploading ? 'Uploading...' : 'Upload & process'}
              </button>
            </form>
          ) : null}
          <div className="artifact-list">
            {meeting.artifacts?.map((artifact) => (
              <div className="artifact-card" key={artifact.id}>
                <div className="file-icon">{artifact.artifact_type.includes('transcript') ? 'TXT' : 'AV'}</div>
                <div>
                  <strong>{artifact.original_filename ?? artifact.artifact_type}</strong>
                  <small>
                    {artifact.artifact_type.replaceAll('_', ' ')} ·{' '}
                    {artifact.file_size ? `${Math.round(artifact.file_size / 1024)} KB` : 'size unavailable'}
                  </small>
                </div>
                <StatusBadge value={artifact.status} />
              </div>
            ))}
            {!meeting.artifacts?.length ? <p className="muted">No artifacts have been uploaded.</p> : null}
          </div>
        </div>

        <aside className="panel">
          <div className="panel-heading">
            <div>
              <h2>Processing</h2>
              <p>Queue and AI service state.</p>
            </div>
          </div>
          <div className="process-state">
            <StatusBadge value={meeting.processing_status} />
            <button className="secondary-button" onClick={() => void load()}>
              Refresh
            </button>
          </div>
          <div className="job-list">
            {meeting.processing_jobs?.map((job) => (
              <div key={job.id}>
                <span>{job.stage}</span>
                <StatusBadge value={job.status} />
                {job.error_message ? <small className="error-text">{job.error_message}</small> : null}
              </div>
            ))}
          </div>

          <div className="panel-heading" style={{ marginTop: 24 }}>
            <div>
              <h2>Participants</h2>
              <p>Convenor and invited attendees.</p>
            </div>
          </div>
          <div className="participant-list">
            {participants.map((participant) => (
              <div className="participant-row" key={participant.user_id || participant.id}>
                <div>
                  <strong>{participantLabel(participant)}</strong>
                  {participantEmail(participant) ? <small>{participantEmail(participant)}</small> : null}
                </div>
                <div className="workspace-row-meta">
                  <ParticipationBadge role={participant.participation_role} />
                  <StatusBadge value={participant.status} />
                </div>
              </div>
            ))}
            {!participants.length ? <p className="muted">No participants listed yet.</p> : null}
          </div>
          {canManage ? (
            <form className="invite-form" onSubmit={(event) => void invite(event)}>
              <select value={inviteUserId} onChange={(event) => setInviteUserId(event.target.value)} required>
                <option value="">Invite a colleague...</option>
                {invitees.map((candidate) => (
                  <option key={candidate.id} value={candidate.id}>
                    {candidate.name} ({candidate.email})
                  </option>
                ))}
              </select>
              <button className="primary-button" disabled={inviting || !invitees.length}>
                {inviting ? 'Inviting...' : 'Invite'}
              </button>
            </form>
          ) : null}
          {canManage && !invitees.length ? (
            <p className="muted">Everyone in the organisation is already on this meeting, or no users are available to invite.</p>
          ) : null}
        </aside>
      </section>

      {recording ? (
        <section className="panel recording-panel">
          <div className="panel-heading">
            <div>
              <h2>Source Recording</h2>
              <p>Transcript timestamps seek directly into this immutable source artifact.</p>
            </div>
            <span className="checksum-label">SHA-256 linked</span>
          </div>
          <MediaPlayer meetingId={meeting.id} artifactId={recording.id} type={recording.artifact_type as 'audio' | 'video'} />
        </section>
      ) : null}

      <section className="panel summary-panel">
        <div className="panel-heading">
          <div>
            <h2>Meeting Notes</h2>
            <p>AI-generated summary from the transcript.</p>
          </div>
        </div>
        {summary ? (
          <MeetingSummaryView summary={summary} />
        ) : (
          <div className="empty-state">
            <h3>Notes not ready</h3>
            <p>Notes generate automatically once the transcript finishes processing.</p>
          </div>
        )}
      </section>

      <section className="panel transcript-panel">
        <div className="panel-heading">
          <div>
            <h2>Transcript Review</h2>
            <p>Click a speaker name to confirm or correct the identity.</p>
          </div>
          {transcript ? <span className="version-pill">Version {transcript.version_number}</span> : null}
        </div>
        {transcript?.segments?.length ? (
          <TranscriptViewer meetingId={meeting.id} initialSegments={transcript.segments} />
        ) : (
          <div className="empty-state">
            <h3>Transcript not ready</h3>
            <p>Upload the sample VTT file and refresh once the queue has completed processing.</p>
          </div>
        )}
      </section>
    </div>
  );
}