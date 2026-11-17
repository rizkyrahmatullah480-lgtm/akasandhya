<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import type { EventModel, EventStats, Guest } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import AttendanceBadge from '$lib/components/AttendanceBadge.svelte';
  import DeliveryBadge from '$lib/components/DeliveryBadge.svelte';
  import { toast } from '$lib/stores/toast';
  import {
    CalendarDays,
    Clock,
    MapPin,
    Users,
    CheckCircle2,
    Send,
    Pencil,
    ArrowLeft,
    ExternalLink,
    ScanLine,
    BarChart3,
    UserPlus
  } from '@lucide/svelte';

  let event = $state<EventModel | null>(null);
  let stats = $state<EventStats | null>(null);
  let loading = $state(true);
  let error = $state('');

  let id = $derived(page.params.id);

  const typeLabels: Record<string, string> = {
    pernikahan: 'Pernikahan',
    ulang_tahun: 'Ulang Tahun',
    seminar: 'Seminar',
    wisuda: 'Wisuda',
    formal: 'Acara Formal',
    keluarga: 'Acara Keluarga',
    lainnya: 'Acara Lainnya'
  };

  async function load() {
    if (!id) return;
    loading = true;
    try {
      const res = await api.getEvent(id);
      event = res.data.event;
      stats = res.data.stats;
    } catch (err: any) {
      error = err.message || 'Acara tidak ditemukan.';
      toast.error(error);
    } finally {
      loading = false;
    }
  }

  onMount(load);

  function formatDate(value?: string) {
    if (!value) return '-';
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }

  let guests = $derived((event?.guests ?? []) as Guest[]);
</script>

<svelte:head><title>{event?.name ?? 'Detail Acara'} — Invitaku</title></svelte:head>

{#if loading}
  <Spinner label="Memuat detail acara..." full />
{:else if error}
  <div class="card p-8 text-center">
    <p class="text-sm text-rose-600">{error}</p>
    <a href="/admin/events" class="btn-secondary mt-4">Kembali ke daftar acara</a>
  </div>
{:else if event}
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div class="flex items-start gap-3">
        <a href="/admin/events" class="p-2 rounded-lg text-stone-500 hover:bg-stone-200 mt-0.5" aria-label="Kembali">
          <ArrowLeft class="w-5 h-5" />
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-xl font-semibold text-stone-900">{event.name}</h2>
            <span class="badge bg-[#7b2d43]/10 text-[#7b2d43] border border-[#7b2d43]/20">
              {typeLabels[event.event_type] ?? event.event_type}
            </span>
          </div>
          <p class="text-sm text-stone-500 mt-1">Diselenggarakan oleh {event.organizer}</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <a href={`/admin/events/${event.id}/edit`} class="btn-secondary text-sm"><Pencil class="w-4 h-4" /> Edit</a>
        <a href={`/admin/guests?event=${event.id}`} class="btn-primary text-sm"><UserPlus class="w-4 h-4" /> Kelola Tamu</a>
      </div>
    </div>

    {#if stats}
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wider text-stone-500">Total Tamu</p>
          <p class="mt-2 text-2xl font-semibold text-stone-900 flex items-center gap-2"><Users class="w-5 h-5 text-stone-400" /> {stats.total_guests}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wider text-stone-500">Hadir</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600 flex items-center gap-2"><CheckCircle2 class="w-5 h-5" /> {stats.attended}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wider text-stone-500">Undangan Terkirim</p>
          <p class="mt-2 text-2xl font-semibold text-sky-600 flex items-center gap-2"><Send class="w-5 h-5" /> {stats.invitations_sent}</p>
        </div>
        <div class="card p-4">
          <p class="text-xs uppercase tracking-wider text-stone-500">Kehadiran</p>
          <p class="mt-2 text-2xl font-semibold text-stone-900">{stats.attendance_percentage}%</p>
        </div>
      </div>
    {/if}

    <div class="grid lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 card overflow-hidden">
        {#if event.banner_url}
          <img src={event.banner_url} alt="Banner {event.name}" class="w-full h-48 sm:h-60 object-cover" />
        {:else if event.template?.preview_image}
          <img src={event.template.preview_image} alt={event.template.name} class="w-full h-48 sm:h-60 object-cover" />
        {/if}

        <div class="p-5 sm:p-6 space-y-4">
          <h3 class="font-semibold text-stone-900">Informasi Acara</h3>

          <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div class="flex items-start gap-3">
              <CalendarDays class="w-4 h-4 text-stone-400 mt-0.5" />
              <div>
                <p class="text-xs text-stone-500">Tanggal</p>
                <p class="font-medium text-stone-800">{formatDate(event.event_date)}</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <Clock class="w-4 h-4 text-stone-400 mt-0.5" />
              <div>
                <p class="text-xs text-stone-500">Waktu</p>
                <p class="font-medium text-stone-800">{event.event_time?.slice(0, 5)} WIB</p>
              </div>
            </div>
            <div class="flex items-start gap-3 sm:col-span-2">
              <MapPin class="w-4 h-4 text-stone-400 mt-0.5" />
              <div>
                <p class="text-xs text-stone-500">Lokasi</p>
                <p class="font-medium text-stone-800">{event.location}</p>
                {#if event.address}<p class="text-xs text-stone-500 mt-0.5">{event.address}</p>{/if}
              </div>
            </div>
          </div>

          {#if event.description}
            <div class="pt-4 border-t border-stone-100">
              <p class="text-xs text-stone-500 mb-1">Deskripsi</p>
              <p class="text-sm text-stone-700 leading-relaxed whitespace-pre-line">{event.description}</p>
            </div>
          {/if}

          {#if event.maps_url}
            <a href={event.maps_url} target="_blank" rel="noopener noreferrer" class="btn-secondary text-xs">
              <ExternalLink class="w-3.5 h-3.5" /> Buka di Google Maps
            </a>
          {/if}
        </div>
      </div>

      <div class="space-y-4">
        <div class="card p-5">
          <h3 class="font-semibold text-stone-900">Aksi Cepat</h3>
          <div class="mt-4 space-y-2">
            <a href={`/admin/guests?event=${event.id}`} class="flex items-center gap-3 rounded-xl border border-stone-200 px-3 py-2.5 text-sm hover:bg-stone-50">
              <Users class="w-4 h-4 text-[#7b2d43]" /> Data Tamu & Undangan
            </a>
            <a href={`/admin/reports?event=${event.id}`} class="flex items-center gap-3 rounded-xl border border-stone-200 px-3 py-2.5 text-sm hover:bg-stone-50">
              <BarChart3 class="w-4 h-4 text-[#7b2d43]" /> Laporan Kehadiran
            </a>
            <a href={`/admin/scanner?event=${event.id}`} class="flex items-center gap-3 rounded-xl border border-stone-200 px-3 py-2.5 text-sm hover:bg-stone-50">
              <ScanLine class="w-4 h-4 text-[#7b2d43]" /> Buka Scanner
            </a>
          </div>
        </div>

        <div class="card p-5">
          <h3 class="font-semibold text-stone-900">Desain Undangan</h3>
          {#if event.template}
            <div class="mt-3 rounded-xl overflow-hidden border border-stone-200">
              {#if event.template.preview_image}
                <img src={event.template.preview_image} alt={event.template.name} class="w-full h-36 object-cover" />
              {/if}
              <div class="p-3 bg-stone-50">
                <p class="text-sm font-medium text-stone-800">{event.template.name}</p>
                <p class="text-xs text-stone-500">{event.template.category?.name ?? 'Umum'}</p>
              </div>
            </div>
          {:else}
            <p class="text-sm text-stone-500 mt-2">Belum ada desain dipilih.</p>
            <a href="/admin/templates" class="btn-secondary text-xs mt-3">Pilih Desain</a>
          {/if}
        </div>
      </div>
    </div>

    <div class="card">
      <div class="flex items-center justify-between px-5 py-4 border-b border-stone-100">
        <div>
          <h3 class="font-semibold text-stone-900">Tamu Terbaru</h3>
          <p class="text-xs text-stone-500">Menampilkan {Math.min(guests.length, 8)} dari {guests.length} tamu.</p>
        </div>
        <a href={`/admin/guests?event=${event.id}`} class="text-xs font-medium text-[#7b2d43] hover:underline">Lihat semua</a>
      </div>

      {#if guests.length === 0}
        <div class="p-8 text-center">
          <p class="text-sm text-stone-500">Belum ada tamu pada acara ini.</p>
          <a href={`/admin/guests?event=${event.id}`} class="btn-primary text-sm mt-4"><UserPlus class="w-4 h-4" /> Tambah Tamu</a>
        </div>
      {:else}
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th class="th">Nama</th>
                <th class="th">WhatsApp</th>
                <th class="th">Pengiriman</th>
                <th class="th">Kehadiran</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              {#each guests.slice(0, 8) as guest (guest.id)}
                <tr class="hover:bg-stone-50/60">
                  <td class="td font-medium text-stone-800">{guest.name}</td>
                  <td class="td text-stone-500">{guest.whatsapp_number}</td>
                  <td class="td">
                    <DeliveryBadge status={guest.invitation?.delivery_status ?? 'not_sent'} />
                  </td>
                  <td class="td"><AttendanceBadge checkedIn={!!guest.attendance} /></td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      {/if}
    </div>
  </div>
{/if}
