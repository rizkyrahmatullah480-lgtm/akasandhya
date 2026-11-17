export type Role = 'admin' | 'scanner';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  phone?: string | null;
  avatar?: string | null;
  created_at?: string;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  description?: string | null;
  templates_count?: number;
}

export interface TemplateConfig {
  theme?: string;
  primary_color?: string;
  secondary_color?: string;
  accent_color?: string;
  bg_color?: string;
  text_color?: string;
  font_heading?: string;
  font_body?: string;
  decoration_style?: string;
  cover_style?: string;
}

export interface Template {
  id: number;
  category_id: number;
  name: string;
  slug: string;
  description?: string | null;
  preview_image?: string | null;
  thumbnail?: string | null;
  configuration: TemplateConfig;
  config: TemplateConfig;
  is_premium: boolean;
  is_active: boolean;
  category?: Category;
}

export type EventType =
  | 'pernikahan'
  | 'ulang_tahun'
  | 'seminar'
  | 'wisuda'
  | 'santri'
  | 'formal'
  | 'keluarga'
  | 'lainnya';

export interface EventModel {
  id: number;
  name: string;
  event_type: EventType;
  organizer: string;
  event_date: string;
  event_time: string;
  location: string;
  address?: string | null;
  description?: string | null;
  banner?: string | null;
  banner_url?: string | null;
  maps_url?: string | null;
  template_id?: number | null;
  created_by?: number | null;
  template?: Template | null;
  guests?: Guest[];
  guests_count?: number;
  attended_count?: number;
  created_at?: string;
  updated_at?: string;
}

export type DeliveryStatus = 'not_sent' | 'queued' | 'sent' | 'failed';

export interface Attendance {
  id: number;
  guest_id: number;
  event_id: number;
  status: string;
  checked_in_at: string;
  checked_in_by?: number | null;
  officer?: User | null;
  guest?: Guest;
  event?: EventModel;
}

export interface Invitation {
  id: number;
  guest_id: number;
  event_id: number;
  invitation_url: string;
  qr_code_path: string | null;
  delivery_status: DeliveryStatus;
  sent_at: string | null;
  delivery_error?: string | null;
  provider_message_id?: string | null;
  guest?: Guest;
  event?: EventModel;
  attendance?: Attendance | null;
}

export interface Guest {
  id: number;
  event_id: number;
  name: string;
  whatsapp_number: string;
  category?: string | null;
  wali_angkatan?: string | null;
  santri_code?: string | null;
  unique_token: string;
  event?: EventModel;
  invitation?: Invitation | null;
  attendance?: Attendance | null;
  created_at?: string;
  updated_at?: string;
}

export type SantriType = 'Santri' | 'Alumni';

export interface SantriStats {
  total: number;
  santri: number;
  alumni: number;
  present: number;
  absent: number;
  attendance_percentage: number;
}

export interface SantriOverview {
  event: EventModel;
  stats: SantriStats;
  recent: Attendance[];
}

export interface AttendanceStats {
  total_guests: number;
  total_present: number;
  total_absent: number;
  attendance_percentage: number;
}

export interface EventStats {
  total_guests: number;
  attended: number;
  not_attended: number;
  invitations_sent: number;
  invitations_not_sent: number;
  attendance_percentage: number;
}

export interface DashboardStats {
  total_events: number;
  total_guests: number;
  invitations_sent: number;
  invitations_not_sent: number;
  invitations_queued: number;
  invitations_failed: number;
  attended: number;
  not_attended: number;
  attendance_percentage: number;
}

export interface EventChartItem {
  id: number;
  name: string;
  event_type: EventType;
  event_date: string;
  guests_count: number;
  attended_count: number;
  attendance_percentage: number;
}

export interface DashboardData {
  stats: DashboardStats;
  delivery: {
    not_sent: number;
    queued: number;
    sent: number;
    failed: number;
  };
  events_chart: EventChartItem[];
  recent_checkins: Attendance[];
}

export interface PublicInvitationData {
  guest: {
    id: number;
    name: string;
    category?: string | null;
    wali_angkatan?: string | null;
    santri_code?: string | null;
    unique_token: string;
  };
  event: EventModel;
  template: Template | null;
  invitation: Invitation | null;
  attendance: {
    status: string;
    checked_in_at: string;
    checked_in_by?: string | null;
  } | null;
  checked_in: boolean;
}

export interface ScanResult {
  success: boolean;
  valid?: boolean;
  checked_in?: boolean;
  already_checked_in?: boolean;
  code?: string;
  message: string;
  data?: {
    guest?: Guest;
    event?: EventModel;
    invitation?: Invitation | null;
    attendance?: Attendance | null;
    selected_event?: string;
    invitation_event?: string;
  };
}

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  message?: string;
  data: T;
  meta?: PaginationMeta;
  errors?: Record<string, string[]>;
}
