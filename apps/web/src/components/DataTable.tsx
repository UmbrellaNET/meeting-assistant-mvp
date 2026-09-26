'use client';

import Link from 'next/link';
import { useMemo, useState, type CSSProperties, type ReactNode } from 'react';

export type DataTableColumn = {
  key: string;
  label: string;
};

export type DataTableRow = {
  id: string;
  href?: string;
  onClick?: () => void;
  cells: ReactNode[];
  searchText?: string;
};

type DataTableProps = {
  columns: DataTableColumn[];
  rows: DataTableRow[];
  title?: string;
  description?: string;
  action?: ReactNode;
  search?: boolean;
  searchPlaceholder?: string;
  emptyTitle: string;
  emptyDescription?: string;
  loading?: boolean;
  error?: string;
  filters?: ReactNode;
  cols?: string;
  searchValue?: string;
  onSearchChange?: (value: string) => void;
};

export function DataTable({
  columns,
  rows,
  title,
  description,
  action,
  search = false,
  searchPlaceholder = 'Search',
  emptyTitle,
  emptyDescription,
  loading = false,
  error,
  filters,
  cols,
  searchValue,
  onSearchChange,
}: DataTableProps) {
  const [query, setQuery] = useState('');
  const searchText = searchValue ?? query;
  const serverSearch = typeof onSearchChange === 'function';

  const visibleRows = useMemo(() => {
    if (serverSearch) return rows;
    const needle = searchText.trim().toLowerCase();
    if (!needle) return rows;
    return rows.filter((row) => (row.searchText ?? '').toLowerCase().includes(needle));
  }, [rows, searchText, serverSearch]);

  const showToolbar = Boolean(title || search || action);

  return (
    <section className="data-table" style={cols ? ({ '--table-cols': cols } as CSSProperties) : undefined}>
      {showToolbar ? (
      <div className="data-table-toolbar">
        {title ? (
          <div className="data-table-heading">
            <h2>{title}</h2>
            {description ? <p>{description}</p> : null}
          </div>
        ) : null}
        <div className="data-table-toolbar-actions">
          {search ? (
            <label className="data-table-search">
              <span className="sr-only">{searchPlaceholder}</span>
              <input
                type="search"
                value={searchText}
                onChange={(event) => {
                  if (onSearchChange) onSearchChange(event.target.value);
                  else setQuery(event.target.value);
                }}
                placeholder={searchPlaceholder}
              />
            </label>
          ) : null}
          {action}
        </div>
      </div>
      ) : null}
      {filters}
      {error ? <div className="error-box">{error}</div> : null}
      <div className="data-table-body">
        <div className="data-table-row data-table-head">
          {columns.map((column) => (
            <span key={column.key}>{column.label}</span>
          ))}
        </div>
        {loading && !rows.length ? (
          <div className="data-table-status">
            <div className="loader" />
            <p>Loading...</p>
          </div>
        ) : null}
        {visibleRows.map((row) => {
          const className = `data-table-row${row.href || row.onClick ? ' is-link' : ''}`;
          const content = row.cells.map((cell, index) => <span key={`${row.id}-${index}`}>{cell}</span>);
          if (row.href) {
            return (
              <Link className={className} href={row.href} key={row.id}>
                {content}
              </Link>
            );
          }
          if (row.onClick) {
            return (
              <button type="button" className={className} key={row.id} onClick={row.onClick}>
                {content}
              </button>
            );
          }
          return (
            <div className={className} key={row.id}>
              {content}
            </div>
          );
        })}
        {!loading && !visibleRows.length ? (
          <div className="empty-state data-table-empty">
            <h3>{searchText.trim() ? 'No matching results' : emptyTitle}</h3>
            <p>
              {searchText.trim()
                ? 'Try a different search term.'
                : emptyDescription ?? ''}
            </p>
          </div>
        ) : null}
      </div>
    </section>
  );
}
