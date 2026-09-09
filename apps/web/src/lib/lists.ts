import type { Invitation, Meeting, MeetingParticipant, TenantUser } from './types';

export function unwrapList<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[];
  if (payload && typeof payload === 'object') {
    const obj = payload as Record<string, unknown>;
    for (const key of ['data', 'items', 'invitations', 'users', 'meetings', 'participants', 'activity', 'logs']) {
      if (Array.isArray(obj[key])) return obj[key] as T[];
    }
  }
  return [];
}

export function unwrapPaginated<T>(payload: unknown): { data: T[]; total: number } {
  const data = unwrapList<T>(payload);
  if (payload && typeof payload === 'object' && typeof (payload as { total?: unknown }).total === 'number') {
    return { data, total: (payload as { total: number }).total };
  }
  return { data, total: data.length };
}

export function invitationMeetingId(invitation: Invitation): string {
  return invitation.meeting_id ?? invitation.meeting?.id ?? invitation.id ?? '';
}

export function invitationTitle(invitation: Invitation): string {
  return invitation.meeting?.title ?? invitation.title ?? 'Meeting invitation';
}

export function invitationWhen(invitation: Invitation): string | undefined {
  return invitation.meeting?.scheduled_start_at ?? invitation.scheduled_start_at ?? invitation.created_at;
}

export function invitationRole(invitation: Invitation) {
  return invitation.my_participation?.participation_role ?? invitation.participation_role ?? 'attendee';
}

export function participantLabel(participant: MeetingParticipant): string {
  return participant.user?.name ?? participant.name ?? participant.user?.email ?? participant.email ?? participant.user_id;
}

export function participantEmail(participant: MeetingParticipant): string | undefined {
  return participant.user?.email ?? participant.email;
}

export function meetingHasNotes(meeting: Meeting): boolean {
  if ((meeting.artifacts?.length ?? 0) > 0) return true;
  if (meeting.status === 'ready') return true;
  return Boolean(meeting.current_transcript);
}

export function isProcessingMeeting(meeting: Meeting): boolean {
  return meeting.processing_status === 'transcribing' || meeting.processing_status === 'queued' || meeting.processing_status === 'processing';
}

export function startOfWeek(date = new Date()): Date {
  const next = new Date(date);
  const day = next.getDay();
  const diff = next.getDate() - day + (day === 0 ? -6 : 1);
  next.setDate(diff);
  next.setHours(0, 0, 0, 0);
  return next;
}

export function endOfWeek(date = new Date()): Date {
  const end = startOfWeek(date);
  end.setDate(end.getDate() + 7);
  return end;
}

export function isScheduledThisWeek(iso?: string | null): boolean {
  if (!iso) return false;
  const time = new Date(iso).getTime();
  if (Number.isNaN(time)) return false;
  return time >= startOfWeek().getTime() && time < endOfWeek().getTime();
}

export function formatWhen(iso?: string | null): string {
  if (!iso) return 'Date to be confirmed';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return 'Date to be confirmed';
  return date.toLocaleString(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export function excludeExistingParticipants(users: TenantUser[], participants: MeetingParticipant[], currentUserId?: string): TenantUser[] {
  const taken = new Set(participants.map((participant) => participant.user_id ?? participant.user?.id).filter(Boolean));
  if (currentUserId) taken.add(currentUserId);
  return users.filter((user) => !taken.has(user.id));
}
