'use client';


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

/** A person who can own an item: a meeting participant with an account, or a speaker heard in the transcript. */
type Person = { key: string; label: string; userId: string | null };

type Props = {
  meetingId: string;
  participants: MeetingParticipant[];
  /** Speakers identified in this meeting's transcript. */
  speakers?: { display_name?: string | null }[];
  /** Convenors and admins: can add, assign and delete. */
  canManage: boolean;
  currentUserId?: string;
  /** Change this (for example the summary id) to reload after notes are regenerated. */
  refreshKey?: string | number | null;
};

const normalize = (value: string) => value.trim().toLowerCase();

function todayIso(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

/** Turn a select value ("user:<id>", "name:<label>" or "") into the fields the API expects. */
function ownerChanges(value: string): { owner_user_id: string | null; owner_name: string | null } {
  if (value.startsWith('user:')) return { owner_user_id: value.slice(5), owner_name: null };
  if (value.startsWith('name:')) return { owner_user_id: null, owner_name: value.slice(5) };
  return { owner_user_id: null, owner_name: null };
}

const emptyDraft = { title: '', owner: '', due_date: '' };

export function ActionItemsPanel({ meetingId, participants, speakers = [], canManage, currentUserId, refreshKey }: Props) {
  const toast = useToast();
  const [items, setItems] = useState<ActionItem[] | null>(null);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const [draft, setDraft] = useState(emptyDraft);
  const [adding, setAdding] = useState(false);
  const [deleting, setDeleting] = useState<ActionItem | null>(null);
  const [busy, setBusy] = useState(false);

  // People in this meeting: everyone heard in the transcript, plus participants. A speaker whose name
  // matches a participant is merged into that participant so the item is linked to their account.
  const people = useMemo<Person[]>(() => {
    const list: Person[] = [];
    const seen = new Set<string>();

    for (const participant of participants) {
      if (!participant.user_id) continue;
      const label = participantLabel(participant);
      list.push({ key: `user:${participant.user_id}`, label, userId: participant.user_id });
      seen.add(normalize(label));
    }

    for (const speaker of speakers) {
      const label = (speaker.display_name ?? '').trim();
      if (!label || seen.has(normalize(label))) continue;

      // "Tom" in the transcript matches "Tom Smith" the participant, if that is the only Tom.
      const firstNameMatches = list.filter(
        (person) => person.userId && normalize(person.label).split(/\s+/).includes(normalize(label)),
      );
      if (firstNameMatches.length === 1) continue;

      list.push({ key: `name:${label}`, label, userId: null });
      seen.add(normalize(label));
    }

    return list.sort((a, b) => a.label.localeCompare(b.label));
  }, [participants, speakers]);

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

  /** The select value that represents an item's current owner. */
  const ownerValue = (item: ActionItem): string => {
    if (item.owner_user_id) return `user:${item.owner_user_id}`;
    const name = item.owner_name?.trim();
    if (!name) return '';
    const match = people.find((person) => !person.userId && normalize(person.label) === normalize(name));
    return match ? match.key : `name:${name}`;
  };

  /** Options for one row: the meeting's people, plus the item's current owner if they are not in that list. */
  const optionsFor = (item: ActionItem): Person[] => {
    const current = ownerValue(item);
    if (!current || people.some((person) => person.key === current)) return people;
    const label = current.startsWith('name:') ? current.slice(5) : (item.owner_name ?? 'Unknown');
    return [...people, { key: current, label, userId: null }];
  };

  const update = async (
    item: ActionItem,
    changes: Partial<Pick<ActionItem, 'title' | 'owner_user_id' | 'owner_name' | 'due_date' | 'status' | 'priority'>>,
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

  const changeOwner = (item: ActionItem, value: string) => {
    const changes = ownerChanges(value);
    // Show the chosen person's name straight away; the server's answer replaces it.
    const label = people.find((person) => person.key === value)?.label ?? changes.owner_name;
    void update(item, { ...changes, owner_name: changes.owner_user_id ? label : changes.owner_name });
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
          ...ownerChanges(draft.owner),
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
            value={draft.owner}
            onChange={(event) => setDraft((current) => ({ ...current, owner: event.target.value }))}
            aria-label="Owner"
          >
            <option value="">Unassigned</option>
            {people.map((person) => (
              <option key={person.key} value={person.key}>
                {person.label}
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
                {item.due_text && !dueDate ? <small>Deadline mentioned: {item.due_text}</small> : null}
              </div>
              <select
                value={ownerValue(item)}
                disabled={!canManage}
                onChange={(event) => changeOwner(item, event.target.value)}
                aria-label="Owner"
              >
                <option value="">Unassigned</option>
                {optionsFor(item).map((person) => (
                  <option key={person.key} value={person.key}>
                    {person.label}
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
