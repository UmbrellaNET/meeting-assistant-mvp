'use client';

import Link from 'next/link';

const icons = {
  plus: 'M12 5v14M5 12h14',
  view: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  edit: 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z',
  trash: 'M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2',
  check: 'M5 12.5 9.5 17 19 7',
  close: 'M6 6l12 12M18 6 6 18',
  impersonate: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M16 11h6 M19 8l3 3-3 3',
} as const;

export type IconName = keyof typeof icons;

type IconButtonProps = {
  name: IconName;
  label: string;
  href?: string;
  onClick?: () => void;
  variant?: 'ghost' | 'primary' | 'danger' | 'success';
  disabled?: boolean;
};

export function IconButton({
  name,
  label,
  href,
  onClick,
  variant = 'ghost',
  disabled = false,
}: IconButtonProps) {
  const className = `icon-action icon-action-${variant}`;
  const content = (
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <path d={icons[name]} stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );

  if (href) {
    return (
      <Link className={className} href={href} aria-label={label} title={label}>
        {content}
      </Link>
    );
  }

  return (
    <button
      type="button"
      className={className}
      aria-label={label}
      title={label}
      disabled={disabled}
      onClick={onClick}
    >
      {content}
    </button>
  );
}

export function TableActions({ children }: { children: React.ReactNode }) {
  return <span className="data-table-actions">{children}</span>;
}
