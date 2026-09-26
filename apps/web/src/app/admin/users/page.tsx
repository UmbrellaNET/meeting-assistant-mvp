'use client';

import { FormEvent, useCallback, useEffect, useState } from 'react';
import { useAuth } from '@/components/AuthProvider';
import { ConfirmDialog } from '@/components/ConfirmDialog';
import { DataTable } from '@/components/DataTable';
import { IconButton, TableActions } from '@/components/IconButton';
import { Modal } from '@/components/Modal';
import { useToast } from '@/components/ToastProvider';
import { api } from '@/lib/api';
import { unwrapList } from '@/lib/lists';
import type { TenantUser } from '@/lib/types';

function formatRole(role: string): string {
  return role.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

const emptyForm = { name: '', email: '', password: '', role: 'user' };

export default function UserManagementPage() {
  const toast = useToast();
  const { user: currentUser, impersonate, isSuperAdmin, isImpersonating } = useAuth();
  const [users, setUsers] = useState<TenantUser[] | null>(null);
  const [error, setError] = useState('');
  const [form, setForm] = useState(emptyForm);
  const [mode, setMode] = useState<'create' | 'edit' | 'view' | null>(null);
  const [active, setActive] = useState<TenantUser | null>(null);
  const [deleting, setDeleting] = useState<TenantUser | null>(null);
  const [impersonating, setImpersonating] = useState<TenantUser | null>(null);
  const [busy, setBusy] = useState(false);
  const [formError, setFormError] = useState('');

  const load = useCallback(() => {
    return api<unknown>('/admin/users')
      .then((payload) => setUsers(unwrapList<TenantUser>(payload)))
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load users'));
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const openCreate = () => {
    setActive(null);
    setForm(emptyForm);
    setFormError('');
    setMode('create');
  };

  const openEdit = (person: TenantUser) => {
    setActive(person);
    setForm({
      name: person.name,
      email: person.email,
      password: '',
      role: person.roles?.[0] ?? 'user',
    });
    setFormError('');
    setMode('edit');
  };

  const openView = (person: TenantUser) => {
    setActive(person);
    setFormError('');
    setMode('view');
  };

  const close = () => {
    if (!busy) setMode(null);
  };

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (mode === 'view') return;
    setBusy(true);
    setFormError('');
    try {
      if (mode === 'edit' && active) {
        await api(`/admin/users/${active.id}`, {
          method: 'PATCH',
          body: JSON.stringify({
            name: form.name,
            email: form.email,
            role: form.role,
            ...(form.password ? { password: form.password } : {}),
          }),
        });
        toast.success('User updated.');
      } else {
        await api('/admin/users', {
          method: 'POST',
          body: JSON.stringify(form),
        });
        toast.success('User created.');
      }
      setMode(null);
      setForm(emptyForm);
      await load();
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'Unable to save user');
    } finally {
      setBusy(false);
    }
  };

  const remove = async () => {
    if (!deleting) return;
    setBusy(true);
    try {
      await api(`/admin/users/${deleting.id}`, { method: 'DELETE' });
      toast.success('User deleted.');
      setDeleting(null);
      await load();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to delete user');
    } finally {
      setBusy(false);
    }
  };

  const startImpersonation = async () => {
    if (!impersonating) return;
    setBusy(true);
    try {
      await impersonate(impersonating.id);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : 'Unable to impersonate user');
      setBusy(false);
    }
  };

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>User Management</h1>
        </div>
      </header>

      <DataTable
        columns={[
          { key: 'name', label: 'Name' },
          { key: 'email', label: 'Email' },
          { key: 'role', label: 'Role' },
          { key: 'actions', label: '' },
        ]}
        cols="minmax(0,1.6fr) minmax(0,1.6fr) minmax(0,1fr) 168px"
        search
        searchPlaceholder="Search people"
        loading={users === null && !error}
        error={error}
        emptyTitle="No users yet"
        emptyDescription="Add a user so they can be invited to meetings."
        action={<IconButton name="plus" label="Add User" variant="primary" onClick={openCreate} />}
        rows={(users ?? []).map((person) => ({
          id: person.id,
          searchText: `${person.name} ${person.email} ${person.roles?.join(' ') ?? ''}`,
          cells: [
            <strong key="name">{person.name}</strong>,
            person.email,
            formatRole(person.roles?.[0] ?? 'user'),
            <TableActions key="actions">
              <IconButton name="view" label="View User" onClick={() => openView(person)} />
              <IconButton name="edit" label="Edit User" onClick={() => openEdit(person)} />
              {isSuperAdmin && !isImpersonating && person.id !== currentUser?.id ? (
                <IconButton name="impersonate" label="Impersonate User" onClick={() => setImpersonating(person)} />
              ) : null}
              {person.id !== currentUser?.id ? (
                <IconButton name="trash" label="Delete User" variant="danger" onClick={() => setDeleting(person)} />
              ) : null}
            </TableActions>,
          ],
        }))}
      />

      <Modal
        open={mode !== null}
        onClose={close}
        title={mode === 'edit' ? 'Edit User' : mode === 'view' ? 'View User' : 'Add User'}
        description={
          mode === 'view'
            ? undefined
            : mode === 'edit'
              ? 'Update this person’s details. Leave the password blank to keep the current one.'
              : 'New people start as a general user unless you assign Super Admin.'
        }
        footer={
          mode === 'view' ? (
            <button type="button" className="secondary-button" onClick={close}>
              Close
            </button>
          ) : (
            <>
              <button type="button" className="secondary-button" onClick={close} disabled={busy}>
                Cancel
              </button>
              <button type="submit" form="user-form" className="primary-button" disabled={busy}>
                {busy ? 'Saving...' : mode === 'edit' ? 'Save Changes' : 'Create User'}
              </button>
            </>
          )
        }
      >
        {mode === 'view' && active ? (
          <div className="form-grid">
            <label>
              Name
              <input value={active.name} readOnly />
            </label>
            <label>
              Email
              <input value={active.email} readOnly />
            </label>
            <label>
              Role
              <input value={formatRole(active.roles?.[0] ?? 'user')} readOnly />
            </label>
          </div>
        ) : (
          <form id="user-form" className="form-grid" onSubmit={(event) => void submit(event)}>
            {formError ? <div className="error-box full">{formError}</div> : null}
            <label>
              Name
              <input
                value={form.name}
                onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
                required
              />
            </label>
            <label>
              Email
              <input
                type="email"
                value={form.email}
                onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
                required
              />
            </label>
            <label>
              {mode === 'edit' ? 'New Password' : 'Password'}
              <input
                type="password"
                value={form.password}
                onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
                required={mode === 'create'}
                minLength={mode === 'edit' && !form.password ? undefined : 8}
                placeholder={mode === 'edit' ? 'Leave blank to keep current password' : undefined}
              />
            </label>
            <label>
              Role
              <select
                value={form.role}
                onChange={(event) => setForm((current) => ({ ...current, role: event.target.value }))}
              >
                <option value="user">User</option>
                <option value="super-admin">Super Admin</option>
              </select>
            </label>
          </form>
        )}
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Delete User"
        description={
          deleting
            ? `Delete ${deleting.name}? They will no longer be able to sign in or be invited to meetings.`
            : ''
        }
        busy={busy}
        onClose={() => {
          if (!busy) setDeleting(null);
        }}
        onConfirm={() => void remove()}
      />

      <ConfirmDialog
        open={Boolean(impersonating)}
        title="Impersonate User"
        description={
          impersonating
            ? `You will see the app as ${impersonating.name}. Stop from the banner to return to your account.`
            : ''
        }
        confirmLabel="Impersonate"
        busyLabel="Starting..."
        busy={busy}
        onClose={() => {
          if (!busy) setImpersonating(null);
        }}
        onConfirm={() => void startImpersonation()}
      />
    </div>
  );
}
