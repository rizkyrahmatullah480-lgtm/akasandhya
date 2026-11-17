<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { EventModel } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import ConfirmModal from '$lib/components/ConfirmModal.svelte';
  import {
    Plus,
    Search,
    CalendarDays,
    MapPin,
    Users,
    CheckCircle2,
    Pencil,
    Trash2,
    Eye,
    ScanLine,
    BarChart3,
    CalendarX2
  } from '@lucide/svelte';

  let events = $state<EventModel[]>([]);
  let loading = $state(true);
  let search = $state('');
  let typeFilter = $state('');
  let deleting = $state<EventModel | null>(null);
  let removing = $state(false);

  const typeLabels: Record<string, string> = {
    pernikahan: 'Pernikahan',
    ulang_tahun: 'Ulang Tahun',
    seminar: 'Seminar',
    wisuda: 'Wisuda',
    formal: 'Acara Formal',
    keluarga: 'Acara Keluarga',
    lainnya: 'Acara Lainnya'
  };

  const typeColors: Record<string, string> = {
    pernikahan: 'bg-rose-50 text-rose-700 border-rose-200',
    ulang_tahun: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
    seminar: 'bg-sky-50 text-sky-700 border-sky-200',
    wisuda: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    formal: 'bg-amber-50 text-amber-700 border-amber-200',
    keluarga: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    lainnya: 'bg-stone-100 text-stone-600 border-stone-200'
  };

  async function load() {
    loading = true;
    try {
      const res = await api.getEvents();
      events = res.data;
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat acara.');
    } finally {
      loading = false;
    }
  }

  onMount(load);

  let filtered = $derived(
    events.filter((event) => {
      const matchSearch =
        !search ||
        event.name.toLowerCase().includes(search.toLowerCase()) ||
        event.organizer.toLowerCase().includes(search.toLowerCase()) ||
        event.location.toLowerCase().includes(search.toLowerCase());
      const matchType = !typeFilter || event.event_type === typeFilter;
      return matchSearch && matchType;
    })
  );

  function formatDate(value: string) {
    if (!value) return '-';
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }

  async function confirmDelete() {
    if (!deleting) return;
    removing = true;
    try {
      const target = deleting;
      await api.deleteEvent(target.id);
      toast.success('Acara berhasil dihapus.');
      events = events.filter((event) => event.id !== target.id);
      deleting = null;
    } catch (err: any) {
      toast.error(err.message || 'Gagal menghapus acara.');
    } finally {
      removing = false;
    }
  }
</script>

<svelte:head><title>Acara — Invitaku</title></svelte:head>

<div class="space-y-6">
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-semibold text-stone-900">Daftar Acara</h2>
      <p class="text-sm text-stone-500 mt-1">{events.length} acara terdaftar. Pilih desain, tamu, dan kelola kehadiran.</p>
    </div>
    <a href="/admin/events/new" class="btn-primary text-sm"><Plus class="w-4 h-4" /> Buat Acara</a>
  </div>

  <div class="card p-4 flex flex-col md:flex-row gap-3">
    <div class="relative flex-1">
      <Search class="w-4 h-4 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
      <input class="input pl-10" placeholder="Cari nama acara, penyelenggara, atau lokasi..." bind:value={search} />
    </div>
    <select class="select md:w-56" bind:value={typeFilter}>
      <option value="">Semua jenis acara</option>
      {#each Object.entries(typeLabels) as [value, label] (value)}
        <option {value}>{label}</option>
      {/each}
    </select>
  </div>

  {#if loading}
    <Spinner label="Memuat acara..." full />
  {:else if filtered.length === 0}
    <div class="card">
      <EmptyState
        title={events.length === 0 ? 'Belum ada acara' : 'Acara tidak ditemukan'}
        message={events.length === 0
          ? 'Buat acara pertama Anda, lalu tambahkan tamu dan kirim undangan digital.'
          : 'Coba ubah kata kunci atau filter jenis acara.'}
        icon={CalendarX2}
      >
        {#snippet action()}
          {#if events.length === 0}
            <a href="/admin/events/new" class="btn-primary text-sm"><Plus class="w-4 h-4" /> Buat Acara</a>
          {/if}
        {/snippet}
      </EmptyState>
    </div>
  {:else}
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
      {#each filtered as event (event.id)}
        <article class="card overflow-hidden flex flex-col group">
          <a href={`/admin/events/${event.id}`} class="relative block h-40 bg-stone-100 overflow-hidden">
            {#if event.template?.preview_image}
              <img
                src={event.template.preview_image}
                alt={event.template.name}
                class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300"
              />
            {:else}
              <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-[#7b2d43]/15 to-[#d6b98c]/20 text-[#7b2d43]">
                <CalendarDays class="w-10 h-10" />
              </div>
            {/if}
            <span class="absolute top-3 left-3 badge border {typeColors[event.event_type] ?? typeColors.lainnya}">
              {typeLabels[event.event_type] ?? event.event_type}
            </span>
          </a>

          <div class="p-5 flex-1 flex flex-col">
            <a href={`/admin/events/${event.id}`} class="font-semibold text-stone-900 hover:text-[#7b2d43] line-clamp-2">
              {event.name}
            </a>
            <p class="text-xs text-stone-500 mt-1">{event.organizer}</p>

            <div class="mt-3 space-y-1.5 text-xs text-stone-600">
              <p class="flex items-center gap-2"><CalendarDays class="w-3.5 h-3.5 text-stone-400" /> {formatDate(event.event_date)}</p>
              <p class="flex items-center gap-2"><MapPin class="w-3.5 h-3.5 text-stone-400" /> <span class="truncate">{event.location}</span></p>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
              <div class="rounded-xl bg-stone-50 px-3 py-2">
                <p class="text-sm font-semibold text-stone-900 flex items-center gap-1.5">
                  <Users class="w-3.5 h-3.5 text-stone-400" /> {event.guests_count ?? 0}
                </p>
                <p class="text-[10px] uppercase tracking-wider text-stone-500">Tamu</p>
              </div>
              <div class="rounded-xl bg-emerald-50 px-3 py-2">
                <p class="text-sm font-semibold text-emerald-700 flex items-center gap-1.5">
                  <CheckCircle2 class="w-3.5 h-3.5" /> {event.attended_count ?? 0}
                </p>
                <p class="text-[10px] uppercase tracking-wider text-emerald-600">Hadir</p>
              </div>
            </div>

            <div class="mt-4 pt-4 border-t border-stone-100 flex items-center justify-between gap-1">
              <div class="flex items-center gap-1">
                <a href={`/admin/events/${event.id}`} class="p-2 rounded-lg text-stone-500 hover:bg-stone-100" title="Detail">
                  <Eye class="w-4 h-4" />
                </a>
                <a href={`/admin/events/${event.id}/edit`} class="p-2 rounded-lg text-stone-500 hover:bg-stone-100" title="Edit">
                  <Pencil class="w-4 h-4" />
                </a>
                <a href={`/admin/guests?event=${event.id}`} class="p-2 rounded-lg text-stone-500 hover:bg-stone-100" title="Data tamu">
                  <Users class="w-4 h-4" />
                </a>
                <a href={`/admin/reports?event=${event.id}`} class="p-2 rounded-lg text-stone-500 hover:bg-stone-100" title="Laporan">
                  <BarChart3 class="w-4 h-4" />
                </a>
                <a href={`/admin/scanner?event=${event.id}`} class="p-2 rounded-lg text-stone-500 hover:bg-stone-100" title="Scanner">
                  <ScanLine class="w-4 h-4" />
                </a>
              </div>
              <button
                onclick={() => (deleting = event)}
                class="p-2 rounded-lg text-rose-500 hover:bg-rose-50"
                title="Hapus"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </article>
      {/each}
    </div>
  {/if}
</div>

<ConfirmModal
  open={!!deleting}
  title="Hapus acara?"
  message={`Acara "${deleting?.name ?? ''}" beserta seluruh data tamu, undangan, dan kehadirannya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`}
  confirmText={removing ? 'Menghapus...' : 'Ya, hapus'}
  type="danger"
  onConfirm={confirmDelete}
  onCancel={() => (deleting = null)}
/>
