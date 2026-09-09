'use client';

import { ChangeEvent, FormEvent, useEffect, useId, useRef, useState } from 'react';
import { useAuth } from '@/components/AuthProvider';
import { useToast } from '@/components/ToastProvider';
import { UserAvatar } from '@/components/UserAvatar';
import { api } from '@/lib/api';
import type { User } from '@/lib/types';

const PHOTO_ACCEPT = 'image/png,image/jpeg,image/webp';
const MAX_PHOTO_BYTES = 2 * 1024 * 1024;

function timezoneOptions(): string[] {
  if (typeof Intl !== 'undefined' && 'supportedValuesOf' in Intl) {
    return Intl.supportedValuesOf('timeZone');
  }
  return ['UTC', 'Australia/Sydney', 'Australia/Melbourne', 'Europe/London', 'America/New_York'];
}

function formatRole(roles: string[]): string {
  const role = roles[0] ?? 'user';
  return role.replace(/-/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function nameParts(user: User) {
  if (user.first_name || user.surname) {
    return { firstName: user.first_name ?? '', surname: user.surname ?? '' };
  }
  const parts = user.name.trim().split(/\s+/).filter(Boolean);
  if (parts.length <= 1) return { firstName: parts[0] ?? '', surname: '' };
  const surname = parts.pop() ?? '';
  return { firstName: parts.join(' '), surname };
}

export default function ProfilePage() {
  const { user, refresh } = useAuth();
  const toast = useToast();
  const photoInputId = useId();
  const photoInputRef = useRef<HTMLInputElement>(null);
  const [firstName, setFirstName] = useState('');
  const [surname, setSurname] = useState('');
  const [email, setEmail] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [jobTitle, setJobTitle] = useState('');
  const [department, setDepartment] = useState('');
  const [timezone, setTimezone] = useState('');
  const [bio, setBio] = useState('');
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [photoBusy, setPhotoBusy] = useState(false);

  useEffect(() => {
    if (!user) return;
    const parts = nameParts(user);
    setFirstName(parts.firstName);
    setSurname(parts.surname);
    setEmail(user.email);
    setContactNumber(user.contact_number ?? '');
    setJobTitle(user.job_title ?? '');
    setDepartment(user.department ?? '');
    setTimezone(user.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone);
    setBio(user.bio ?? '');
  }, [user]);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      const body: Record<string, string> = {
        first_name: firstName.trim(),
        surname: surname.trim(),
        email,
        contact_number: contactNumber.trim(),
        job_title: jobTitle.trim(),
        department: department.trim(),
        timezone,
        bio: bio.trim(),
      };
      if (password) {
        body.password = password;
        body.current_password = currentPassword;
      }
      await api<User>('/auth/profile', { method: 'PATCH', body: JSON.stringify(body) });
      setPassword('');
      setCurrentPassword('');
      await refresh();
      toast.success('Profile updated.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to update profile');
    } finally {
      setBusy(false);
    }
  };

  const uploadPhoto = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;
    if (file.size > MAX_PHOTO_BYTES) {
      setError('Photo must be 2MB or smaller.');
      return;
    }
    setPhotoBusy(true);
    setError('');
    try {
      const body = new FormData();
      body.append('file', file);
      await api<User>('/auth/profile/photo', { method: 'POST', body });
      await refresh();
      toast.success('Photo updated.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to upload photo');
    } finally {
      setPhotoBusy(false);
    }
  };

  const removePhoto = async () => {
    setPhotoBusy(true);
    setError('');
    try {
      await api<User>('/auth/profile/photo', { method: 'DELETE' });
      await refresh();
      toast.success('Photo removed.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to remove photo');
    } finally {
      setPhotoBusy(false);
    }
  };

  if (!user) return null;

  const displayName = `${firstName} ${surname}`.trim() || user.name;
  const zones = timezoneOptions();
  const zoneList = timezone && !zones.includes(timezone) ? [timezone, ...zones] : zones;

  return (
    <div className="narrow-page profile-page">
      <header className="page-header">
        <div>
          <h1>Profile</h1>
        </div>
      </header>

      {error ? <div className="error-box">{error}</div> : null}

      <section className="panel profile-identity">
        <div className="profile-photo-control">
          <UserAvatar name={displayName} src={user.avatar_url} size="lg" />
          <div className="profile-photo-overlay">
            <input
              ref={photoInputRef}
              id={photoInputId}
              className="profile-photo-input"
              type="file"
              accept={PHOTO_ACCEPT}
              disabled={photoBusy}
              onChange={(event) => void uploadPhoto(event)}
            />
            <button type="button" disabled={photoBusy} onClick={() => photoInputRef.current?.click()}>
              {photoBusy ? 'Uploading…' : user.has_avatar ? 'Change photo' : 'Upload photo'}
            </button>
            {user.has_avatar ? (
              <button type="button" disabled={photoBusy} onClick={() => void removePhoto()}>
                Remove
              </button>
            ) : null}
          </div>
        </div>
        <div className="profile-identity-copy">
          <h2>{displayName}</h2>
          {jobTitle ? <p className="profile-job">{jobTitle}</p> : null}
          <p>{user.tenant.name}</p>
          <span className="profile-role">{formatRole(user.roles)}</span>
        </div>
      </section>

      <form className="profile-form" onSubmit={(event) => void submit(event)}>
        <section className="panel">
          <div className="panel-heading">
            <div>
              <h2>Personal details</h2>
            </div>
          </div>
          <div className="form-grid">
            <label>
              First Name
              <input value={firstName} onChange={(event) => setFirstName(event.target.value)} required />
            </label>
            <label>
              Surname
              <input value={surname} onChange={(event) => setSurname(event.target.value)} required />
            </label>
            <label>
              Email
              <input type="email" value={email} onChange={(event) => setEmail(event.target.value)} required />
            </label>
            <label>
              Contact Number
              <input
                type="tel"
                value={contactNumber}
                onChange={(event) => setContactNumber(event.target.value)}
              />
            </label>
          </div>
        </section>

        <section className="panel">
          <div className="panel-heading">
            <div>
              <h2>Work and meetings</h2>
            </div>
          </div>
          <div className="form-grid">
            <label>
              Job Title
              <input value={jobTitle} onChange={(event) => setJobTitle(event.target.value)} />
            </label>
            <label>
              Department
              <input value={department} onChange={(event) => setDepartment(event.target.value)} />
            </label>
            <label>
              Timezone
              <select value={timezone} onChange={(event) => setTimezone(event.target.value)}>
                {zoneList.map((zone) => (
                  <option key={zone} value={zone}>
                    {zone}
                  </option>
                ))}
              </select>
            </label>
            <label className="full">
              Bio
              <textarea
                rows={4}
                maxLength={500}
                value={bio}
                onChange={(event) => setBio(event.target.value)}
                placeholder="A short line attendees will see"
              />
            </label>
          </div>
        </section>

        <section className="panel">
          <div className="panel-heading">
            <div>
              <h2>Security</h2>
            </div>
          </div>
          <div className="form-grid">
            <label>
              Current password
              <input
                type="password"
                value={currentPassword}
                onChange={(event) => setCurrentPassword(event.target.value)}
                autoComplete="current-password"
                required={Boolean(password)}
              />
            </label>
            <label>
              New password
              <input
                type="password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                autoComplete="new-password"
                placeholder="Leave blank to keep the current password"
              />
            </label>
          </div>
        </section>

        <div className="form-actions">
          <button className="primary-button" disabled={busy}>
            {busy ? 'Saving...' : 'Save profile'}
          </button>
        </div>
      </form>
    </div>
  );
}
