export type Tenant = { id: string; name: string; slug: string };

export type Impersonator = {
  id: string;
  name: string;
  email: string;
};

export type ImpersonationState = {
  active: boolean;
  impersonator: Impersonator;
  expires_at?: string | null;
};

export type User = {
  id: string;
  name: string;
  first_name?: string | null;
  surname?: string | null;
  email: string;
  contact_number?: string | null;
  job_title?: string | null;
  department?: string | null;
  timezone?: string | null;
  bio?: string | null;
  avatar_url?: string | null;
  has_avatar?: boolean;
  tenant: Tenant;
  roles: string[];
  permissions: string[];
  is_super_admin: boolean;
  impersonation?: ImpersonationState | null;
};

export type ActivityPerson = {
  id?: string | null;
  name?: string | null;
  email?: string | null;
  type?: string | null;
};

export type ActivityLog = {
  id: string;
  log_name?: string | null;
  event?: string | null;
  action?: string | null;
  description?: string | null;
  created_at?: string | null;
  time?: string;
  actor?: ActivityPerson | string | null;
  target?: ActivityPerson | string | null;
  actor_name?: string | null;
  actor_email?: string | null;
  subject_label?: string | null;
  subject_type?: string | null;
  subject_id?: string | null;
  ip_address?: string | null;
  user_agent?: string | null;
  browser?: string | null;
  browser_version?: string | null;
  os?: string | null;
  device_type?: string | null;
  country?: string | null;
  region?: string | null;
  city?: string | null;
  location?: string | null;
  source?: string | null;
  integration?: string | null;
  request_id?: string | null;
  impersonator?: ActivityPerson | null;
  properties?: Record<string, unknown>;
  tenant_id?: string | null;
};

export type TenantUser = {
  id: string;
  name: string;
  first_name?: string | null;
  surname?: string | null;
  email: string;
  avatar_url?: string | null;
  roles?: string[];
};

export type TenantRole = {
  name: string;
  permissions: string[];
};

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

export type Artifact = {
  id: string;
  artifact_type: string;
  original_filename?: string;
  status: string;
  file_size?: number;
  created_at: string;
};

export type Speaker = { id: string; display_name: string; diarization_label: string; identity_status: string };
export type Segment = { id: string; sequence: number; start_ms: number; end_ms: number; text: string; speaker?: Speaker };
export type Transcript = { id: string; version_number: number; status: string; language?: string; segments: Segment[] };
export type ProcessingJob = { id: string; stage: string; status: string; error_message?: string; created_at: string };

export type ParticipationRole = 'convenor' | 'attendee';
export type ParticipationStatus = 'pending' | 'accepted' | 'declined';

export type MeetingParticipation = {
  participation_role: ParticipationRole;
  status: ParticipationStatus;
};

export type MeetingParticipant = {
  id?: string;
  user_id: string;
  name?: string;
  email?: string;
  user?: { id: string; name: string; email: string };
  participation_role: ParticipationRole;
  status: ParticipationStatus;
  invited_by_user_id?: string;
};

export type Meeting = {
  id: string;
  title: string;
  provider: string;
  status: string;
  processing_status: string;
  scheduled_start_at?: string;
  created_at: string;
  artifacts?: Artifact[];
  speakers?: Speaker[];
  current_transcript?: Transcript | null;
  processing_jobs?: ProcessingJob[];
  my_participation?: MeetingParticipation | null;
  participants?: MeetingParticipant[];
};

export type Invitation = {
  id?: string;
  meeting_id?: string;
  meeting?: Meeting;
  title?: string;
  scheduled_start_at?: string;
  created_at?: string;
  participation_role?: ParticipationRole;
  status?: ParticipationStatus;
  my_participation?: MeetingParticipation | null;
};

export type DashboardPayload = {
  pending_invitations?: Invitation[];
  upcoming_meetings?: Meeting[];
  notes_ready?: Meeting[];
  total_meetings?: number;
  ready_count?: number;
  processing_count?: number;
};

export type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number };
