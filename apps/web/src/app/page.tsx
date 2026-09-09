'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { IconButton, TableActions } from '@/components/IconButton';
import { ParticipationBadge } from '@/components/ParticipationBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { useAuth } from '@/components/AuthProvider';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import {
  formatWhen,
  invitationMeetingId,
  invitationRole,
  invitationTitle,
  invitationWhen,
  isProcessingMeeting,
  isScheduledThisWeek,
  meetingHasNotes,
  unwrapList,
  unwrapPaginated,
} from '@/lib/lists';
import type { DashboardPayload, Invitation, Meeting, Paginated } from '@/lib/types';

type WorkspaceState = {
  pending: Invitation[];
  upcoming: Meeting[];
  notes: Meeting[];
  orgTotal?: number;
  orgReady?: number;
  orgProcessing?: number;
};

async function loadWorkspace(isSuperAdmin: boolean): Promise<WorkspaceState> {
  try {
    const dashboard = await api<DashboardPayload>('/dashboard');
    const state: WorkspaceState = {
      pending: unwrapList<Invitation>(dashboard.pending_invitations).filter((item) => invitationMeetingId(item)),
      upcoming: unwrapList<Meeting>(dashboard.upcoming_meetings),
      notes: unwrapList<Meeting>(dashboard.notes_ready),
      orgTotal: dashboard.total_meetings,
      orgReady: dashboard.ready_count,
      orgProcessing: dashboard.processing_count,
    };
    if (isSuperAdmin && state.orgTotal == null) {
      try {
        const meetingsPage = unwrapPaginated<Meeting>(await api('/meetings'));
        state.orgTotal = meetingsPage.total;
        state.orgReady = meetingsPage.data.filter((meeting) => meeting.status === 'ready').length;
        state.orgProcessing = meetingsPage.data.filter(isProcessingMeeting).length;
      } catch {
        // Org strip stays empty when the meetings list is unavailable.
      }
    }
    return state;
  } catch {
    // Fall back to list endpoints if the aggregate is not ready yet.
  }

  const [invitationsResult, meetingsResult] = await Promise.allSettled([
    api<unknown>('/invitations'),
    api<Paginated<Meeting>>('/meetings'),
  ]);

  const pending =
    invitationsResult.status === 'fulfilled'
      ? unwrapList<Invitation>(invitationsResult.value).filter((item) => invitationMeetingId(item))
      : [];

  const meetingsPage =
    meetingsResult.status === 'fulfilled' ? unwrapPaginated<Meeting>(meetingsResult.value) : { data: [], total: 0 };
  const meetings = meetingsPage.data;
  const upcoming = meetings.filter((meeting) => isScheduledThisWeek(meeting.scheduled_start_at));
  const notes = meetings.filter(meetingHasNotes);

  return {
    pending,
    upcoming,
    notes,
    orgTotal: isSuperAdmin ? meetingsPage.total : undefined,
    orgReady: isSuperAdmin ? meetings.filter((meeting) => meeting.status === 'ready').length : undefined,
    orgProcessing: isSuperAdmin ? meetings.filter(isProcessingMeeting).length : undefined,
  };
}

export default function Dashboard() {
  const { isSuperAdmin } = useAuth();
  const toast = useToast();
  const [workspace, setWorkspace] = useState<WorkspaceState | null>(null);
  const [error, setError] = useState('');
  const [actingId, setActingId] = useState<string>('');
  const [deleting, setDeleting] = useState<Meeting | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError('');
    return loadWorkspace(isSuperAdmin)
      .then(setWorkspace)
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load dashboard'));
  }, [isSuperAdmin]);

  useEffect(() => {
    void load();
  }, [load]);

  const respond = async (meetingId: string, status: 'accepted' | 'declined') => {
    setActingId(`${meetingId}:${status}`);
    setError('');
    try {
      await api(`/meetings/${meetingId}/invitations/respond`, {
        method: 'POST',
        body: JSON.stringify({ status }),
      });
      toast.success(status === 'accepted' ? 'Invitation accepted.' : 'Invitation declined.');
      await load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to update invitation');
    } finally {
      setActingId('');
    }
  };

  const remove = async () => {
    if (!deleting) return;
    setBusy(true);
    try {
      await api(`/meetings/${deleting.id}`, { method: 'DELETE' });
      toast.success('Meeting deleted.');
      setDeleting(null);
      await load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to delete meeting');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>Dashboard</h1>
        </div>
        <Link className="primary-button" href="/meetings/new">
          Create Meeting
        </Link>
      </header>

      {error ? <div className="error-box">{error}</div> : null}

      {isSuperAdmin && workspace ? (
        <section className="metric-grid org-strip">
          <div className="metric">
            <small>Organisation meetings</small>
            <strong>{workspace.orgTotal ?? '—'}</strong>
            <span>Visible across this tenant</span>
          </div>
          <div className="metric">
            <small>Ready for review</small>
            <strong>{workspace.orgReady ?? '—'}</strong>
            <span>Transcript available</span>
          </div>
          <div className="metric">
            <small>In processing</small>
            <strong>{workspace.orgProcessing ?? '—'}</strong>
            <span>Queue or AI worker</span>
          </div>
        </section>
      ) : null}

      <section className="metric-grid">
        <div className="metric">
          <small>Pending invitations</small>
          <strong>{workspace?.pending.length ?? '—'}</strong>
          <span>Waiting for your response</span>
        </div>
        <div className="metric">
          <small>Upcoming this week</small>
          <strong>{workspace?.upcoming.length ?? '—'}</strong>
          <span>Meetings you convene or attend</span>
        </div>
        <div className="metric">
          <small>Notes ready</small>
          <strong>{workspace?.notes.length ?? '—'}</strong>
          <span>Transcripts or artifacts to review</span>
        </div>
      </section>

      <DataTable
        loading={workspace === null && !error}
        emptyTitle="No pending invitations"
        emptyDescription="When someone invites you to a meeting, it will appear here."
        cols="minmax(0,2fr) minmax(0,1.3fr) .9fr 132px"
        columns={[
          { key: 'meeting', label: 'Meeting' },
          { key: 'when', label: 'When' },
          { key: 'role', label: 'Role' },
          { key: 'actions', label: '' },
        ]}
        rows={(workspace?.pending ?? []).map((invitation) => {
          const meetingId = invitationMeetingId(invitation);
          return {
            id: meetingId,
            searchText: invitationTitle(invitation),
            cells: [
              <strong key="title">{invitationTitle(invitation)}</strong>,
              formatWhen(invitationWhen(invitation)),
              <ParticipationBadge key="role" role={invitationRole(invitation)} />,
              <TableActions key="actions">
                {meetingId ? <IconButton name="view" label="View Meeting" href={`/meetings/${meetingId}`} /> : null}
                <IconButton
                  name="check"
                  label="Accept Invitation"
                  variant="success"
                  disabled={Boolean(actingId)}
                  onClick={() => void respond(meetingId, 'accepted')}
                />
                <IconButton
                  name="close"
                  label="Decline Invitation"
                  variant="danger"
                  disabled={Boolean(actingId)}
                  onClick={() => void respond(meetingId, 'declined')}
                />
              </TableActions>,
            ],
          };
        })}
      />

      <DataTable
        loading={workspace === null && !error}
        emptyTitle="Nothing scheduled this week"
        emptyDescription="Create a meeting to become Convenor, or accept an invitation."
        cols="minmax(0,2fr) minmax(0,1.4fr) .9fr 1fr 96px"
        columns={[
          { key: 'meeting', label: 'Meeting' },
          { key: 'when', label: 'When' },
          { key: 'role', label: 'Role' },
          { key: 'status', label: 'Status' },
          { key: 'actions', label: '' },
        ]}
        rows={(workspace?.upcoming ?? []).map((meeting) => ({
          id: meeting.id,
          cells: [
            <strong key="title">{meeting.title}</strong>,
            formatWhen(meeting.scheduled_start_at),
            <ParticipationBadge key="role" role={meeting.my_participation?.participation_role} />,
            <StatusBadge key="status" value={meeting.status} />,
            <TableActions key="actions">
              <IconButton name="view" label="View Meeting" href={`/meetings/${meeting.id}`} />
              <IconButton name="trash" label="Delete Meeting" variant="danger" onClick={() => setDeleting(meeting)} />
            </TableActions>,
          ],
        }))}
      />

      <DataTable
        loading={workspace === null && !error}
        emptyTitle="No notes yet"
        emptyDescription="Notes appear after a meeting has a transcript or uploaded artifacts."
        cols="minmax(0,2fr) minmax(0,1.3fr) .9fr 1fr 96px"
        columns={[
          { key: 'meeting', label: 'Meeting' },
          { key: 'processing', label: 'Processing' },
          { key: 'role', label: 'Role' },
          { key: 'status', label: 'Status' },
          { key: 'actions', label: '' },
        ]}
        rows={(workspace?.notes ?? []).map((meeting) => ({
          id: meeting.id,
          cells: [
            <strong key="title">{meeting.title}</strong>,
            meeting.processing_status.replaceAll('_', ' '),
            <ParticipationBadge key="role" role={meeting.my_participation?.participation_role} />,
            <StatusBadge key="status" value={meeting.status} />,
            <TableActions key="actions">
              <IconButton name="view" label="View Meeting" href={`/meetings/${meeting.id}`} />
              <IconButton name="trash" label="Delete Meeting" variant="danger" onClick={() => setDeleting(meeting)} />
            </TableActions>,
          ],
        }))}
      />
      <ConfirmDialog
        open={Boolean(deleting)}
        title="Delete Meeting"
        description={deleting ? `Delete “${deleting.title}”? This cannot be undone.` : ''}
        busy={busy}
        onClose={() => {
          if (!busy) setDeleting(null);
        }}
        onConfirm={() => void remove()}
      />
    </div>
  );
}
