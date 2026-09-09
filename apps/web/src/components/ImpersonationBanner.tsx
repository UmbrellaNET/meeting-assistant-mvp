'use client';

import { useState } from 'react';
import { useAuth } from './AuthProvider';

export function ImpersonationBanner() {
  const { user, isImpersonating, stopImpersonation } = useAuth();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  if (!isImpersonating || !user) return null;

  const stop = async () => {
    setBusy(true);
    setError('');
    try {
      await stopImpersonation();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to stop impersonation');
      setBusy(false);
    }
  };

  return (
    <div className="impersonation-banner" role="status">
      <p>
        Viewing as {user.name} ({user.email})
        {error ? <span className="impersonation-banner-error">{error}</span> : null}
      </p>
      <button type="button" className="impersonation-banner-stop" onClick={() => void stop()} disabled={busy}>
        {busy ? 'Stopping...' : 'Stop impersonating'}
      </button>
    </div>
  );
}
