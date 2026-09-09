import type { ParticipationRole } from '@/lib/types';

export function ParticipationBadge({ role }: { role?: ParticipationRole | null }) {
  if (!role) return null;
  const label = role === 'convenor' ? 'Convenor' : 'Attendee';
  return <span className={`participation-badge participation-badge-${role}`}>{label}</span>;
}
