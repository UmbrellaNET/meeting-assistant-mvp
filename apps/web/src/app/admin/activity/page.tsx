'use client';

import { useEffect, useState } from 'react';
import { DataTable } from '@/components/DataTable';
import { Modal } from '@/components/Modal';
import { api } from '@/lib/api';
import { unwrapPaginated } from '@/lib/lists';
import type { ActivityLog, ActivityPerson, Paginated } from '@/lib/types';

const CHANNELS = [
  { value: '', label: 'All activity' },
  { value: 'auth', label: 'Authentication' },
  { value: 'users', label: 'Users' },
  { value: 'meetings', label: 'Meetings' },
  { value: 'settings', label: 'Settings' },
  { value: 'integrations', label: 'Integrations' },
  { value: 'system', label: 'System' },
];

function formatAction(value?: string | null): string {
  if (!value) return 'Unknown';
  return value.replaceAll(/[._-]+/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function personFrom(value: ActivityPerson | string | null | undefined, fallback?: string | null): string {
  if (typeof value === 'string' && value.trim()) return value;
  if (value && typeof value === 'object') {
    if (value.name?.trim()) return value.name;
    if (value.email?.trim()) return value.email;
    if (value.id) return value.id;
  }
  return fallback?.trim() || '—';
}

function formatActivityTime(iso?: string | null): string {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function formatLocation(log: ActivityLog): string {
  if (log.location?.trim()) return log.location;
  const parts = [log.city, log.region, log.country].filter((part): part is string => Boolean(part?.trim()));
  if (parts.length) return parts.join(', ');
  return log.ip_address?.trim() || '—';
}

function actorLabel(log: ActivityLog): string {
  const name = personFrom(log.actor, log.actor_name);
  if (log.impersonator?.name) {
    return `${name} (via ${log.impersonator.name})`;
  }
  return name;
}

function targetLabel(log: ActivityLog): string {
  return personFrom(log.target, log.subject_label);
}

function propertyRows(properties?: Record<string, unknown>): Array<{ label: string; value: string }> {
  if (!properties || Object.keys(properties).length === 0) return [];
  const rows: Array<{ label: string; value: string }> = [];
  const attributes = properties.attributes;
  const old = properties.old;
  if (attributes && typeof attributes === 'object') {
    Object.entries(attributes as Record<string, unknown>).forEach(([key, value]) => {
      const previous = old && typeof old === 'object' ? (old as Record<string, unknown>)[key] : undefined;
      rows.push({
        label: formatAction(key),
        value: previous === undefined ? stringify(value) : `${stringify(previous)} → ${stringify(value)}`,
      });
    });
    return rows;
  }
  Object.entries(properties).forEach(([key, value]) => {
    rows.push({ label: formatAction(key), value: stringify(value) });
  });
  return rows;
}

function stringify(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
}

export default function ActivityPage() {
  const [logs, setLogs] = useState<ActivityLog[] | null>(null);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [logName, setLogName] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [selected, setSelected] = useState<ActivityLog | null>(null);

  useEffect(() => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('per_page', '50');
    if (query.trim()) params.set('q', query.trim());
    if (logName) params.set('log_name', logName);

    let cancelled = false;
    api<Paginated<ActivityLog>>(`/admin/activity?${params.toString()}`)
      .then((payload) => {
        if (cancelled) return;
        const unwrapped = unwrapPaginated<ActivityLog>(payload);
        setLogs(unwrapped.data);
        setTotal(unwrapped.total);
        setLastPage(payload.last_page ?? 1);
        setError('');
      })
      .catch((err) => {
        if (cancelled) return;
        setLogs([]);
        setError(err instanceof Error ? err.message : 'Unable to load activity');
      });

    return () => {
      cancelled = true;
    };
  }, [query, logName, page]);

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>Activity Tracking</h1>
        </div>
      </header>
      <DataTable
        columns={[
          { key: 'time', label: 'Time' },
          { key: 'actor', label: 'Actor' },
          { key: 'action', label: 'Action' },
          { key: 'target', label: 'Target' },
          { key: 'location', label: 'Location' },
        ]}
        cols="minmax(0,1.2fr) minmax(0,1.1fr) minmax(0,1.2fr) minmax(0,1.1fr) minmax(0,1fr)"
        search
        searchPlaceholder="Search activity"
        searchValue={query}
        onSearchChange={(value) => {
          setQuery(value);
          setPage(1);
        }}
        loading={logs === null && !error}
        error={error}
        emptyTitle="No activity yet"
        emptyDescription="Activity will be recorded here as people manage users, meetings, and settings."
        filters={
          <div className="activity-filters">
            <label>
              <span className="sr-only">Channel</span>
              <select
                value={logName}
                onChange={(event) => {
                  setLogName(event.target.value);
                  setPage(1);
                }}
              >
                {CHANNELS.map((channel) => (
                  <option key={channel.value || 'all'} value={channel.value}>
                    {channel.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
        }
        rows={(logs ?? []).map((log) => {
          const actor = actorLabel(log);
          const target = targetLabel(log);
          const action = formatAction(log.description || log.event || log.action);
          const time = formatActivityTime(log.created_at ?? log.time);
          const location = formatLocation(log);
          return {
            id: log.id,
            searchText: `${time} ${actor} ${action} ${target} ${location}`,
            onClick: () => setSelected(log),
            cells: [time, actor, action, target, location],
          };
        })}
      />
      {lastPage > 1 ? (
        <div className="activity-pager">
          <button type="button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>
            Previous
          </button>
          <span>
            Page {page} of {lastPage}
            {total ? ` · ${total} events` : ''}
          </span>
          <button type="button" disabled={page >= lastPage} onClick={() => setPage((current) => current + 1)}>
            Next
          </button>
        </div>
      ) : null}

      <Modal
        open={Boolean(selected)}
        title={selected ? formatAction(selected.description || selected.event || selected.action) : 'Activity'}
        description={selected ? formatActivityTime(selected.created_at) : undefined}
        onClose={() => setSelected(null)}
      >
        {selected ? (
          <dl className="activity-detail">
            <div>
              <dt>Actor</dt>
              <dd>{actorLabel(selected)}</dd>
            </div>
            <div>
              <dt>Target</dt>
              <dd>{targetLabel(selected)}</dd>
            </div>
            <div>
              <dt>Channel</dt>
              <dd>{formatAction(selected.log_name)}</dd>
            </div>
            <div>
              <dt>Source</dt>
              <dd>{[selected.source, selected.integration].filter(Boolean).join(' · ') || '—'}</dd>
            </div>
            <div>
              <dt>IP address</dt>
              <dd>{selected.ip_address || '—'}</dd>
            </div>
            <div>
              <dt>Location</dt>
              <dd>{formatLocation(selected)}</dd>
            </div>
            <div>
              <dt>Browser</dt>
              <dd>
                {[selected.browser, selected.browser_version].filter(Boolean).join(' ') || '—'}
                {selected.os ? ` · ${selected.os}` : ''}
                {selected.device_type ? ` · ${selected.device_type}` : ''}
              </dd>
            </div>
            <div className="full">
              <dt>User agent</dt>
              <dd>{selected.user_agent || '—'}</dd>
            </div>
            {selected.impersonator ? (
              <div className="full">
                <dt>Impersonator</dt>
                <dd>
                  {selected.impersonator.name}
                  {selected.impersonator.email ? ` · ${selected.impersonator.email}` : ''}
                </dd>
              </div>
            ) : null}
            {propertyRows(selected.properties).map((row) => (
              <div key={row.label} className="full">
                <dt>{row.label}</dt>
                <dd>{row.value}</dd>
              </div>
            ))}
          </dl>
        ) : null}
      </Modal>
    </div>
  );
}
