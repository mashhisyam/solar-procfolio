export type Tone = 'bad' | 'warn' | 'info' | 'ok';

export interface PageProps {
  app: { name: string; today: string };
  period: { key: string; since: string | null; options: { value: string; label: string }[] };
  auth: { user: { id: number; name: string; email: string; role: string } | null; can: { edit: boolean; admin: boolean } };
  flash: { success?: string | null; error?: string | null; next?: { type: string; amendment_id?: number | null } | null };
  errors: Record<string, string>;
  [key: string]: unknown;
}

export interface Alert { code: string; tone: Tone; label: string; target: string }

/** Baris tabel monitoring (Dashboard & Daftar Pengadaan). */
export interface ProcRow {
  code: string; spp_number: string | null; title: string; rab_value: number; method: 'SPP Pusat' | 'SPP Kemitraan'; proc_method: string | null;
  priority: 'Normal' | 'Urgent'; pengaju_name: string;
  progress: { approval: (string | null)[]; contract: boolean; terms: { paid: boolean; scheduled: boolean; stage: string | null }[] };
  track: 'pengadaan' | 'pasca'; amendment: boolean; stage: string | null; cycle: number; activity: string | null; pic: string | null;
  days: number | null; level: 'ok' | 'warn' | 'bad' | null; late_days: number; status: string; last_update: string; last_update_days: number;
  alerts: Alert[]; score: number; registered_at: string; vendor: string | null; sub_bidang: string | null; stage_key: string | null; pic_id: number | null;
  customer_contract_ids: number[]; position: string | null; process_days: number; cost: number; category: string | null; is_draft?: boolean; cancelled?: boolean; cancel_type?: 'batal' | 'gagal' | null; amendment_running?: boolean;
}

export interface StepNode { key: string; name: string; group?: string | null; state: 'done' | 'current' | 'returned' | 'skipped' | 'pending'; cycles: number; date: string | null; days: number | null; scheduled: boolean }

export interface Activity { date: string; category: string; note: string | null; kind: string }
export interface HistoryVisit { id: number; stage: string; cycle: number; status: string; entered_at: string; completed_at: string | null; days: number | null; pic: string | null; activity: string | null; return_reason: string | null; return_type: 'minor' | 'mayor' | null; resume_key: string | null; amendment_id: number | null; activities: Activity[] }
export interface CurrentVisit { id: number; stage_key: string; resume_key?: string | null; stage: string; cycle: number; entered_at: string; pic_id: number | null; pic: string | null; activity: string | null; amendment_id: number | null; term_id: number | null; days: number | null; scheduled: boolean; level: 'ok' | 'warn' | 'bad' | null }
export interface StageDef { key: string; name: string; group?: string | null; enabled: boolean; optional?: boolean; pic_id: number | null; default_activities: string[] }

export interface Pic { id: number; name: string; role: string | null; title?: string | null; company?: string | null }
export interface Option { id: number; name: string }

export interface Term {
  id: number; seq: number; name: string; kind: string; percent: number | null; amount: number; planned_end: string | null; paid_at: string | null; bucket_code: string | null; bucket_number: string | null; bucket_link: string | null;
  plan: { start: string; end: string | null; days: number | null } | null; scheduled: boolean; late_days: number;
  current: CurrentVisit | null; nodes: StepNode[]; history: HistoryVisit[]; ba_ids: number[]; ba_ready: boolean;
  gr: { id: number; status: string; number: string | null; gr_type: string | null; amount: number | null; submitted_at: string | null; gr_date: string | null; source_app: string | null; pic_id: number | null; pic: string | null; link: string | null; note: string | null; ba_ids: number[] } | null;
  invoice: { id: number; number: string; date: string; amount: number; status: string; pic_id: number | null; pic: string | null; note: string | null; checklist: Record<string, { required: boolean; done: boolean }>; payment: { date: string; amount: number } | null } | null;
}

export interface BaSummary { id: number; type: string; number: string | null; title: string; date: string | null; link: string | null; status: string; round: number; signed: number; total: number; days: number; term_ids: number[]; term_seqs: number[] }
