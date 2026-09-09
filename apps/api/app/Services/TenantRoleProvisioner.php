<?php

namespace App\Services;

use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantRoleProvisioner
{
    public const APP_PERMISSIONS = [
        'meetings.create',
        'meetings.view-own',
        'calendar.view',
        'profile.update',
        'settings.update',
        'assistant.view',
    ];

    public const ADMIN_PERMISSIONS = [
        'users.manage',
        'roles.view',
        'activity.view',
        'meetings.configure',
        'branding.manage',
        'meetings.view-any',
    ];

    public static function allPermissions(): array
    {
        return array_merge(self::APP_PERMISSIONS, self::ADMIN_PERMISSIONS);
    }

    public function provision(Tenant $tenant): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        setPermissionsTeamId($tenant->id);

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $user = Role::findOrCreate('user', 'web');

        $superAdmin->syncPermissions(self::allPermissions());
        $user->syncPermissions(self::APP_PERMISSIONS);
    }
}
