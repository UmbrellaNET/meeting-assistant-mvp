'use client';

// Put in: components/ActionItemsPanel.tsx
import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import { participantLabel, unwrapList } from '@/lib/lists';
import type { ActionItem, ActionItemStatus, MeetingParticipant } from '@/lib/types';

const STATUS_LABELS: Record<ActionItemStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  done: 'Done',
  cancelled: 'Cancelled',
};

type Filter = 'all' | 'active' | 'closed';

type Props = {
  meetingId: string;
  participants: MeetingParticipant[];
  /** Convenors and admins: can add, assign and delete. */
  canManage: boolean;
  currentUserId?: string;
  /** Change this (for example the summary id) to reload after notes are regenerated. */
  refreshKey?: string | number | null;
};

function todayIso(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

const emptyDraft = { title: '', owner_user_id: '', due_date: '' };

export function ActionItemsPanel({ meetingId, participants, canManage, currentUserId, refreshKey }: Props) {
  const toast = useToast();
  const [items, setItems] = useState<ActionItem[] | null>(null);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const [draft, setDraft] = useState(emptyDraft);
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<ActionItem | null>(null);
  const [busy, setBusy] = useState(false);

  const owners = useMemo(() => participants.filter((participant) => participant.user_id), [participants]);

  const load = useCallback(async () => {
    try {
      setItems(unwrapList<ActionItem>(await api(`/meetings/${meetingId}/action-items`)));
      setError('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load action items');
    }
  }, [meetingId]);

  useEffect(() => {
    void load();
  }, [load, refreshKey]);

  const update = async (
    item: ActionItem,
    changes: Partial<Pick<ActionItem, 'title' | 'owner_user_id' | 'due_date' | 'status' | 'priority'>>,
  ) => {
    const previous = items;
    setItems((current) => (current ?? []).map((row) => (row.id === item.id ? { ...row, ...changes } : row)));
    try {
      const saved = await api<ActionItem>(`/action-items/${item.id}`, {
        method: 'PATCH',
        body: JSON.stringify(changes),
      });
      setItems((current) => (current ?? []).map((row) => (row.id === item.id ? { ...row, ...saved } : row)));
    } catch (err) {
      setItems(previous);
      toast.error(err instanceof Error ? err.message : 'Unable to update action item');
    }
  };

  const add = async (event: FormEvent) => {
    event.preventDefault();
    if (!draft.title.trim()) return;
    setAdding(true);
    try {
      const created = await api<ActionItem>(`/meetings/${meetingId}/action-items`, {
        method: 'POST',
        body: JSON.stringify({
          title: draft.title.trim(),
          owner_user_id: draft.owner_user_id || null,
          due_date: draft.due_date || null,
        }),
      });
      setItems((current) => [...(current ?? []), created]);
      setDraft(emptyDraft);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to add action item');
    } finally {
      setAdding(false);
    }
  };

  const remove = async () => {
    if (!deleting) return;
    setBusy(true);
    try {
      await api(`/action-items/${deleting.id}`, { method: 'DELETE' });
      setItems((current) => (current ?? []).filter((row) => row.id !== deleting.id));
      setDeleting(null);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to delete action item');
    } finally {
      setBusy(false);
    }
  };

  const today = todayIso();
  const visible = (items ?? []).filter((item) => {
    const closed = item.status === 'done' || item.status === 'cancelled';
    if (filter === 'active') return !closed;
    if (filter === 'closed') return closed;
    return true;
  });
  const activeCount = (items ?? []).filter((item) => item.status === 'open' || item.status === 'in_progress').length;

  return (
    <section className="panel action-items">
      <div className="panel-heading">
        <div>
          <h2>Action Items</h2>
          <p>
            Owners, due dates and status for follow-ups
            {items ? ` · ${activeCount} open` : ''}
          </p>
        </div>
        <div className="filter-row" role="tablist" aria-label="Action item filter">
          {([
            ['all', 'All'],
            ['active', 'Open'],
            ['closed', 'Closed'],
          ] as const).map(([value, label]) => (
            <button
              key={value}
              type="button"
              className={`filter-chip${filter === value ? ' active' : ''}`}
              onClick={() => setFilter(value)}
            >
              {label}
            </button>
          ))}
        </div>
      </div>

      {error ? <div className="error-box">{error}</div> : null}

      {canManage ? (
        <form className="action-add" onSubmit={(event) => void add(event)}>
          <input
            value={draft.title}
            onChange={(event) => setDraft((current) => ({ ...current, title: event.target.value }))}
            placeholder="Add an action item"
            aria-label="Action item"
            maxLength={1000}
          />
          <select
            value={draft.owner_user_id}
            onChange={(event) => setDraft((current) => ({ ...current, owner_user_id: event.target.value }))}
            aria-label="Owner"
          >
            <option value="">Unassigned</option>
            {owners.map((participant) => (
              <option key={participant.user_id} value={participant.user_id}>
                {participantLabel(participant)}
              </option>
            ))}
          </select>
          <input
            type="date"
            value={draft.due_date}
            onChange={(event) => setDraft((current) => ({ ...current, due_date: event.target.value }))}
            aria-label="Due date"
          />
          <button className="primary-button" disabled={adding || !draft.title.trim()}>
            {adding ? 'Adding...' : 'Add'}
          </button>
        </form>
      ) : null}

      {items === null && !error ? <p className="muted">Loading action items...</p> : null}

      {items !== null && !visible.length ? (
        <div className="empty-state">
          <h3>{items.length ? 'Nothing in this view' : 'No action items yet'}</h3>
          <p>
            {items.length
              ? 'Try another filter.'
              : 'They are created automatically from the meeting notes, and you can add your own.'}
          </p>
        </div>
      ) : null}

      <div className="action-list">
        {visible.map((item) => {
          const canEdit = canManage || (Boolean(currentUserId) && item.owner_user_id === currentUserId);
          const closed = item.status === 'done' || item.status === 'cancelled';
          const dueDate = item.due_date ? item.due_date.slice(0, 10) : '';
          const overdue = Boolean(dueDate) && dueDate < today && !closed;
          const unmatchedOwner = !item.owner_user_id && item.owner_name;

          return (
            <div className={`action-row${closed ? ' is-closed' : ''}`} key={item.id}>
              <input
                type="checkbox"
                className="action-check"
                checked={item.status === 'done'}
                disabled={!canEdit}
                onChange={(event) => void update(item, { status: event.target.checked ? 'done' : 'open' })}
                aria-label={`Mark "${item.title}" as done`}
              />
              <div className="action-main">
                <strong>{item.title}</strong>
                {item.context ? <small>{item.context}</small> : null}
                {unmatchedOwner ? <small>Owner named in the meeting: {item.owner_name}</small> : null}
                {item.due_text && !dueDate ? <small>Deadline mentioned: {item.due_text}</small> : null}
              </div>
              <select
                value={item.owner_user_id ?? ''}
                disabled={!canManage}
                onChange={(event) => void update(item, { owner_user_id: event.target.value || null })}
                aria-label="Owner"
              >
                <option value="">Unassigned</option>
                {owners.map((participant) => (
                  <option key={participant.user_id} value={participant.user_id}>
                    {participantLabel(participant)}
                  </option>
                ))}
              </select>
              <input
                type="date"
                className={overdue ? 'is-overdue' : undefined}
                value={dueDate}
                disabled={!canEdit}
                onChange={(event) => void update(item, { due_date: event.target.value || null })}
                aria-label="Due date"
              />
              <select
                value={item.status}
                disabled={!canEdit}
                onChange={(event) => void update(item, { status: event.target.value as ActionItemStatus })}
                aria-label="Status"
              >
                {(Object.keys(STATUS_LABELS) as ActionItemStatus[]).map((status) => (
                  <option key={status} value={status}>
                    {STATUS_LABELS[status]}
                  </option>
                ))}
              </select>
              <span className={`priority-pill priority-${item.priority}`}>{item.priority}</span>
              {canManage ? (
                <button
                  type="button"
                  className="table-action"
                  onClick={() => setDeleting(item)}
                  aria-label={`Delete "${item.title}"`}
                >
                  Delete
                </button>
              ) : (
                <span />
              )}
            </div>
          );
        })}
      </div>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Delete Action Item"
        description={deleting ? `Delete “${deleting.title}”? This cannot be undone.` : ''}
        busy={busy}
        onClose={() => {
          if (!busy) setDeleting(null);
        }}
        onConfirm={() => void remove()}
      />
    </section>
  );
}
