<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/services/api';
  import { toast } from '$lib/stores/toast';
  import type { AttendanceStats, EventModel, Guest } from '$lib/types';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import StatCard from '$lib/components/StatCard.svelte';
  import DonutChart from '$lib/components/DonutChart.svelte';
  import DeliveryBadge from '$lib/components/DeliveryBadge.svelte';
  import {
    Users,
    UserCheck,
    UserX,
    Percent,
    Download,
    Search,
    CalendarDays,
    Loader2,
    BarChart3,
    Clock,
    ShieldCheck
  } from '@lucide/svelte';

  let events = $state<EventModel[]>([]);
  let selectedEventId = $state('');
  let loadingEvents = $state(true);
  let loading = $state(false);

  let event = $state<EventModel | null>(null);
  let stats = $state<AttendanceStats | null>(null);
  let present = $state<Guest[]>([]);
  let absent = $state<Guest[]>([]);

  let activeTab = $state<'present' | 'absent'>('present');
  let search = $state('');
  let exporting = $state(false);
  let reloadKey = $state(0);

  let selectedEvent = $derived(events.find((item) => String(item.id) === selectedEventId) ?? null);

  let filteredPresent = $derived(
    present.filter((guest) => !search || guest.name.toLowerCase().includes(search.toLowerCase()))
  );
  let filteredAbsent = $derived(
    absent.filter((guest) => !search || guest.name.toLowerCase().includes(search.toLowerCase()))
  );

  let donutSegments = $derived([
    { label: 'Hadir', value: stats?.total_present ?? 0, color: '#10b981' },
    { label: 'Belum Hadir', value: stats?.total_absent ?? 0, color: '#d6d3d1' }
  ]);

  async function loadEvents() {
    loadingEvents = true;
    try {
      const res = await api.getEvents();
      events = res.data;
      const queryEvent = page.url.searchParams.get('event');
      selectedEventId = queryEvent && events.some((item) => String(item.id) === queryEvent)
        ? queryEvent
        : events[0]
          ? String(events[0].id)
          : '';
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat daftar acara.');
    } finally {
      loadingEvents = false;
    }
  }

  async function loadReport() {
    if (!selectedEventId) {
      event = null;
      stats = null;
      present = [];
      absent = [];
      return;
    }

    loading = true;
    try {
      const res = await api.getAttendance(selectedEventId);
      event = res.data.event;
      stats = res.data.stats;
      present = res.data.present;
      absent = res.data.absent;
    } catch (err: any) {
      toast.error(err.message || 'Gagal memuat laporan kehadiran.');
    } finally {
      loading = false;
    }
  }

  onMount(loadEvents);

  $effect(() => {
    const eventId = selectedEventId;
    reloadKey;
    if (!eventId) return;
    loadReport();
  });

  function changeEvent() {
    search = '';
    activeTab = 'present';
  }

  async function exportCsv() {
    if (!selectedEventId) return;
    exporting = true;
    try {
      await api.downloadAttendanceCsv(selectedEventId);
      toast.success('Laporan CSV berhasil diunduh.');
    } catch (err: any) {
      toast.error(err.message || 'Gagal mengunduh laporan.');
    } finally {
      exporting = false;
    }
  }

  function formatDateTime(value?: string | null) {
    if (!value) return '-';
    const date = new Date(value);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function formatDate(value?: string | null) {
    if (!value) return '-';
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    if (isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
  }
</script>

<svelte:head><title>Laporan Kehadiran — Invitaku</title></svelte:head>

{#if loadingEvents}
  <Spinner label="Memuat daftar acara..." full />
{:else if events.length === 0}
  <div class="card">
    <EmptyState
      title="Belum ada acara"
      message="Laporan kehadiran akan tersedia setelah acara dan data tamu dibuat."
      icon={BarChart3}
    />
  </div>
{:else}
  <div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-stone-900">Laporan Kehadiran</h2>
        <p class="text-sm text-stone-500 mt-1">
          Rekap tamu hadir, belum hadir, dan status undangan untuk evaluasi acara.
        </p>
      </div>

      <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
        <div class="w-full sm:w-72">
          <label class="label" for="report-event">Acara</label>
          <select id="report-event" class="select" bind:value={selectedEventId} onchange={changeEvent}>
            {#each events as item (item.id)}
              <option value={String(item.id)}>{item.name}</option>
            {/each}
          </select>
        </div>
        <button class="btn-primary text-sm" onclick={exportCsv} disabled={exporting || !selectedEventId}>
          {#if exporting}
            <Loader2 class="w-4 h-4 animate-spin" /> Mengunduh...
          {:else}
            <Download class="w-4 h-4" /> Export CSV
          {/if}
        </button>
      </div>
    </div>

    {#if selectedEvent}
      <div class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-[#7b2d43]/10 text-[#7b2d43] flex items-center justify-center shrink-0">
          <CalendarDays class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <p class="font-semibold text-stone-900 truncate">{selectedEvent.name}</p>
          <p class="text-xs text-stone-500 truncate">
            {formatDate(selectedEvent.event_date)} · {selectedEvent.event_time?.slice(0, 5)} WIB · {selectedEvent.location}
          </p>
        </div>
        <div class="sm:ml-auto flex items-center gap-2 text-xs text-stone-500">
          <ShieldCheck class="w-4 h-4 text-emerald-500" />
          Data kehadiran berasal dari check-in QR Code petugas.
        </div>
      </div>
    {/if}

    {#if loading}
      <div class="card"><Spinner label="Memuat laporan kehadiran..." /></div>
    {:else if stats}
      <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <StatCard title="Total Tamu" value={stats.total_guests} subtitle="Tamu pada acara ini" icon={Users} tone="primary" />
        <StatCard title="Hadir" value={stats.total_present} subtitle="Sudah check-in" icon={UserCheck} tone="emerald" />
        <StatCard title="Belum Hadir" value={stats.total_absent} subtitle="Belum check-in" icon={UserX} tone="amber" />
        <StatCard
          title="Kehadiran"
          value={`${stats.attendance_percentage}%`}
          subtitle="Persentase kehadiran"
          icon={Percent}
          tone="sky"
        />
      </div>

      <div class="grid lg:grid-cols-3 gap-5">
        <div class="card p-5 lg:col-span-1">
          <h3 class="font-semibold text-stone-900 mb-4">Proporsi Kehadiran</h3>
          <DonutChart segments={donutSegments} />
          <div class="mt-5 rounded-xl bg-stone-50 border border-stone-200 px-3 py-2.5 text-[11px] text-stone-500 leading-relaxed">
            {stats.total_present} dari {stats.total_guests} tamu sudah tercatat hadir.
          </div>
        </div>

        <div class="card lg:col-span-2 overflow-hidden">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-b border-stone-100">
            <div class="flex gap-1 bg-stone-100 rounded-xl p-1">
              <button
                class="px-4 py-1.5 rounded-lg text-xs font-medium transition-colors {activeTab === 'present'
                  ? 'bg-white text-emerald-700 shadow-sm'
                  : 'text-stone-500 hover:text-stone-700'}"
                onclick={() => (activeTab = 'present')}
              >
                Hadir ({present.length})
              </button>
              <button
                class="px-4 py-1.5 rounded-lg text-xs font-medium transition-colors {activeTab === 'absent'
                  ? 'bg-white text-amber-700 shadow-sm'
                  : 'text-stone-500 hover:text-stone-700'}"
                onclick={() => (activeTab = 'absent')}
              >
                Belum Hadir ({absent.length})
              </button>
            </div>

            <div class="relative sm:w-64">
              <Search class="w-4 h-4 text-stone-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input class="input pl-10 text-sm" placeholder="Cari nama tamu..." bind:value={search} />
            </div>
          </div>

          {#if activeTab === 'present'}
            {#if filteredPresent.length === 0}
              <EmptyState
                title={present.length === 0 ? 'Belum ada tamu hadir' : 'Tamu tidak ditemukan'}
                message={present.length === 0
                  ? 'Kehadiran akan muncul setelah petugas melakukan scan QR Code.'
                  : 'Coba ubah kata kunci pencarian.'}
                icon={UserCheck}
              />
            {:else}
              <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[640px]">
                  <thead>
                    <tr>
                      <th class="th w-12">No</th>
                      <th class="th">Nama Tamu</th>
                      <th class="th">Kategori</th>
                      <th class="th">Waktu Check-in</th>
                      <th class="th">Petugas</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-stone-100">
                    {#each filteredPresent as guest, index (guest.id)}
                      <tr class="hover:bg-stone-50/70">
                        <td class="td text-stone-400">{index + 1}</td>
                        <td class="td">
                          <p class="font-medium text-stone-800">{guest.name}</p>
                          <p class="text-[11px] text-stone-400">+{guest.whatsapp_number}</p>
                        </td>
                        <td class="td text-stone-500">{guest.category ?? '-'}</td>
                        <td class="td text-stone-600 whitespace-nowrap">
                          <span class="inline-flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5 text-emerald-500" />
                            {formatDateTime(guest.attendance?.checked_in_at)}
                          </span>
                        </td>
                        <td class="td text-stone-500">{guest.attendance?.officer?.name ?? '-'}</td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            {/if}
          {:else}
            {#if filteredAbsent.length === 0}
              <EmptyState
                title={absent.length === 0 ? 'Semua tamu sudah hadir' : 'Tamu tidak ditemukan'}
                message={absent.length === 0
                  ? 'Seluruh tamu pada acara ini sudah tercatat hadir.'
                  : 'Coba ubah kata kunci pencarian.'}
                icon={Users}
              />
            {:else}
              <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[640px]">
                  <thead>
                    <tr>
                      <th class="th w-12">No</th>
                      <th class="th">Nama Tamu</th>
                      <th class="th">Kategori</th>
                      <th class="th">Nomor WhatsApp</th>
                      <th class="th">Status Undangan</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-stone-100">
                    {#each filteredAbsent as guest, index (guest.id)}
                      <tr class="hover:bg-stone-50/70">
                        <td class="td text-stone-400">{index + 1}</td>
                        <td class="td font-medium text-stone-800">{guest.name}</td>
                        <td class="td text-stone-500">{guest.category ?? '-'}</td>
                        <td class="td text-stone-600 whitespace-nowrap">+{guest.whatsapp_number}</td>
                        <td class="td">
                          <DeliveryBadge status={guest.invitation?.delivery_status ?? 'not_sent'} />
                        </td>
                      </tr>
                    {/each}
                  </tbody>
                </table>
              </div>
            {/if}
          {/if}
        </div>
      </div>
    {/if}
  </div>
{/if}
