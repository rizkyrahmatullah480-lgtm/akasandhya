import type {
  ApiResponse,
  AttendanceStats,
  Category,
  DashboardData,
  EventModel,
  EventStats,
  Guest,
  Invitation,
  PaginationMeta,
  PublicInvitationData,
  Role,
  SantriOverview,
  SantriType,
  ScanResult,
  Template,
  User
} from '$lib/types';
import { browser } from '$app/env';
import { PUBLIC_API_URL } from '$app/env/public';

export const API_BASE = (PUBLIC_API_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');

class ApiService {
  private getToken(): string | null {
    if (browser) {
      return localStorage.getItem('invitaku_token');
    }
    return null;
  }

  async request<T = unknown>(endpoint: string, options: RequestInit = {}): Promise<ApiResponse<T>> {
    const token = this.getToken();
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers as Record<string, string>)
    };

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    const url = `${API_BASE}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

    try {
      const response = await fetch(url, { ...options, headers });
      const json = await response.json().catch(() => null);

      if (!response.ok) {
        const error: any = new Error(json?.message || `Terjadi kesalahan (HTTP ${response.status})`);
        error.status = response.status;
        error.errors = json?.errors;
        error.payload = json;
        throw error;
      }

      return json as ApiResponse<T>;
    } catch (err: any) {
      if (!err.status && err.message?.includes('Failed to fetch')) {
        err.message = `Gagal menghubungi server backend. Pastikan Laravel aktif di ${API_BASE}.`;
      }
      throw err;
    }
  }

  // --- Auth ---
  async login(credentials: { email: string; password: string }) {
    const res = await this.request<{ user: User; token: string }>('/login', {
      method: 'POST',
      body: JSON.stringify(credentials)
    });

    if (browser && res.data.token) {
      localStorage.setItem('invitaku_token', res.data.token);
      localStorage.setItem('invitaku_user', JSON.stringify(res.data.user));
    }

    return res;
  }

  async logout() {
    try {
      await this.request('/logout', { method: 'POST' });
    } finally {
      if (browser) {
        localStorage.removeItem('invitaku_token');
        localStorage.removeItem('invitaku_user');
      }
    }
  }

  async getMe() {
    return this.request<{ user: User }>('/user');
  }

  async updateProfile(data: { name: string; phone?: string }) {
    return this.request<{ user: User }>('/user/profile', {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async updatePassword(data: { current_password: string; new_password: string; new_password_confirmation: string }) {
    return this.request('/user/password', {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  // --- Dashboard ---
  async getDashboard() {
    return this.request<DashboardData>('/dashboard');
  }

  // --- Simple santri mode ---
  async getSantriOverview() {
    return this.request<SantriOverview>('/santri/overview');
  }

  async getSantriGuests(params?: { search?: string; tipe?: string; status?: string; compact?: boolean }) {
    const query = new URLSearchParams();
    if (params?.search) query.set('search', params.search);
    if (params?.tipe) query.set('tipe', params.tipe);
    if (params?.status) query.set('status', params.status);
    if (params?.compact) query.set('compact', '1');
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request<Guest[]>(`/santri/guests${qs}`);
  }

  async createSantriGuest(data: {
    name: string;
    tipe: SantriType;
    wali_angkatan?: string;
    whatsapp_number: string;
  }) {
    return this.request<Guest>('/santri/guests', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async updateSantriGuest(id: number | string, data: {
    name: string;
    tipe: SantriType;
    wali_angkatan?: string;
    whatsapp_number: string;
  }) {
    return this.request<Guest>(`/santri/guests/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteSantriGuest(id: number | string) {
    return this.request(`/santri/guests/${id}`, { method: 'DELETE' });
  }

  async toggleSantriAttendance(id: number | string) {
    return this.request<{
      status: 'Hadir' | 'Belum';
      attendance: Guest['attendance'];
      stats: {
        total: number;
        santri: number;
        alumni: number;
        present: number;
        absent: number;
        attendance_percentage: number;
      };
    }>(`/santri/guests/${id}/toggle-attendance`, { method: 'POST' });
  }

  async validateSantriScan(code: string) {
    const res = await this.request<ScanResult>('/santri/scanner/validate', {
      method: 'POST',
      body: JSON.stringify({ code })
    });
    return res as unknown as ScanResult;
  }

  async checkInSantri(code: string) {
    const res = await this.request<ScanResult>('/santri/scanner/check-in', {
      method: 'POST',
      body: JSON.stringify({ code })
    });
    return res as unknown as ScanResult;
  }

  // --- Templates ---
  async getTemplates(params?: { category?: string; search?: string }) {
    const query = new URLSearchParams();
    if (params?.category) query.set('category', params.category);
    if (params?.search) query.set('search', params.search);
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request<Template[]>(`/templates${qs}`);
  }

  async getTemplate(idOrSlug: string | number) {
    return this.request<Template>(`/templates/${idOrSlug}`);
  }

  async getTemplateCategories() {
    return this.request<Category[]>('/template-categories');
  }

  // --- Events ---
  async getEvents(params?: { search?: string; event_type?: string }) {
    const query = new URLSearchParams();
    if (params?.search) query.set('search', params.search);
    if (params?.event_type) query.set('event_type', params.event_type);
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request<EventModel[]>(`/events${qs}`);
  }

  async getEvent(id: number | string) {
    return this.request<{ event: EventModel; stats: EventStats }>(`/events/${id}`);
  }

  async createEvent(data: Partial<EventModel>) {
    return this.request<EventModel>('/events', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async updateEvent(id: number | string, data: Partial<EventModel>) {
    return this.request<EventModel>(`/events/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteEvent(id: number | string) {
    return this.request(`/events/${id}`, { method: 'DELETE' });
  }

  async uploadEventBanner(id: number | string, file: File) {
    const form = new FormData();
    form.append('banner', file);
    return this.request<{ banner: string; banner_url: string }>(`/events/${id}/banner`, {
      method: 'POST',
      body: form
    });
  }

  // --- Guests ---
  async getGuests(eventId: number | string, params?: Record<string, string | number | undefined>) {
    const query = new URLSearchParams();
    Object.entries(params ?? {}).forEach(([key, value]) => {
      if (value !== undefined && value !== '' && value !== null) query.set(key, String(value));
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request<Guest[]>(`/events/${eventId}/guests${qs}`).then((res) => ({
      ...res,
      meta: res.meta as PaginationMeta
    }));
  }

  async createGuest(eventId: number | string, data: { name: string; whatsapp_number: string; category?: string }) {
    return this.request<Guest>(`/events/${eventId}/guests`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  }

  async updateGuest(id: number | string, data: { name: string; whatsapp_number: string; category?: string }) {
    return this.request<Guest>(`/guests/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  }

  async deleteGuest(id: number | string) {
    return this.request(`/guests/${id}`, { method: 'DELETE' });
  }

  async importGuests(eventId: number | string, file: File) {
    const form = new FormData();
    form.append('file', file);
    return this.request<{ imported: number; skipped: number; errors: string[] }>(`/events/${eventId}/guests/import`, {
      method: 'POST',
      body: form
    });
  }

  // --- Invitations ---
  async getInvitations(params?: Record<string, string | number | undefined>) {
    const query = new URLSearchParams();
    Object.entries(params ?? {}).forEach(([key, value]) => {
      if (value !== undefined && value !== '' && value !== null) query.set(key, String(value));
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request<Invitation[]>(`/invitations${qs}`).then((res) => ({
      ...res,
      meta: res.meta as PaginationMeta
    }));
  }

  async getGuestInvitation(guestId: number | string) {
    return this.request<{
      guest: Guest;
      event: EventModel;
      invitation: Invitation;
      attendance: Guest['attendance'];
    }>(`/guests/${guestId}/invitation`);
  }

  async generateInvitation(guestId: number | string) {
    return this.request<{ invitation: Invitation; qr_code_url: string }>(`/guests/${guestId}/generate-invitation`, {
      method: 'POST'
    });
  }

  async sendWhatsApp(guestId: number | string, data: { mode: 'click' | 'api'; message?: string }) {
    return this.request<{ mode: 'click' | 'api'; wa_url?: string; message?: string; invitation: Invitation }>(
      `/guests/${guestId}/send-whatsapp`,
      { method: 'POST', body: JSON.stringify(data) }
    );
  }

  async markInvitationSent(invitationId: number | string) {
    return this.request<Invitation>(`/invitations/${invitationId}/mark-sent`, { method: 'POST' });
  }

  // --- Scanner ---
  async getScannerEvents() {
    return this.request<EventModel[]>('/scanner/events');
  }

  async validateScan(eventId: number | string, token: string) {
    const res = await this.request<ScanResult>('/scanner/validate', {
      method: 'POST',
      body: JSON.stringify({ event_id: Number(eventId), token })
    });
    // Scanner endpoints return ScanResult fields at the top level (not nested in data).
    return res as unknown as ScanResult;
  }

  async checkIn(eventId: number | string, token: string) {
    const res = await this.request<ScanResult>('/scanner/check-in', {
      method: 'POST',
      body: JSON.stringify({ event_id: Number(eventId), token })
    });
    return res as unknown as ScanResult;
  }

  // --- Reports ---
  async getAttendance(eventId: number | string, params?: { date?: string }) {
    const query = new URLSearchParams();
    if (params?.date) query.set('date', params.date);
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request<{
      event: EventModel;
      stats: AttendanceStats;
      present: Guest[];
      absent: Guest[];
    }>(`/events/${eventId}/attendance${qs}`);
  }

  async downloadAttendanceCsv(eventId: number | string) {
    const token = this.getToken();
    const response = await fetch(`${API_BASE}/events/${eventId}/attendance/export`, {
      headers: {
        Accept: 'text/csv',
        ...(token ? { Authorization: `Bearer ${token}` } : {})
      }
    });

    if (!response.ok) {
      throw new Error('Gagal mengunduh laporan kehadiran.');
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `kehadiran-acara-${eventId}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  // --- Public invitation ---
  async getPublicInvitation(token: string) {
    return this.request<PublicInvitationData>(`/public/invitations/${token}`);
  }

  qrUrl(token: string) {
    return `${API_BASE}/public/invitations/${token}/qr`;
  }

  // --- Users (officers) ---
  async getUsers(role?: Role) {
    const qs = role ? `?role=${role}` : '';
    return this.request<User[]>(`/users${qs}`);
  }

  async createUser(data: { name: string; email: string; password: string; password_confirmation: string; role: Role; phone?: string }) {
    return this.request<User>('/users', { method: 'POST', body: JSON.stringify(data) });
  }

  async updateUser(id: number | string, data: Partial<User> & { password?: string; password_confirmation?: string }) {
    return this.request<User>(`/users/${id}`, { method: 'PUT', body: JSON.stringify(data) });
  }

  async deleteUser(id: number | string) {
    return this.request(`/users/${id}`, { method: 'DELETE' });
  }
}

export const api = new ApiService();
