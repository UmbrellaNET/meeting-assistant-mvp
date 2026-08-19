'use client';

import { useEffect, useId, useRef, useState } from 'react';
import { UserAvatar } from './UserAvatar';

type UserMenuProps = {
  name: string;
  email: string;
  onLogout: () => void;
};

export function UserMenu({ name, email, onLogout }: UserMenuProps) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const panelId = useId();

  useEffect(() => {
    if (!open) return;

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setOpen(false);
    };

    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);

  return (
    <div className="top-nav-menu" ref={rootRef}>
      <button
        type="button"
        className="avatar-button"
        aria-label={`Account menu for ${name}`}
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((value) => !value)}
      >
        <UserAvatar name={name} />
      </button>
      {open ? (
        <div className="top-nav-popover user-menu-popover" id={panelId} role="menu">
          <div className="user-menu-header">
            <UserAvatar name={name} size="sm" />
            <div>
              <strong>{name}</strong>
              <small>{email}</small>
            </div>
          </div>
          <button
            type="button"
            className="user-menu-action"
            role="menuitem"
            onClick={() => {
              setOpen(false);
              onLogout();
            }}
          >
            Sign out
          </button>
        </div>
      ) : null}
    </div>
  );
}
