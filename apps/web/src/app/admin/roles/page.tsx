'use client';

import { useEffect, useState } from 'react';
import { DataTable } from '@/components/DataTable';
import { IconButton, TableActions } from '@/components/IconButton';
import { Modal } from '@/components/Modal';
import { api } from '@/lib/api';
import { unwrapList } from '@/lib/lists';
import type { TenantRole } from '@/lib/types';

function formatRole(role: string): string {
  return role.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function RolesPage() {
  const [roles, setRoles] = useState<TenantRole[] | null>(null);
  const [error, setError] = useState('');
  const [viewing, setViewing] = useState<TenantRole | null>(null);

  useEffect(() => {
    api<unknown>('/admin/roles')
      .then((payload) => setRoles(unwrapList<TenantRole>(payload)))
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load roles'));
  }, []);

  const permissionCount = viewing?.permissions.length ?? 0;

  return (
    <div>
      <header className="page-header">
        <div>
          <h1>Roles and Permissions</h1>
        </div>
      </header>

      <DataTable
        search
        searchPlaceholder="Search roles"
        loading={roles === null && !error}
        error={error}
        emptyTitle="No roles yet"
        emptyDescription="Roles will appear here once they are provisioned for this organisation."
        cols="minmax(0,1.1fr) 140px 72px"
        columns={[
          { key: 'role', label: 'Role' },
          { key: 'count', label: 'Permissions' },
          { key: 'actions', label: '' },
        ]}
        rows={(roles ?? []).map((role) => ({
          id: role.name,
          searchText: `${role.name} ${role.permissions.join(' ')}`,
          cells: [
            <strong key="name">{formatRole(role.name)}</strong>,
            String(role.permissions.length),
            <TableActions key="actions">
              <IconButton
                name="view"
                label={`View permissions for ${formatRole(role.name)}`}
                onClick={() => setViewing(role)}
              />
            </TableActions>,
          ],
        }))}
      />

      <Modal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing ? `${formatRole(viewing.name)} permissions` : 'Assigned permissions'}
        description={
          viewing
            ? `${permissionCount} permission${permissionCount === 1 ? '' : 's'} assigned to this role.`
            : undefined
        }
        footer={
          <button type="button" className="secondary-button" onClick={() => setViewing(null)}>
            Close
          </button>
        }
      >
        {viewing ? (
          viewing.permissions.length ? (
            <span className="permission-chips">
              {viewing.permissions.map((permission) => (
                <span className="permission-chip" key={permission}>
                  {permission}
                </span>
              ))}
            </span>
          ) : (
            <p>This role has no assigned permissions.</p>
          )
        ) : null}
      </Modal>
    </div>
  );
}
