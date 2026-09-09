'use client';

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { useAuth } from './AuthProvider';
import { api } from '@/lib/api';
import {
  applyBrandColors,
  DEFAULT_BRAND_COLORS,
  DEFAULT_LOGO_URL,
  type Branding,
  type BrandColors,
} from '@/lib/brandColors';

type BrandingContextValue = {
  branding: Branding | null;
  loading: boolean;
  logoUrl: string;
  iconUrl: string | null;
  faviconUrl: string | null;
  refresh: () => Promise<void>;
  setBranding: (next: Branding) => void;
};

const BrandingContext = createContext<BrandingContextValue | null>(null);

function upsertFavicon(href: string | null): void {
  if (typeof document === 'undefined') return;
  const existing = document.querySelector<HTMLLinkElement>('link[data-brand-favicon="true"]');
  if (!href) {
    existing?.remove();
    return;
  }
  const link = existing ?? document.createElement('link');
  link.rel = 'icon';
  link.href = href;
  link.setAttribute('data-brand-favicon', 'true');
  if (!existing) document.head.appendChild(link);
}

export function BrandingProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const [branding, setBrandingState] = useState<Branding | null>(null);
  const [loading, setLoading] = useState(false);

  const refresh = useCallback(async () => {
    if (!user) {
      setBrandingState(null);
      applyBrandColors(null);
      upsertFavicon(null);
      return;
    }
    setLoading(true);
    try {
      const data = await api<Branding>('/settings/branding');
      setBrandingState(data);
      applyBrandColors(data.colors);
      upsertFavicon(data.favicon_url ?? data.icon_url);
    } catch {
      setBrandingState(null);
      applyBrandColors(DEFAULT_BRAND_COLORS);
      upsertFavicon(null);
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (!user) {
      applyBrandColors(null);
      upsertFavicon(null);
    }
  }, [user]);

  const setBranding = useCallback((next: Branding) => {
    setBrandingState(next);
    applyBrandColors(next.colors);
    upsertFavicon(next.favicon_url ?? next.icon_url);
  }, []);

  const value = useMemo<BrandingContextValue>(
    () => ({
      branding,
      loading,
      logoUrl: branding?.logo_url ?? DEFAULT_LOGO_URL,
      iconUrl: branding?.icon_url ?? null,
      faviconUrl: branding?.favicon_url ?? branding?.icon_url ?? null,
      refresh,
      setBranding,
    }),
    [branding, loading, refresh, setBranding],
  );

  return <BrandingContext.Provider value={value}>{children}</BrandingContext.Provider>;
}

export function useBranding() {
  const value = useContext(BrandingContext);
  if (!value) throw new Error('useBranding must be used inside BrandingProvider');
  return value;
}

export function useBrandColors(): BrandColors {
  const { branding } = useBranding();
  return branding?.colors ?? DEFAULT_BRAND_COLORS;
}
