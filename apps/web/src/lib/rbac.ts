import type { Meeting, User } from './types';

export function isSuperAdmin(user: User | null | undefined): boolean {
  if (!user) return false;
  return Boolean(user.is_super_admin || user.roles?.includes('super-admin'));
}

export function hasPermission(user: User | null | undefined, permission: string): boolean {
  if (!user) return false;
  if (user.permissions?.includes(permission)) return true;
  return isSuperAdmin(user);
}

export function canManageBranding(user: User | null | undefined): boolean {
  return hasPermission(user, 'branding.manage');
}

export function isMeetingConvenor(meeting: Meeting, user?: User | null): boolean {
  if (isSuperAdmin(user)) return true;
  return meeting.my_participation?.participation_role === 'convenor';
}
