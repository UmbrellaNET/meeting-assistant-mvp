'use client';

import { useCallback, useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { IconButton, TableActions } from '@/components/IconButton';
import { ParticipationBadge } from '@/components/ParticipationBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import { formatWhen, meetingHasNotes, unwrapPaginated } from '@/lib/lists';
import type { Meeting } from '@/lib/types';

export default function AssistantPage() {
  const toast = useToast();
  const [meetings, setMeetings] = useState<Meeting[] | null>(null);
  const [error, setError] = useState('');
  const [deleting, setDeleting] = useState<Meeting | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    return api<unknown>('/meetings')
      .then((payload) => setMeetings(unwrapPaginated<Meeting>(payload).data.filter(meetingHasNotes)))
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load notes'));
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

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
          <h1>Meeting Assistant</h1>
        </div>
      </header>

      <DataTable
        search
        searchPlaceholder="Search notes"
        loading={meetings === null && !error}
        error={error}
        emptyTitle="No notes yet"
        emptyDescription="Create a meeting to become Convenor, or accept an invitation. Notes appear here once a transcript or artifact is ready."
        cols="minmax(0,2fr) minmax(0,1.4fr) .9fr 1fr 96px"
        action={<IconButton name="plus" label="Create Meeting" variant="primary" href="/meetings/new" />}
        columns={[
          { key: 'meeting', label: 'Meeting' },
          { key: 'when', label: 'When' },
          { key: 'role', label: 'Role' },
          { key: 'status', label: 'Status' },
          { key: 'actions', label: '' },
        ]}
        rows={(meetings ?? []).map((meeting) => ({
          id: meeting.id,
          searchText: `${meeting.title} ${meeting.provider} ${meeting.status}`,
          cells: [
            <>
              <strong>{meeting.title}</strong>
              <small className="provider">{meeting.provider}</small>
            </>,
            formatWhen(meeting.scheduled_start_at ?? meeting.created_at),
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
