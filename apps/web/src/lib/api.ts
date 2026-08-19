const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export class ApiError extends Error {
  constructor(public status: number, message: string) { super(message); }
}

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem('meeting_token');
}

export function setToken(token: string | null): void {
  if (typeof window === 'undefined') return;
  if (token) window.localStorage.setItem('meeting_token', token);
  else window.localStorage.removeItem('meeting_token');
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
  const token = getToken();
  const headers = new Headers(init.headers);
  headers.set('Accept', 'application/json');
  if (!(init.body instanceof FormData)) headers.set('Content-Type', 'application/json');
  if (token) headers.set('Authorization', `Bearer ${token}`);
  const response = await fetch(`${API_URL}${path}`, {...init, headers, cache: 'no-store'});
  if (!response.ok) {
    let message = `Request failed with status ${response.status}`;
    try { const body = await response.json(); message = body.message ?? body.detail ?? message; } catch {}
    throw new ApiError(response.status, message);
  }
  return response.json() as Promise<T>;
}

export function formatTime(ms: number): string {
  const total = Math.floor(ms / 1000);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  return hours > 0 ? `${hours}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}` : `${minutes}:${String(seconds).padStart(2,'0')}`;
}
