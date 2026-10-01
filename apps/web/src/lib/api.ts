import { MeetingSummary } from "./types";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:8000/api';

export class ApiError extends Error {
  constructor(public status: number, message: string) { super(message); }
}

const TOKEN_KEY = 'meeting_token';
const ADMIN_TOKEN_KEY = 'meeting_admin_token';

export function getToken(): string | null {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem(TOKEN_KEY);
}


export async function downloadFile(path: string, fallbackFilename: string): Promise<void> {
  const token = getToken();
  const headers = new Headers();
  headers.set('Accept', '*/*');
  if (token) headers.set('Authorization', `Bearer ${token}`);

  const response = await fetch(`${API_URL}${path}`, { headers, cache: 'no-store' });

  if (!response.ok) {
    let message = `Download failed with status ${response.status}`;
    try {
      const body = await response.json();
      message = body.message ?? body.detail ?? message;
    } catch {
      
    }
    throw new ApiError(response.status, message);
  }

 
  const disposition = response.headers.get('Content-Disposition') ?? '';
  const match = disposition.match(/filename="?([^"]+)"?/);
  const filename = match?.[1] ?? fallbackFilename;

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.URL.revokeObjectURL(url);
}

export async function getShareLink(meetingId: string): Promise<{ share_token: string; url: string }> {
  return api<{ share_token: string; url: string }>(`/meetings/${meetingId}/share-link`, {
    method: 'POST',
  });
}

export async function revokeShareLink(meetingId: string): Promise<void> {
  await api<void>(`/meetings/${meetingId}/share-link`, {
    method: 'DELETE',
  });
}


export function setToken(token: string | null): void {
  if (typeof window === 'undefined') return;
  if (token) window.localStorage.setItem(TOKEN_KEY, token);
  else window.localStorage.removeItem(TOKEN_KEY);
}

export function getAdminToken(): string | null {
  if (typeof window === 'undefined') return null;
  return window.localStorage.getItem(ADMIN_TOKEN_KEY);
}

export function stashAdminToken(): void {
  if (typeof window === 'undefined') return;
  const token = getToken();
  if (token) window.localStorage.setItem(ADMIN_TOKEN_KEY, token);
}

export function clearAdminToken(): void {
  if (typeof window === 'undefined') return;
  window.localStorage.removeItem(ADMIN_TOKEN_KEY);
}

export function restoreAdminToken(): boolean {
  const adminToken = getAdminToken();
  if (!adminToken) return false;
  setToken(adminToken);
  clearAdminToken();
  return true;
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
  if (response.status === 204) return undefined as T;
  const text = await response.text();
  if (!text) return undefined as T;
  return JSON.parse(text) as T;
}

export function formatTime(ms: number): string {
  const total = Math.floor(ms / 1000);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  return hours > 0 ? `${hours}:${String(minutes).padStart(2,'0')}:${String(seconds).padStart(2,'0')}` : `${minutes}:${String(seconds).padStart(2,'0')}`;
}

export async function getMeetingSummary(meetingId: string): Promise<MeetingSummary> {
  return api<MeetingSummary>(`/meetings/${meetingId}/summary`);
}

export async function regenerateMeetingSummary(meetingId: string): Promise<void> {
  await api<void>(`/meetings/${meetingId}/summary/regenerate`, { method: 'POST' });
}
