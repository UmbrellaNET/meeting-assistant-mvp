'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { IconButton, TableActions } from '@/components/IconButton';
import { ParticipationBadge } from '@/components/ParticipationBadge';
import { StatusBadge } from '@/components/StatusBadge';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import { unwrapPaginated } from '@/lib/lists';
import type { Meeting, ParticipationRole } from '@/lib/types';

type RoleFilter = 'all' | ParticipationRole;

export default function MeetingsIndex() {
  const toast = useToast();
  const [meetings, setMeetings] = useState<Meeting[] | null>(null);
  const [error, setError] = useState('');
  const [roleFilter, setRoleFilter] = useState<RoleFilter>('all');
  const [deleting, setDeleting] = useState<Meeting | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    return api<unknown>('/meetings')
      .then((payload) => setMeetings(unwrapPaginated<Meeting>(payload).data))
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load meetings'));
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const filtered = useMemo(() => {
    if (!meetings) return [];
    if (roleFilter === 'all') return meetings;
    return meetings.filter((meeting) => meeting.my_participation?.participation_role === roleFilter);
  }, [meetings, roleFilter]);

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
          <h1>Meetings</h1>
        </div>
      </header>
      <DataTable
        search
        searchPlaceholder="Search meetings"
        loading={meetings === null && !error}
        error={error}
        emptyTitle={roleFilter === 'all' ? 'No meetings yet' : `No ${roleFilter} meetings`}
        emptyDescription={
          roleFilter === 'all'
            ? 'Create a meeting to become Convenor, or accept an invitation to attend.'
            : 'Try another participation filter, or create a meeting.'
        }
        cols="minmax(0,2fr) .9fr 1fr 1fr 1fr 96px"
        action={<IconButton name="plus" label="Create Meeting" variant="primary" href="/meetings/new" />}
        filters={
          <div className="filter-row" role="tablist" aria-label="Participation filter">
            {([
              ['all', 'All'],
              ['convenor', 'Convenor'],
              ['attendee', 'Attendee'],
            ] as const).map(([value, label]) => (
              <button
                key={value}
                type="button"
                className={`filter-chip${roleFilter === value ? ' active' : ''}`}
                onClick={() => setRoleFilter(value)}
              >
                {label}
              </button>
            ))}
          </div>
        }
        columns={[
          { key: 'meeting', label: 'Meeting' },
          { key: 'role', label: 'Role' },
          { key: 'provider', label: 'Provider' },
          { key: 'status', label: 'Status' },
          { key: 'created', label: 'Created' },
          { key: 'actions', label: '' },
        ]}
        rows={filtered.map((meeting) => ({
          id: meeting.id,
          searchText: `${meeting.title} ${meeting.provider} ${meeting.status}`,
          cells: [
            <>
              <strong>{meeting.title}</strong>
              <small>{meeting.processing_status.replaceAll('_', ' ')}</small>
            </>,
            <ParticipationBadge key="role" role={meeting.my_participation?.participation_role} />,
            <span className="provider" key="provider">{meeting.provider}</span>,
            <StatusBadge key="status" value={meeting.status} />,
            new Date(meeting.created_at).toLocaleDateString(),
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
