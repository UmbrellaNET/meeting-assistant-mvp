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
  DEFAULT_FAVICON_URL,
  DEFAULT_ICON_URL,
  DEFAULT_LOGO_URL,
  type Branding,
  type BrandColors,
} from '@/lib/brandColors';

type BrandingContextValue = {
  branding: Branding | null;
  loading: boolean;
  logoUrl: string;
  iconUrl: string;
  faviconUrl: string;
  refresh: () => Promise<void>;
  setBranding: (next: Branding) => void;
};

const BrandingContext = createContext<BrandingContextValue | null>(null);

function upsertFavicon(href: string | null): void {
  if (typeof document === 'undefined') return;
  const url = href || DEFAULT_FAVICON_URL;
  const existing = document.querySelector<HTMLLinkElement>('link[data-brand-favicon="true"]');
  const link = existing ?? document.createElement('link');
  link.rel = 'icon';
  link.type = 'image/png';
  link.href = url;
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
      upsertFavicon(DEFAULT_FAVICON_URL);
      return;
    }
    setLoading(true);
    try {
      const data = await api<Branding>('/settings/branding');
      setBrandingState(data);
      applyBrandColors(data.colors);
      upsertFavicon(data.favicon_url ?? data.icon_url ?? DEFAULT_FAVICON_URL);
    } catch {
      setBrandingState(null);
      applyBrandColors(DEFAULT_BRAND_COLORS);
      upsertFavicon(DEFAULT_FAVICON_URL);
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
      upsertFavicon(DEFAULT_FAVICON_URL);
    }
  }, [user]);

  const setBranding = useCallback((next: Branding) => {
    setBrandingState(next);
    applyBrandColors(next.colors);
    upsertFavicon(next.favicon_url ?? next.icon_url ?? DEFAULT_FAVICON_URL);
  }, []);

  const value = useMemo<BrandingContextValue>(
    () => ({
      branding,
      loading,
      logoUrl: branding?.logo_url ?? DEFAULT_LOGO_URL,
      iconUrl: branding?.icon_url ?? DEFAULT_ICON_URL,
      faviconUrl: branding?.favicon_url ?? branding?.icon_url ?? DEFAULT_FAVICON_URL,
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
