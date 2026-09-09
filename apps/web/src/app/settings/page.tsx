'use client';

import { ChangeEvent, FormEvent, useEffect, useId, useRef, useState } from 'react';
import { useAuth } from '@/components/AuthProvider';
import { useBranding } from '@/components/BrandingProvider';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import {
  darkenHex,
  DEFAULT_BRAND_COLORS,
  DEFAULT_FAVICON_URL,
  DEFAULT_ICON_URL,
  DEFAULT_LOGO_URL,
  deriveColorsFromLogo,
  softenHex,
  type BrandColors,
  type Branding,
} from '@/lib/brandColors';

type AssetType = 'logo' | 'icon' | 'favicon';

const ACCEPT =
  'image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,.ico,.png,.jpg,.jpeg,.webp,.svg';

const PREFERENCES_KEY = 'meeting_preferences';

function timezoneOptions(): string[] {
  if (typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl) {
    return Intl.supportedValuesOf('timeZone');
  }
  return ['UTC', 'Australia/Sydney', 'Australia/Melbourne', 'Europe/London', 'America/New_York'];
}

function readStoredTimezone(): string {
  if (typeof window === 'undefined') return Intl.DateTimeFormat().resolvedOptions().timeZone;
  try {
    const stored = window.localStorage.getItem(PREFERENCES_KEY);
    if (stored) {
      const parsed = JSON.parse(stored) as { timezone?: string };
      if (parsed.timezone) return parsed.timezone;
    }
  } catch {
    // Use the browser timezone when stored preferences are unavailable.
  }
  return Intl.DateTimeFormat().resolvedOptions().timeZone;
}

function ColorField({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (hex: string) => void;
}) {
  return (
    <label className="color-field">
      {label}
      <div className="color-field-row">
        <input
          type="color"
          value={/^#[0-9A-Fa-f]{6}$/.test(value) ? value : '#D040C0'}
          onChange={(e) => onChange(e.target.value.toUpperCase())}
        />
        <input
          type="text"
          value={value}
          maxLength={7}
          onChange={(e) => onChange(e.target.value.toUpperCase())}
          spellCheck={false}
        />
      </div>
    </label>
  );
}

function AssetUploadCard({
  title,
  description,
  preview,
  emptyLabel,
  hasAsset,
  uploading,
  accept,
  onFileChange,
  onReset,
  previewClassName,
}: {
  title: string;
  description: string;
  preview: string | null;
  emptyLabel: string;
  hasAsset: boolean;
  uploading: boolean;
  accept: string;
  onFileChange: (event: ChangeEvent<HTMLInputElement>) => void;
  onReset: () => void;
  previewClassName?: string;
}) {
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);

  return (
    <div className="branding-asset-card">
      <div className="branding-asset-copy">
        <h3>{title}</h3>
        <p>{description}</p>
      </div>
      <div className={`branding-preview ${previewClassName ?? ''}`.trim()}>
        {preview ? (
          <img src={preview} alt={`${title} preview`} />
        ) : (
          <span className="muted">{emptyLabel}</span>
        )}
      </div>
      <div className="branding-asset-actions">
        <input
          ref={inputRef}
          id={inputId}
          className="branding-file-input"
          type="file"
          accept={accept}
          disabled={uploading}
          onChange={onFileChange}
        />
        <button
          type="button"
          className="secondary-button"
          disabled={uploading}
          onClick={() => inputRef.current?.click()}
        >
          {uploading ? 'Uploading…' : hasAsset ? 'Replace' : 'Upload'}
        </button>
        {hasAsset ? (
          <button
            type="button"
            className="secondary-button"
            disabled={uploading}
            onClick={onReset}
          >
            Reset
          </button>
        ) : null}
      </div>
    </div>
  );
}

export default function SettingsPage() {
  const { canManageBranding } = useAuth();
  const { branding, setBranding, refresh } = useBranding();
  const toast = useToast();
  const [colors, setColors] = useState<BrandColors>(DEFAULT_BRAND_COLORS);
  const [timezone, setTimezone] = useState(readStoredTimezone);
  const [prefBusy, setPrefBusy] = useState(false);
  const [busy, setBusy] = useState(false);
  const [uploading, setUploading] = useState<AssetType | null>(null);
  const [error, setError] = useState('');

  const savePreferences = (event: FormEvent) => {
    event.preventDefault();
    setPrefBusy(true);
    setError('');
    try {
      window.localStorage.setItem(PREFERENCES_KEY, JSON.stringify({ timezone }));
      toast.success('Preferences saved on this device.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to save preferences');
    } finally {
      setPrefBusy(false);
    }
  };

  useEffect(() => {
    if (branding?.colors) setColors(branding.colors);
  }, [branding]);

  const updateColor = (key: keyof BrandColors, value: string) => {
    setColors((prev) => {
      const next = { ...prev, [key]: value, derivedFromLogo: false };
      if (key === 'primary' && /^#[0-9A-Fa-f]{6}$/.test(value)) {
        next.primaryHover = darkenHex(value, 0.18);
        next.primarySoft = softenHex(value, 0.88);
        if (!prev.derivedFromLogo || prev.action === prev.primary) {
          next.action = value;
          next.actionHover = darkenHex(value, 0.18);
        }
      }
      if (key === 'action' && /^#[0-9A-Fa-f]{6}$/.test(value)) {
        next.actionHover = darkenHex(value, 0.18);
      }
      return next;
    });
  };

  const uploadAsset = async (type: AssetType, file: File) => {
    setUploading(type);
    setError('');
    try {
      const body = new FormData();
      body.append('file', file);
      const next = await api<Branding>(`/settings/branding/${type}`, { method: 'POST', body });
      setBranding(next);

      if (type === 'logo') {
        const derived = await deriveColorsFromLogo(file);
        if (derived) {
          setColors(derived);
          toast.success('Logo uploaded. Palette derived from the logo — save to apply colors.');
          return;
        }
      }
      toast.success(`${type[0].toUpperCase()}${type.slice(1)} uploaded.`);
    } catch (err) {
      setError(err instanceof Error ? err.message : `Unable to upload ${type}`);
    } finally {
      setUploading(null);
    }
  };

  const onFileChange = (type: AssetType) => (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (file) void uploadAsset(type, file);
  };

  const removeAsset = async (type: AssetType) => {
    setUploading(type);
    setError('');
    try {
      const next = await api<Branding>(`/settings/branding/${type}`, { method: 'DELETE' });
      setBranding(next);
      toast.success(`${type[0].toUpperCase()}${type.slice(1)} reset to default.`);
    } catch (err) {
      setError(err instanceof Error ? err.message : `Unable to remove ${type}`);
    } finally {
      setUploading(null);
    }
  };

  const saveColors = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const next = await api<Branding>('/settings/branding', {
        method: 'PUT',
        body: JSON.stringify({
          primary: colors.primary,
          primaryHover: colors.primaryHover,
          primarySoft: colors.primarySoft,
          action: colors.action,
          actionHover: colors.actionHover,
          derivedFromLogo: colors.derivedFromLogo,
        }),
      });
      setBranding(next);
      toast.success('Branding colors saved for your organisation.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to save branding');
    } finally {
      setBusy(false);
    }
  };

  const rederiveFromLogo = async () => {
    const src = branding?.logo_url;
    if (!src) {
      setError('Upload a logo first to derive colors.');
      return;
    }
    setError('');
    const derived = await deriveColorsFromLogo(src);
    if (!derived) {
      setError('Could not extract colors from the logo. Try a different image.');
      return;
    }
    setColors(derived);
    toast.success('Palette refreshed from the logo. Save to apply.');
  };

  const resetDefaults = () => {
    setColors(DEFAULT_BRAND_COLORS);
    toast.success('Form reset to UmbrellaNET defaults. Save to apply, or reset assets separately.');
  };

  const logoPreview = branding?.logo_url ?? DEFAULT_LOGO_URL;
  const iconPreview = branding?.icon_url ?? DEFAULT_ICON_URL;
  const faviconPreview = branding?.favicon_url ?? branding?.icon_url ?? DEFAULT_FAVICON_URL;

  return (
    <div className="settings-page">
      <header className="page-header">
        <div>
          <span className="eyebrow">WORKSPACE</span>
          <h1>Settings</h1>
          <p>Timezone and preferences for your account{canManageBranding ? ', plus organisation branding.' : '.'}</p>
        </div>
      </header>

      {error ? <div className="error-box">{error}</div> : null}

      <section className="panel settings-panel">
        <div className="panel-heading">
          <div>
            <h2>Preferences</h2>
            <p>Used when creating meetings and displaying dates on this device.</p>
          </div>
        </div>
        <form className="form-grid" onSubmit={savePreferences}>
          <label>
            Timezone
            <select value={timezone} onChange={(event) => setTimezone(event.target.value)}>
              {timezoneOptions().map((zone) => (
                <option key={zone} value={zone}>
                  {zone}
                </option>
              ))}
            </select>
          </label>
          <div className="form-actions full">
            <button className="primary-button" disabled={prefBusy}>
              {prefBusy ? 'Saving...' : 'Save preferences'}
            </button>
          </div>
        </form>
      </section>

      {canManageBranding ? (
        <>
      <section className="panel settings-panel">
        <div className="panel-heading">
          <div>
            <h2>Organisation Branding</h2>
            <p>Upload a logo, app icon, and favicon. Logo changes can auto-suggest a palette.</p>
          </div>
        </div>
        <div className="branding-assets">
          <AssetUploadCard
            title="Logo"
            description="Horizontal mark used in the sidebar."
            preview={logoPreview}
            emptyLabel="No logo uploaded"
            hasAsset={Boolean(branding?.has_logo)}
            uploading={uploading === 'logo'}
            accept={ACCEPT}
            onFileChange={onFileChange('logo')}
            onReset={() => void removeAsset('logo')}
          />
          <AssetUploadCard
            title="Icon"
            description="Square icon for compact surfaces."
            preview={iconPreview}
            emptyLabel="No icon uploaded"
            hasAsset={Boolean(branding?.has_icon)}
            uploading={uploading === 'icon'}
            accept={ACCEPT}
            onFileChange={onFileChange('icon')}
            onReset={() => void removeAsset('icon')}
            previewClassName="branding-preview-icon"
          />
          <AssetUploadCard
            title="Favicon"
            description="Browser tab icon. Falls back to the app icon."
            preview={faviconPreview}
            emptyLabel="No favicon uploaded"
            hasAsset={Boolean(branding?.has_favicon)}
            uploading={uploading === 'favicon'}
            accept={ACCEPT}
            onFileChange={onFileChange('favicon')}
            onReset={() => void removeAsset('favicon')}
            previewClassName="branding-preview-icon"
          />
        </div>
      </section>

      <section className="panel settings-panel">
        <div className="panel-heading">
          <div>
            <h2>Color Palette</h2>
            <p>
              {colors.derivedFromLogo
                ? 'Colors were derived from your logo. Override any value below.'
                : 'Set primary accents and action button colors for the workspace.'}
            </p>
          </div>
        </div>
        <form className="branding-colors-form" onSubmit={(e) => void saveColors(e)}>
          <div className="color-grid">
            <ColorField
              label="Primary"
              value={colors.primary}
              onChange={(v) => updateColor('primary', v)}
            />
            <ColorField
              label="Primary hover"
              value={colors.primaryHover}
              onChange={(v) => updateColor('primaryHover', v)}
            />
            <ColorField
              label="Primary soft"
              value={colors.primarySoft}
              onChange={(v) => updateColor('primarySoft', v)}
            />
            <ColorField
              label="Action button"
              value={colors.action}
              onChange={(v) => updateColor('action', v)}
            />
            <ColorField
              label="Action hover"
              value={colors.actionHover}
              onChange={(v) => updateColor('actionHover', v)}
            />
          </div>

          <div className="branding-colors-footer">
            <div className="branding-preview-actions">
              <span className="muted">Preview</span>
              <button
                type="button"
                className="primary-button"
                style={{ background: colors.action, boxShadow: 'none' }}
              >
                Primary action
              </button>
              <button
                type="button"
                className="secondary-button"
                style={{ background: colors.primarySoft }}
              >
                Secondary
              </button>
            </div>
            <div className="form-actions branding-form-actions">
              <button
                type="button"
                className="secondary-button"
                onClick={() => void rederiveFromLogo()}
                disabled={!branding?.has_logo}
              >
                Re-derive from logo
              </button>
              <button type="button" className="secondary-button" onClick={resetDefaults}>
                Reset to defaults
              </button>
              <button type="button" className="secondary-button" onClick={() => void refresh()}>
                Reload saved
              </button>
              <button className="primary-button" disabled={busy}>
                {busy ? 'Saving...' : 'Save colors'}
              </button>
            </div>
          </div>
        </form>
      </section>
        </>
      ) : null}
    </div>
  );
}
