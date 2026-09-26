'use client';

import { Modal } from './Modal';

type ConfirmDialogProps = {
  open: boolean;
  title: string;
  description: string;
  confirmLabel?: string;
  busyLabel?: string;
  busy?: boolean;
  onConfirm: () => void;
  onClose: () => void;
};

export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel = 'Delete',
  busyLabel,
  busy = false,
  onConfirm,
  onClose,
}: ConfirmDialogProps) {
  return (
    <Modal
      open={open}
      onClose={() => {
        if (!busy) onClose();
      }}
      title={title}
      description={description}
      footer={
        <>
          <button type="button" className="secondary-button" onClick={onClose} disabled={busy}>
            Cancel
          </button>
          <button type="button" className="danger-button" onClick={onConfirm} disabled={busy}>
            {busy ? busyLabel ?? (confirmLabel === 'Delete' ? 'Deleting...' : `${confirmLabel}...`) : confirmLabel}
          </button>
        </>
      }
    />
  );
}
