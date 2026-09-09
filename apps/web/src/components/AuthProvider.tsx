'use client';
import { useRouter } from 'next/navigation';
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import {
  ApiError,
  api,
  clearAdminToken,
  getAdminToken,
  restoreAdminToken,
  setToken,
  stashAdminToken,
} from '@/lib/api';
import { canManageBranding, hasPermission, isSuperAdmin } from '@/lib/rbac';
import type { Impersonator, User } from '@/lib/types';

type AuthContextValue = {
  user: User | null;
  loading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
  impersonate: (userId: string) => Promise<void>;
  stopImpersonation: () => Promise<void>;
  isSuperAdmin: boolean;
  isImpersonating: boolean;
  impersonator: Impersonator | null;
  hasPermission: (permission: string) => boolean;
  canManageBranding: boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

function normalizeUser(raw: User): User {
  return {
    ...raw,
    roles: raw.roles ?? [],
    permissions: raw.permissions ?? [],
    is_super_admin: Boolean(raw.is_super_admin),
    impersonation: raw.impersonation ?? null,
  };
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = async () => {
    try {
      setUser(normalizeUser(await api<User>('/auth/me')));
    } catch (err) {
      if (err instanceof ApiError && err.status === 401 && getAdminToken()) {
        restoreAdminToken();
        try {
          setUser(normalizeUser(await api<User>('/auth/me')));
          return;
        } catch {
          setToken(null);
          clearAdminToken();
        }
      }
      setUser(null);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void refresh();
  }, []);

  const login = async (email: string, password: string) => {
    const result = await api<{ token: string; user: User }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    clearAdminToken();
    setToken(result.token);
    setUser(normalizeUser(result.user));
  };

  const logout = async () => {
    try {
      await api('/auth/logout', { method: 'POST' });
    } finally {
      setToken(null);
      clearAdminToken();
      setUser(null);
    }
  };

  const impersonate = useCallback(async (userId: string) => {
    const result = await api<{ token: string; user: User; impersonator: Impersonator }>(
      `/admin/users/${userId}/impersonate`,
      { method: 'POST' },
    );
    stashAdminToken();
    setToken(result.token);
    setUser(
      normalizeUser({
        ...result.user,
        impersonation:
          result.user.impersonation ?? {
            active: true,
            impersonator: result.impersonator,
            expires_at: null,
          },
      }),
    );
    router.push('/');
  }, [router]);

  const stopImpersonation = useCallback(async () => {
    try {
      await api('/auth/stop-impersonation', { method: 'POST' });
    } catch (err) {
      if (!(err instanceof ApiError && err.status === 401)) {
        throw err;
      }
    }
    restoreAdminToken();
    await refresh();
    router.push('/admin/users');
  }, [router]);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      login,
      logout,
      refresh,
      impersonate,
      stopImpersonation,
      isSuperAdmin: isSuperAdmin(user),
      isImpersonating: Boolean(user?.impersonation?.active),
      impersonator: user?.impersonation?.impersonator ?? null,
      hasPermission: (permission: string) => hasPermission(user, permission),
      canManageBranding: canManageBranding(user),
    }),
    [user, loading, impersonate, stopImpersonation],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const value = useContext(AuthContext);
  if (!value) throw new Error('useAuth must be used inside AuthProvider');
  return value;
}
