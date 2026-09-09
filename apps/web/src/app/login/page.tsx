'use client';
import { FormEvent, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { DEFAULT_LOGO_URL } from '@/lib/brandColors';

export default function LoginPage() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await login(email, password);
      router.replace('/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="login-page">
      <section className="login-panel">
        <div className="brand login-brand">
          <img
            src={DEFAULT_LOGO_URL}
            alt="UmbrellaNET"
            className="brand-logo"
          />
        </div>
        <form onSubmit={submit} className="form-card">
          <label>
            Email
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </label>
          <label>
            Password
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </label>
          {error && <div className="error-box">{error}</div>}
          <button className="primary-button" disabled={busy}>
            {busy ? 'Signing in...' : 'Sign in'}
          </button>
        </form>
      </section>
      <aside className="login-visual">
        <div className="visual-card">
          <span>01</span>
          <h3>Capture</h3>
          <p>Store original meeting recordings and transcripts.</p>
        </div>
        <div className="visual-card">
          <span>02</span>
          <h3>Process</h3>
          <p>Normalize timestamped, speaker-aware transcript segments.</p>
        </div>
        <div className="visual-card">
          <span>03</span>
          <h3>Verify</h3>
          <p>Trace generated outputs back to the source evidence.</p>
        </div>
      </aside>
    </div>
  );
}
