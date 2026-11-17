<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/services/api';
  import type { DashboardData } from '$lib/types';
  import StatCard from '$lib/components/StatCard.svelte';
  import BarChart from '$lib/components/BarChart.svelte';
  import DonutChart from '$lib/components/DonutChart.svelte';
  import Spinner from '$lib/components/Spinner.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { toast } from '$lib/stores/toast';
  import {
    CalendarDays,
    Users,
    Send,
    Clock,
    CheckCircle2,
    XCircle,
    Plus,
    ScanLine,
    ArrowUpRight,
    RotateCcw
  } from '@lucide/svelte';

  let data = $state<DashboardData | null>(null);
  let loading = $state(true);
  let error = $state('');

  async function load() {
    loading = true;
    error = '';
    try {
      const res = await api.getDashboard();
      data = res.data;
    } catch (err: any) {
      error = err.message || 'Gagal memuat dashboard.';
      toast.error(error);
    } finally {
      loading = false;
    }
  }

  onMount(load);

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

  let chartItems = $derived(
    (data?.events_chart ?? []).map((event) => ({
      label: event.name,
      value: event.guests_count,
      secondary: event.attended_count
    }))
  );

  let deliverySegments = $derived([
    { label: 'Terkirim', value: data?.delivery.sent ?? 0, color: '#10b981' },
    { label: 'Menunggu', value: data?.delivery.queued ?? 0, color: '#f59e0b' },
    { label: 'Gagal', value: data?.delivery.failed ?? 0, color: '#f43f5e' },
    { label: 'Belum dikirim', value: data?.delivery.not_sent ?? 0, color: '#a8a29e' }
  ]);
</script>

<svelte:head><title>Dashboard — Invitaku</title></svelte:head>

{#if loading}
  <Spinner label="Memuat statistik..." full />
{:else if error}
  <div class="card p-8 text-center">
    <p class="text-sm text-rose-600">{error}</p>
    <button class="btn-secondary mt-4" onclick={load}><RotateCcw class="w-4 h-4" /> Coba lagi</button>
  </div>
{:else if data}
  <div class="space-y-6">
    <!-- Greeting + quick actions -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-stone-900">Ringkasan Acara</h2>
        <p class="text-sm text-stone-500 mt-1">
          Pantau undangan, pengiriman WhatsApp, dan kehadiran tamu dalam satu tempat.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="/admin/events/new" class="btn-primary text-sm"><Plus class="w-4 h-4" /> Buat Acara</a>
        <a href="/admin/guests" class="btn-secondary text-sm"><Users class="w-4 h-4" /> Data Tamu</a>
        <a href="/admin/scanner" class="btn-secondary text-sm"><ScanLine class="w-4 h-4" /> Scanner</a>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
      <StatCard title="Total Acara" value={data.stats.total_events} icon={CalendarDays} tone="primary" />
      <StatCard title="Total Tamu" value={data.stats.total_guests} icon={Users} tone="sky" />
      <StatCard title="Undangan Terkirim" value={data.stats.invitations_sent} icon={Send} tone="emerald" />
      <StatCard
        title="Belum Terkirim"
        value={data.stats.invitations_not_sent}
        icon={Clock}
        tone="amber"
        subtitle="{data.stats.invitations_queued} menunggu konfirmasi"
      />
      <StatCard title="Tamu Hadir" value={data.stats.attended} icon={CheckCircle2} tone="emerald" />
      <StatCard title="Belum Hadir" value={data.stats.not_attended} icon={XCircle} tone="rose" />
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Attendance per event -->
      <div class="card p-5 lg:col-span-2">
        <div class="flex items-center justify-between gap-3 mb-5">
          <div>
            <h3 class="font-semibold text-stone-900">Kehadiran per Acara</h3>
            <p class="text-xs text-stone-500 mt-0.5">Perbandingan total tamu dan tamu yang sudah hadir.</p>
          </div>
          <div class="flex items-center gap-3 text-[11px] text-stone-500">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-[#7b2d43]"></span> Tamu</span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded bg-emerald-400"></span> Hadir</span>
          </div>
        </div>

        {#if chartItems.length}
          <BarChart items={chartItems} />
        {:else}
          <EmptyState title="Belum ada acara" message="Buat acara pertama untuk melihat grafik kehadiran." />
        {/if}
      </div>

      <!-- Delivery status -->
      <div class="card p-5">
        <h3 class="font-semibold text-stone-900">Status Pengiriman</h3>
        <p class="text-xs text-stone-500 mt-0.5 mb-5">Distribusi status undangan WhatsApp.</p>
        <DonutChart segments={deliverySegments} />
        <div class="mt-5 rounded-xl bg-stone-50 border border-stone-100 p-3 text-[11px] text-stone-500 leading-relaxed">
          Mode Click to Chat menandai undangan sebagai <span class="font-medium text-amber-600">Menunggu</span>.
          Konfirmasi manual setelah pesan terkirim.
        </div>
      </div>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Recent check-ins -->
      <div class="card p-5 lg:col-span-2">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-stone-900">Check-in Terbaru</h3>
          <a href="/admin/reports" class="text-xs font-medium text-[#7b2d43] hover:underline flex items-center gap-1">
            Lihat laporan <ArrowUpRight class="w-3.5 h-3.5" />
          </a>
        </div>

        {#if data.recent_checkins.length}
          <div class="divide-y divide-stone-100">
            {#each data.recent_checkins as record (record.id)}
              <div class="flex items-center gap-3 py-3">
                <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                  <CheckCircle2 class="w-4 h-4" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-stone-800 truncate">{record.guest?.name ?? 'Tamu'}</p>
                  <p class="text-[11px] text-stone-500 truncate">{record.event?.name ?? '-'}</p>
                </div>
                <div class="text-right shrink-0">
                  <p class="text-[11px] text-stone-600">{formatDateTime(record.checked_in_at)}</p>
                  <p class="text-[10px] text-stone-400">oleh {record.officer?.name ?? '-'}</p>
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <EmptyState
            title="Belum ada check-in"
            message="Kehadiran tamu akan muncul di sini setelah QR Code dipindai."
            icon={ScanLine}
          />
        {/if}
      </div>

      <!-- Attendance summary -->
      <div class="card p-5">
        <h3 class="font-semibold text-stone-900">Persentase Kehadiran</h3>
        <p class="text-xs text-stone-500 mt-0.5">Dari seluruh tamu di semua acara.</p>

        <div class="mt-6 flex items-center justify-center">
          <div class="relative w-40 h-40">
            <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
              <circle cx="60" cy="60" r="50" fill="none" stroke="#f1efec" stroke-width="12" />
              <circle
                cx="60"
                cy="60"
                r="50"
                fill="none"
                stroke="#10b981"
                stroke-width="12"
                stroke-linecap="round"
                stroke-dasharray="{Math.max(0, (data.stats.attendance_percentage / 100) * 314 - 2)} 314"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-3xl font-semibold text-stone-900">{data.stats.attendance_percentage}%</span>
              <span class="text-[10px] uppercase tracking-wider text-stone-500">Hadir</span>
            </div>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 text-center">
          <div class="rounded-xl bg-stone-50 p-3">
            <p class="text-lg font-semibold text-stone-900">{data.stats.attended}</p>
            <p class="text-[10px] uppercase tracking-wider text-stone-500">Hadir</p>
          </div>
          <div class="rounded-xl bg-stone-50 p-3">
            <p class="text-lg font-semibold text-stone-900">{data.stats.not_attended}</p>
            <p class="text-[10px] uppercase tracking-wider text-stone-500">Belum hadir</p>
          </div>
        </div>
      </div>
    </div>
  </div>
{/if}
