'use client';

import { useEffect, useState } from 'react';
import { DataTable } from '@/components/DataTable';
import { api } from '@/lib/api';
import { unwrapList } from '@/lib/lists';
import type { TenantRole } from '@/lib/types';

function formatRole(role: string): string {
  return role.replaceAll('-', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export default function RolesPage() {
  const [roles, setRoles] = useState<TenantRole[] | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api<unknown>('/admin/roles')
      .then((payload) => setRoles(unwrapList<TenantRole>(payload)))
      .catch((err) => setError(err instanceof Error ? err.message : 'Unable to load roles'));
  }, []);

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
        cols="minmax(0,1.1fr) 120px minmax(0,2.4fr)"
        columns={[
          { key: 'role', label: 'Role' },
          { key: 'count', label: 'Permissions' },
          { key: 'names', label: 'Assigned' },
        ]}
        rows={(roles ?? []).map((role) => ({
          id: role.name,
          searchText: `${role.name} ${role.permissions.join(' ')}`,
          cells: [
            <strong key="name">{formatRole(role.name)}</strong>,
            String(role.permissions.length),
            <span className="permission-chips" key="chips">
              {role.permissions.map((permission) => (
                <span className="permission-chip" key={permission}>
                  {permission}
                </span>
              ))}
            </span>,
          ],
        }))}
      />
    </div>
  );
}
