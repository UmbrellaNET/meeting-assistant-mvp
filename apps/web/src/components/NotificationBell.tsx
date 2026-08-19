'use client';

import { useEffect, useId, useRef, useState } from 'react';

export function NotificationBell() {
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
        className="icon-button"
        aria-label="Notifications"
        aria-expanded={open}
        aria-controls={panelId}
        onClick={() => setOpen((value) => !value)}
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 1 0-14 0v5l-1.6 1.6A1 1 0 0 0 4.1 19h15.8a1 1 0 0 0 .7-1.7L19 16Z"
            fill="currentColor"
          />
        </svg>
      </button>
      {open ? (
        <div className="top-nav-popover" id={panelId} role="dialog" aria-label="Notifications">
          <p className="top-nav-popover-empty">No notifications yet</p>
        </div>
      ) : null}
    </div>
  );
}
