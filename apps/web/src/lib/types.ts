export type Tenant = { id: string; name: string; slug: string };
export type User = { id: string; name: string; email: string; tenant: Tenant };
export type Artifact = { id: string; artifact_type: string; original_filename?: string; status: string; file_size?: number; created_at: string };
export type Speaker = { id: string; display_name: string; diarization_label: string; identity_status: string };
export type Segment = { id: string; sequence: number; start_ms: number; end_ms: number; text: string; speaker?: Speaker };
export type Transcript = { id: string; version_number: number; status: string; language?: string; segments: Segment[] };
export type ProcessingJob = { id: string; stage: string; status: string; error_message?: string; created_at: string };
export type Meeting = {
  id: string; title: string; provider: string; status: string; processing_status: string;
  scheduled_start_at?: string; created_at: string; artifacts?: Artifact[]; speakers?: Speaker[];
  current_transcript?: Transcript | null; processing_jobs?: ProcessingJob[];
};
export type Paginated<T> = { data: T[]; current_page: number; last_page: number; total: number };
