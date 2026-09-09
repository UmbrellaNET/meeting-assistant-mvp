export type BrandColors = {
  primary: string;
  primaryHover: string;
  primarySoft: string;
  action: string;
  actionHover: string;
  derivedFromLogo: boolean;
};

export type Branding = {
  logo_url: string | null;
  icon_url: string | null;
  favicon_url: string | null;
  has_logo: boolean;
  has_icon: boolean;
  has_favicon: boolean;
  colors: BrandColors;
};

export const DEFAULT_BRAND_COLORS: BrandColors = {
  primary: '#D040C0',
  primaryHover: '#A03090',
  primarySoft: '#F6E8F4',
  action: '#D040C0',
  actionHover: '#A03090',
  derivedFromLogo: false,
};

export const DEFAULT_LOGO_URL = '/un-logo-horizontal-light.webp';

function clamp(n: number, min = 0, max = 255): number {
  return Math.min(max, Math.max(min, Math.round(n)));
}

function toHex(r: number, g: number, b: number): string {
  return `#${[r, g, b].map((v) => clamp(v).toString(16).padStart(2, '0')).join('')}`.toUpperCase();
}

function parseHex(hex: string): [number, number, number] | null {
  const match = /^#?([0-9A-Fa-f]{6})$/.exec(hex.trim());
  if (!match) return null;
  const n = parseInt(match[1], 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

export function darkenHex(hex: string, amount = 0.16): string {
  const rgb = parseHex(hex);
  if (!rgb) return hex;
  return toHex(rgb[0] * (1 - amount), rgb[1] * (1 - amount), rgb[2] * (1 - amount));
}

export function softenHex(hex: string, mix = 0.88): string {
  const rgb = parseHex(hex);
  if (!rgb) return hex;
  return toHex(
    rgb[0] + (255 - rgb[0]) * mix,
    rgb[1] + (255 - rgb[1]) * mix,
    rgb[2] + (255 - rgb[2]) * mix,
  );
}

function hexToRgba(hex: string, alpha: number): string {
  const rgb = parseHex(hex);
  if (!rgb) return `rgba(208,64,192,${alpha})`;
  return `rgba(${rgb[0]},${rgb[1]},${rgb[2]},${alpha})`;
}

export function applyBrandColors(colors: BrandColors | null): void {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (!colors) {
    [
      '--blue',
      '--primary',
      '--primary-hover',
      '--primary-soft',
      '--blue-soft',
      '--action',
      '--action-hover',
      '--action-shadow',
      '--action-shadow-hover',
      '--focus-ring',
      '--shadow',
    ].forEach((key) => root.style.removeProperty(key));
    return;
  }

  root.style.setProperty('--blue', colors.primary);
  root.style.setProperty('--primary', colors.primary);
  root.style.setProperty('--primary-hover', colors.primaryHover);
  root.style.setProperty('--primary-soft', colors.primarySoft);
  root.style.setProperty('--blue-soft', colors.primarySoft);
  root.style.setProperty('--action', colors.action);
  root.style.setProperty('--action-hover', colors.actionHover);
  root.style.setProperty('--action-shadow', `0 8px 20px ${hexToRgba(colors.action, 0.28)}`);
  root.style.setProperty('--action-shadow-hover', `0 10px 24px ${hexToRgba(colors.actionHover, 0.32)}`);
  root.style.setProperty('--focus-ring', `0 0 0 3px ${hexToRgba(colors.primary, 0.16)}`);
  root.style.setProperty('--shadow', `0 18px 45px ${hexToRgba(colors.primaryHover, 0.07)}`);
}

function saturation(r: number, g: number, b: number): number {
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  if (max === 0) return 0;
  return (max - min) / max;
}

function luminance(r: number, g: number, b: number): number {
  return (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
}

/** Sample a logo image URL or File and derive a brand palette. */
export async function deriveColorsFromLogo(source: string | File): Promise<BrandColors | null> {
  try {
    let blobUrl: string;
    let revoke = false;

    if (typeof source === 'string') {
      try {
        const response = await fetch(source);
        if (!response.ok) throw new Error('fetch failed');
        const blob = await response.blob();
        blobUrl = URL.createObjectURL(blob);
        revoke = true;
      } catch {
        blobUrl = source;
      }
    } else {
      blobUrl = URL.createObjectURL(source);
      revoke = true;
    }

    const img = await loadImage(blobUrl);
    if (revoke) URL.revokeObjectURL(blobUrl);

    const canvas = document.createElement('canvas');
    const size = 64;
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return null;
    ctx.drawImage(img, 0, 0, size, size);
    const { data } = ctx.getImageData(0, 0, size, size);

    const buckets = new Map<string, { count: number; r: number; g: number; b: number; score: number }>();

    for (let i = 0; i < data.length; i += 4) {
      const a = data[i + 3];
      if (a < 128) continue;
      const r = data[i];
      const g = data[i + 1];
      const b = data[i + 2];
      const lum = luminance(r, g, b);
      const sat = saturation(r, g, b);
      if (lum > 0.92 || lum < 0.08) continue;
      if (sat < 0.12 && lum > 0.75) continue;

      const key = `${Math.round(r / 16)}-${Math.round(g / 16)}-${Math.round(b / 16)}`;
      const existing = buckets.get(key);
      const score = sat * 2 + (1 - Math.abs(lum - 0.45));
      if (existing) {
        existing.count += 1;
        existing.r += r;
        existing.g += g;
        existing.b += b;
        existing.score += score;
      } else {
        buckets.set(key, { count: 1, r, g, b, score });
      }
    }

    if (buckets.size === 0) return null;

    const best = [...buckets.values()].sort((a, b) => b.score - a.score || b.count - a.count)[0];
    const primary = toHex(best.r / best.count, best.g / best.count, best.b / best.count);

    return {
      primary,
      primaryHover: darkenHex(primary, 0.18),
      primarySoft: softenHex(primary, 0.88),
      action: primary,
      actionHover: darkenHex(primary, 0.18),
      derivedFromLogo: true,
    };
  } catch {
    return null;
  }
}

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('Unable to load image for color extraction'));
    img.src = src;
  });
}
